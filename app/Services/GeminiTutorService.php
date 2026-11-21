<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Document;
use App\Models\Lesson;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GeminiTutorService
{
    public function __construct(private DocumentConversionService $conversionService) {}

    /**
     * Tạo HTTP client gọi Gemini API (có read_timeout riêng cho streaming để không bao giờ bị treo kết nối).
     */
    private function geminiHttp(string $apiKey, int $timeout, bool $stream = false): PendingRequest
    {
        $options = [
            'read_timeout' => $stream ? min($timeout, 45) : $timeout,
        ];
        if ($stream) {
            $options['stream'] = true;
        }

        $headers = [
            'x-goog-api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ];
        if ($stream) {
            $headers['Accept'] = 'text/event-stream';
        }

        return Http::withOptions($options)
            ->connectTimeout(10)
            ->timeout($timeout)
            ->withHeaders($headers);
    }

    /**
     * Xây dựng chuỗi model dự phòng (ưu tiên model cấu hình, nếu dính 503/429/timeout sẽ tự chuyển tiếp).
     *
     * @return array<int, string>
     */
    private function buildModelChain(string $preferredModel): array
    {
        $primary = (string) config('services.gemini.model', 'gemini-3.5-flash-lite');
        $fast = (string) config('services.gemini.fast_model', 'gemini-3.1-flash-lite');

        return array_values(array_unique(array_filter([
            $preferredModel,
            $fast,
            $primary,
            'gemini-3.1-flash-lite',
            'gemini-3.5-flash-lite',
        ])));
    }

    /**
     * Chọn mức thinking_level hợp lệ tương ứng với từng dòng model.
     */
    private function thinkingLevelForModel(string $model, bool $fastMode): string
    {
        if (str_contains($model, 'flash-lite')) {
            return 'minimal';
        }

        return $fastMode ? 'low' : 'low';
    }

    /**
     * Gửi request tới /v1/interactions có tự động dự phòng qua chuỗi model khi gặp 503/429 hoặc timeout.
     *
     * @param  array<string, mixed>  $payload
     */
    private function postInteractionWithFallback(
        string $baseUrl,
        string $apiKey,
        array $payload,
        int $timeout,
        bool $stream = false
    ): Response {
        $preferred = (string) ($payload['model'] ?? config('services.gemini.model', 'gemini-3.5-flash-lite'));
        $chain = $this->buildModelChain($preferred);
        $lastResponse = null;

        foreach ($chain as $idx => $candidateModel) {
            $isFirst = ($idx === 0);
            $hasNext = ($idx < count($chain) - 1);
            $attemptTimeout = $hasNext ? min($timeout, 40) : $timeout;

            $attemptPayload = $payload;
            $attemptPayload['model'] = $candidateModel;
            if (! $isFirst) {
                unset($attemptPayload['previous_interaction_id']);
                if (isset($attemptPayload['generation_config']) && is_array($attemptPayload['generation_config'])) {
                    $attemptPayload['generation_config']['thinking_level'] = $this->thinkingLevelForModel($candidateModel, true);
                }
            }

            try {
                $response = $this->geminiHttp($apiKey, $attemptTimeout, $stream)
                    ->post("{$baseUrl}/v1/interactions", $attemptPayload);
            } catch (ConnectionException $e) {
                if (! $hasNext) {
                    throw new RuntimeException('Kết nối tới máy chủ Gemini bị chậm hoặc quá thời gian chờ. Vui lòng thử lại sau vài giây.');
                }
                Log::warning('Gemini model timed out, switching to fallback', [
                    'failed_model' => $candidateModel,
                    'next_model' => $chain[$idx + 1],
                ]);

                continue;
            }

            $lastResponse = $response;

            if ($hasNext && in_array($response->status(), [429, 500, 502, 503, 504], true)) {
                Log::warning('Gemini model busy/unavailable, switching to fallback', [
                    'failed_model' => $candidateModel,
                    'next_model' => $chain[$idx + 1],
                    'status' => $response->status(),
                ]);

                continue;
            }

            return $response;
        }

        if ($lastResponse instanceof Response) {
            return $lastResponse;
        }

        throw new RuntimeException('Các máy chủ Gemini đang tạm bận. Vui lòng thử lại sau ít phút.');
    }

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.api_key'));
    }

    /**
     * Tính mã băm nội dung học liệu đã duyệt của bài học để tự làm mới cache khi giảng viên sửa/thêm video hoặc tài liệu.
     */
    public function computeLessonHash(Lesson $lesson): string
    {
        /** @var Collection<int, Document> $documents */
        $documents = $lesson->relationLoaded('documents')
            ? $lesson->documents->filter(fn (Document $d) => $d->isApproved())
            : $lesson->documents()->approved()->get();

        $signature = [
            'lesson_id' => $lesson->id,
            'lesson_title' => $lesson->title,
            'docs' => $documents->sortBy('id')->values()->map(fn (Document $d) => [
                'id' => $d->id,
                'type' => $d->type,
                'title' => $d->title,
                'url' => $d->url,
                'file_path' => $d->file_path,
                'updated_at' => $d->updated_at?->timestamp,
            ])->all(),
        ];

        return hash('sha256', json_encode($signature, JSON_UNESCAPED_UNICODE) ?: '');
    }

    public function hasFreshContext(Lesson $lesson): bool
    {
        return filled($lesson->ai_context_text)
            && $lesson->ai_context_hash === $this->computeLessonHash($lesson);
    }

    /**
     * Đọc ngầm toàn bộ video & tài liệu của bài học 1 lần duy nhất rồi lưu vào DB
     * để mọi lượt hỏi sau của toàn bộ học viên trong lớp trả lời chỉ trong ~2-3 giây.
     *
     * @return array{status: string, cached: bool}
     */
    public function prepareLessonContext(Course $course, Lesson $lesson): array
    {
        if (! $this->isConfigured()) {
            return ['status' => 'unconfigured', 'cached' => false];
        }

        $currentHash = $this->computeLessonHash($lesson);
        if (filled($lesson->ai_context_text) && $lesson->ai_context_hash === $currentHash) {
            return ['status' => 'ready', 'cached' => true];
        }

        $apiKey = (string) config('services.gemini.api_key');
        $model = (string) config('services.gemini.model', 'gemini-3.5-flash-lite');
        $baseUrl = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com'), '/');
        $timeout = (int) config('services.gemini.timeout', 90);

        [$mediaBlocks, $textContext] = $this->collectLessonContext($lesson, $apiKey, $baseUrl, $timeout);

        // Nếu bài học không có video/PDF (chỉ có Word/PPT/TXT hoặc không có file) -> lưu trực tiếp không cần gọi Gemini!
        if (empty($mediaBlocks)) {
            $fallbackText = $textContext !== ''
                ? $textContext
                : "Bài học: {$lesson->title} (thuộc khóa học {$course->title}).";

            $lesson->forceFill([
                'ai_context_text' => $fallbackText,
                'ai_context_hash' => $currentHash,
                'ai_context_ready_at' => now(),
            ])->save();

            return ['status' => 'ready', 'cached' => false];
        }

        $contentParts = $mediaBlocks;
        if ($textContext !== '') {
            $contentParts[] = ['type' => 'text', 'text' => $textContext];
        }
        $contentParts[] = [
            'type' => 'text',
            'text' => <<<'INDEX_PROMPT'
Hãy xem toàn bộ video bài giảng và đọc kỹ toàn bộ tài liệu đính kèm của bài học này, sau đó lập một BẢN GHI CHÚ KIẾN THỨC CHI TIẾT bằng tiếng Việt để làm cơ sở dữ liệu trả lời nhanh cho học viên.

Yêu cầu cấu trúc:
1. TÓM TẮT MỤC TIÊU BÀI HỌC (3-5 gạch đầu dòng).
2. MỤC LỤC CHI TIẾT VIDEO THEO MỐC THỜI GIAN (ghi rõ mốc mm:ss cho từng ý giảng, định lý, công thức, ví dụ minh họa và lưu ý của giảng viên).
3. KIẾN THỨC, CÔNG THỨC & VÍ DỤ TRONG TÀI LIỆU ĐÍNH KÈM (tóm tắt đầy đủ các định nghĩa, dạng bài tập, phương pháp giải và đáp án/ví dụ tiêu biểu nếu có).

Hãy viết cô đọng, đầy đủ dữ kiện chính xác để có thể dùng trả lời bất kỳ câu hỏi chi tiết nào của học viên.
INDEX_PROMPT,
        ];

        $preparePayload = [
            'model' => $model,
            'system_instruction' => $this->buildSystemInstruction($course, $lesson),
            'input' => [
                [
                    'type' => 'user_input',
                    'content' => $contentParts,
                ],
            ],
            'generation_config' => [
                'thinking_level' => $this->thinkingLevelForModel($model, true),
                'max_output_tokens' => 8192,
            ],
        ];

        try {
            $response = $this->postInteractionWithFallback($baseUrl, $apiKey, $preparePayload, max($timeout, 90), false);
        } catch (\Throwable $e) {
            Log::warning('Gemini background pre-read exception', [
                'lesson_id' => $lesson->id,
                'message' => $e->getMessage(),
            ]);

            return ['status' => 'failed', 'cached' => false];
        }

        if (! $response->successful()) {
            Log::warning('Gemini background pre-read failed', [
                'lesson_id' => $lesson->id,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return ['status' => 'failed', 'cached' => false];
        }

        $body = $response->json();
        $extracted = $this->extractOutputText(is_array($body) ? $body : []);
        if ($extracted === '') {
            return ['status' => 'failed', 'cached' => false];
        }

        $fullNotes = $extracted;
        if ($textContext !== '') {
            $fullNotes .= "\n\n---\n".$textContext;
        }

        $lesson->forceFill([
            'ai_context_text' => $fullNotes,
            'ai_context_hash' => $currentHash,
            'ai_context_ready_at' => now(),
        ])->save();

        return ['status' => 'ready', 'cached' => false];
    }

    /**
     * Các mẫu câu hỏi tóm tắt toàn bài có thể lưu cache dùng chung cho cả lớp (< 50ms, 0 token).
     */
    private const CACHEABLE_QUICK_PROMPTS = [
        'hãy tóm tắt các ý chính trong video bài giảng này kèm các mốc thời gian quan trọng (mm:ss).',
        'hãy tóm tắt trọng tâm tài liệu đính kèm của bài học này.',
    ];

    public function quickPromptCacheKey(
        Lesson $lesson,
        string $question,
        ?int $videoTimestampSeconds,
        ?array $imageAttachment
    ): ?string {
        if ($imageAttachment !== null || $videoTimestampSeconds !== null) {
            return null;
        }

        $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', $question) ?? ''));
        if (! in_array($normalized, self::CACHEABLE_QUICK_PROMPTS, true)) {
            return null;
        }

        return 'ai_tutor_quick:'.$lesson->id.':'.$this->computeLessonHash($lesson).':'.md5($normalized);
    }

    /**
     * Xây dựng payload chuẩn Interactions API v1 (tự chọn 2 model: model nhẹ siêu tốc khi đã đọc ngầm, model sâu khi đọc trực tiếp).
     *
     * @param  array{data: string, mime_type: string}|null  $imageAttachment
     * @return array{payload: array<string, mixed>, sources_used: array<int, array{type: string, title: string}>, used_fast_model: bool}
     */
    private function buildInteractionPayload(
        Course $course,
        Lesson $lesson,
        string $question,
        ?int $videoTimestampSeconds,
        ?string $previousInteractionId,
        ?array $imageAttachment,
        string $apiKey,
        string $baseUrl,
        int $timeout,
        bool $stream = false
    ): array {
        $deepModel = (string) config('services.gemini.model', 'gemini-4-hydrogen');
        $fastModel = (string) config('services.gemini.fast_model', 'gemini-3.5-flash-lite') ?: $deepModel;

        $systemInstruction = $this->buildSystemInstruction($course, $lesson);
        $sourcesUsed = [];
        $hasFresh = $this->hasFreshContext($lesson);

        // Dùng model siêu nhanh (gemini-3.5-flash-lite + thinking minimal) khi đã có bản đọc ngầm hoặc hỏi nối tiếp bằng chữ
        $useFastModel = ($hasFresh && $imageAttachment === null)
            || ($previousInteractionId !== null && $imageAttachment === null && $hasFresh);

        $selectedModel = $useFastModel ? $fastModel : $deepModel;
        $thinkingLevel = $useFastModel ? 'minimal' : 'low';

        $formattedTs = $videoTimestampSeconds !== null ? $this->formatSeconds($videoTimestampSeconds) : null;
        $userPrompt = $formattedTs
            ? "[Học viên đang xem video tại mốc {$formattedTs}] {$question}"
            : $question;

        $imageBlock = null;
        if ($imageAttachment && ! empty($imageAttachment['data']) && ! empty($imageAttachment['mime_type'])) {
            $imageBlock = [
                'type' => 'image',
                'data' => $imageAttachment['data'],
                'mime_type' => $imageAttachment['mime_type'],
            ];
        }

        if (! $previousInteractionId) {
            $contentParts = [];

            if ($hasFresh) {
                // Giữ bản ghi chú đọc ngầm của VIDEO để không phải xử lý lại file video nặng,
                // đồng thời LUÔN đính kèm trực tiếp toàn bộ FILE TÀI LIỆU (PDF/Word/PPT/TXT) để AI đọc chính xác từng câu, từng trang!
                $contentParts[] = [
                    'type' => 'text',
                    'text' => "=== GHI CHÚ VIDEO BÀI GIẢNG ĐÃ ĐỌC TRƯỚC (KÈM MỐC THỜI GIAN) ===\n".$lesson->ai_context_text,
                ];

                [$docMediaBlocks, $docTextContext, $sourcesUsed] = $this->collectLessonContext($lesson, $apiKey, $baseUrl, $timeout, false);
                foreach ($docMediaBlocks as $block) {
                    $contentParts[] = $block;
                }
                if ($docTextContext !== '') {
                    $contentParts[] = ['type' => 'text', 'text' => $docTextContext];
                }
            } else {
                [$mediaBlocks, $textContext, $sourcesUsed] = $this->collectLessonContext($lesson, $apiKey, $baseUrl, $timeout, true);
                foreach ($mediaBlocks as $block) {
                    $contentParts[] = $block;
                }
                if ($textContext !== '') {
                    $contentParts[] = ['type' => 'text', 'text' => $textContext];
                }
            }

            if ($imageBlock) {
                $contentParts[] = $imageBlock;
            }
            $contentParts[] = ['type' => 'text', 'text' => $userPrompt];

            $inputBlocks = [
                [
                    'type' => 'user_input',
                    'content' => $contentParts,
                ],
            ];
        } elseif ($imageBlock) {
            $inputBlocks = [
                [
                    'type' => 'user_input',
                    'content' => [
                        $imageBlock,
                        ['type' => 'text', 'text' => $userPrompt],
                    ],
                ],
            ];
        } else {
            $inputBlocks = $userPrompt;
        }

        $payload = [
            'model' => $selectedModel,
            'system_instruction' => $systemInstruction,
            'input' => $inputBlocks,
            'generation_config' => [
                'thinking_level' => $thinkingLevel,
                'max_output_tokens' => 4096,
            ],
        ];

        if ($stream) {
            $payload['stream'] = true;
        }

        if ($previousInteractionId) {
            $payload['previous_interaction_id'] = $previousInteractionId;
        }

        return [
            'payload' => $payload,
            'sources_used' => $sourcesUsed,
            'used_fast_model' => $useFastModel,
        ];
    }

    /**
     * Trả lời câu hỏi học viên (chế độ JSON chuẩn, có cache gợi ý nhanh + tách 2 model).
     *
     * @param  array{data: string, mime_type: string}|null  $imageAttachment
     * @return array{answer: string, interaction_id: ?string, sources_used: array<int, array{type: string, title: string}>, cached?: bool}
     */
    public function answerLessonQuestion(
        Course $course,
        Lesson $lesson,
        string $question,
        ?int $videoTimestampSeconds = null,
        ?string $previousInteractionId = null,
        ?array $imageAttachment = null
    ): array {
        $apiKey = (string) config('services.gemini.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('Trợ lý AI hiện chưa được kích hoạt. Vui lòng liên hệ quản trị viên.');
        }

        // Tối ưu #3: Trúng cache cho các nút gợi ý tóm tắt chung (< 50ms, 0 token)
        $cacheKey = $this->quickPromptCacheKey($lesson, $question, $videoTimestampSeconds, $imageAttachment);
        if ($cacheKey !== null) {
            $cachedAnswer = Cache::get($cacheKey);
            if (is_string($cachedAnswer) && $cachedAnswer !== '') {
                return [
                    'answer' => $cachedAnswer,
                    'interaction_id' => null,
                    'sources_used' => [],
                    'cached' => true,
                ];
            }
        }

        $baseUrl = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com'), '/');
        $timeout = (int) config('services.gemini.timeout', 120);

        $built = $this->buildInteractionPayload(
            $course,
            $lesson,
            $question,
            $videoTimestampSeconds,
            $previousInteractionId,
            $imageAttachment,
            $apiKey,
            $baseUrl,
            $timeout,
            false
        );

        $response = $this->postInteractionWithFallback($baseUrl, $apiKey, $built['payload'], $timeout, false);

        // Nếu previous_interaction_id đã hết hạn hoặc lệch model -> tự động thử lại 1 lượt mới kèm full context
        if ($previousInteractionId && $response->status() >= 400 && $response->status() < 500) {
            return $this->answerLessonQuestion($course, $lesson, $question, $videoTimestampSeconds, null, $imageAttachment);
        }

        if (! $response->successful()) {
            $errorMsg = $response->json('error.message') ?: ('Mã lỗi HTTP '.$response->status());
            Log::warning('Gemini API error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException("Không thể kết nối Trợ lý AI Gemini: {$errorMsg}");
        }

        $body = $response->json();
        $answer = $this->extractOutputText(is_array($body) ? $body : []);
        if ($answer === '') {
            throw new RuntimeException('Trợ lý AI không trả về nội dung câu trả lời. Vui lòng thử diễn đạt lại câu hỏi.');
        }

        if ($cacheKey !== null) {
            Cache::put($cacheKey, $answer, now()->addDays(7));
        }

        return [
            'answer' => $answer,
            'interaction_id' => is_string($body['id'] ?? null) ? $body['id'] : null,
            'sources_used' => $built['sources_used'],
            'cached' => false,
        ];
    }

    /**
     * Tối ưu #1: Trả lời câu hỏi theo luồng Streaming (SSE) từng chữ hiện ra ngay sau ~0.5-1 giây.
     *
     * @param  array{data: string, mime_type: string}|null  $imageAttachment
     * @param  callable(string): void  $onDelta
     * @return array{answer: string, interaction_id: ?string, sources_used: array<int, array{type: string, title: string}>, cached: bool}
     */
    public function streamLessonQuestion(
        Course $course,
        Lesson $lesson,
        string $question,
        ?int $videoTimestampSeconds,
        ?string $previousInteractionId,
        ?array $imageAttachment,
        callable $onDelta
    ): array {
        $apiKey = (string) config('services.gemini.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('Trợ lý AI hiện chưa được kích hoạt. Vui lòng liên hệ quản trị viên.');
        }

        // Tối ưu #3: Nếu trúng cache gợi ý nhanh -> đẩy ra tức thì trong < 50ms
        $cacheKey = $this->quickPromptCacheKey($lesson, $question, $videoTimestampSeconds, $imageAttachment);
        if ($cacheKey !== null) {
            $cachedAnswer = Cache::get($cacheKey);
            if (is_string($cachedAnswer) && $cachedAnswer !== '') {
                $onDelta($cachedAnswer);

                return [
                    'answer' => $cachedAnswer,
                    'interaction_id' => null,
                    'sources_used' => [],
                    'cached' => true,
                ];
            }
        }

        $baseUrl = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com'), '/');
        $timeout = (int) config('services.gemini.timeout', 120);

        $built = $this->buildInteractionPayload(
            $course,
            $lesson,
            $question,
            $videoTimestampSeconds,
            $previousInteractionId,
            $imageAttachment,
            $apiKey,
            $baseUrl,
            $timeout,
            true
        );

        $preferred = (string) ($built['payload']['model'] ?? config('services.gemini.model', 'gemini-3.5-flash-lite'));
        $chain = $this->buildModelChain($preferred);
        $lastError = 'Không thể kết nối Trợ lý AI Gemini.';

        foreach ($chain as $idx => $candidateModel) {
            $isFirst = ($idx === 0);
            $hasNext = ($idx < count($chain) - 1);
            $attemptTimeout = $hasNext ? min($timeout, 40) : $timeout;

            $attemptPayload = $built['payload'];
            $attemptPayload['model'] = $candidateModel;
            if (! $isFirst) {
                unset($attemptPayload['previous_interaction_id']);
                if (isset($attemptPayload['generation_config']) && is_array($attemptPayload['generation_config'])) {
                    $attemptPayload['generation_config']['thinking_level'] = $this->thinkingLevelForModel($candidateModel, true);
                }
            }

            try {
                $response = $this->geminiHttp($apiKey, $attemptTimeout, true)
                    ->post("{$baseUrl}/v1/interactions", $attemptPayload);
            } catch (ConnectionException $e) {
                $lastError = 'Kết nối tới máy chủ Gemini bị chậm hoặc quá thời gian chờ.';
                if ($hasNext) {
                    continue;
                }
                throw new RuntimeException($lastError);
            }

            if ($previousInteractionId && $isFirst && $response->status() >= 400 && $response->status() < 500) {
                return $this->streamLessonQuestion($course, $lesson, $question, $videoTimestampSeconds, null, $imageAttachment, $onDelta);
            }

            if (! $response->successful()) {
                $rawError = $response->body();
                $decoded = json_decode($rawError, true);
                $errorMsg = (is_array($decoded) ? ($decoded['error']['message'] ?? null) : null) ?: ('Mã lỗi HTTP '.$response->status());
                $lastError = "Không thể kết nối Trợ lý AI Gemini: {$errorMsg}";

                if ($hasNext && in_array($response->status(), [429, 500, 502, 503, 504], true)) {
                    Log::warning('Gemini streaming HTTP error, switching to fallback', [
                        'failed_model' => $candidateModel,
                        'next_model' => $chain[$idx + 1],
                        'status' => $response->status(),
                    ]);

                    continue;
                }

                Log::warning('Gemini Streaming API error', ['status' => $response->status(), 'body' => mb_substr($rawError, 0, 500)]);
                throw new RuntimeException($lastError);
            }

            $streamBody = $response->toPsrResponse()->getBody();
            $buffer = '';
            $fullAnswer = '';
            $interactionId = null;
            $streamRetryableError = null;

            while (! $streamBody->eof()) {
                $chunk = $streamBody->read(1024);
                if ($chunk === '') {
                    continue;
                }
                $buffer .= $chunk;

                while (($newlinePos = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $newlinePos));
                    $buffer = substr($buffer, $newlinePos + 1);

                    if ($line === '' || ! str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $jsonStr = trim(substr($line, 5));
                    if ($jsonStr === '' || $jsonStr === '[DONE]') {
                        continue;
                    }

                    $event = json_decode($jsonStr, true);
                    if (! is_array($event)) {
                        continue;
                    }

                    $eventType = (string) ($event['event_type'] ?? '');

                    if ($eventType === 'interaction.created' || $eventType === 'interaction.completed') {
                        $id = $event['interaction']['id'] ?? null;
                        if (is_string($id) && $id !== '') {
                            $interactionId = $id;
                        }
                    } elseif ($eventType === 'step.delta') {
                        $delta = $event['delta'] ?? [];
                        if (($delta['type'] ?? null) === 'text' && is_string($delta['text'] ?? null) && $delta['text'] !== '') {
                            $fullAnswer .= $delta['text'];
                            $onDelta($delta['text']);
                        }
                    } elseif ($eventType === 'error') {
                        $errCode = (string) ($event['error']['code'] ?? '');
                        $msg = (string) ($event['error']['message'] ?? 'Lỗi trong quá trình truyền phản hồi từ Gemini.');
                        if ($fullAnswer === '' && $hasNext && in_array($errCode, ['service_unavailable', 'resource_exhausted', 'internal', 'deadline_exceeded'], true)) {
                            $streamRetryableError = $msg;
                            break 2;
                        }
                        throw new RuntimeException($msg);
                    }
                }
            }

            if ($streamRetryableError !== null) {
                $lastError = $streamRetryableError;
                Log::warning('Gemini SSE in-stream unavailable, switching to fallback', [
                    'failed_model' => $candidateModel,
                    'next_model' => $chain[$idx + 1],
                ]);

                continue;
            }

            $fullAnswer = trim($fullAnswer);
            if ($fullAnswer === '') {
                if ($hasNext) {
                    continue;
                }
                throw new RuntimeException('Trợ lý AI không trả về nội dung câu trả lời. Vui lòng thử diễn đạt lại câu hỏi.');
            }

            if ($cacheKey !== null) {
                Cache::put($cacheKey, $fullAnswer, now()->addDays(7));
            }

            return [
                'answer' => $fullAnswer,
                'interaction_id' => $interactionId,
                'sources_used' => $built['sources_used'],
                'cached' => false,
            ];
        }

        throw new RuntimeException($lastError);
    }

    /**
     * Thu thập toàn bộ học liệu đã duyệt của bài học (Video YouTube/MP4, PDF, Word/PPT/TXT).
     *
     * @return array{0: array<int, array<string, mixed>>, 1: string, 2: array<int, array{type: string, title: string}>}
     */
    private function collectLessonContext(
        Lesson $lesson,
        string $apiKey,
        string $baseUrl,
        int $timeout,
        bool $includeVideo = true
    ): array {
        /** @var Collection<int, Document> $documents */
        $documents = $lesson->relationLoaded('documents')
            ? $lesson->documents->filter(fn (Document $d) => $d->isApproved())
            : $lesson->documents()->approved()->get();

        $mediaBlocks = [];
        $textParts = [];
        $sourcesUsed = [];
        $seenPdfFingerprints = [];
        $pdfCount = 0;

        foreach ($documents as $doc) {
            // 1. Video bài giảng
            if ($doc->isVideo()) {
                if (! $includeVideo) {
                    continue;
                }
                if ($doc->isYouTube() && ($watchUrl = $doc->youtubeWatchUrl())) {
                    $mediaBlocks[] = [
                        'type' => 'video',
                        'uri' => $watchUrl,
                    ];
                    $sourcesUsed[] = ['type' => 'video_youtube', 'title' => $doc->title];
                } elseif ($doc->file_path && Storage::exists($doc->file_path)) {
                    $uploaded = $this->ensureGeminiFile($doc, $doc->file_path, $doc->mime_type ?: 'video/mp4', $apiKey, $baseUrl, $timeout);
                    if ($uploaded) {
                        $mediaBlocks[] = [
                            'type' => 'video',
                            'uri' => $uploaded['uri'],
                            'mime_type' => $doc->mime_type ?: 'video/mp4',
                        ];
                        $sourcesUsed[] = ['type' => 'video_file', 'title' => $doc->title];
                    }
                }

                continue;
            }

            // 2. Tài liệu PDF (tối đa 3 PDF không trùng lặp <= 25MB, cache URI 40 giờ trên Gemini Files API)
            if ($doc->isPdf() && $doc->file_path && Storage::exists($doc->file_path) && $pdfCount < 3) {
                $size = Storage::size($doc->file_path);
                $fingerprint = mb_strtolower(trim((string) ($doc->original_name ?: $doc->title))).'|'.$size;
                if (isset($seenPdfFingerprints[$fingerprint])) {
                    continue;
                }
                if ($size > 0 && $size <= 25 * 1024 * 1024) {
                    $uploaded = $this->ensureGeminiFile($doc, $doc->file_path, 'application/pdf', $apiKey, $baseUrl, $timeout);
                    if ($uploaded) {
                        $mediaBlocks[] = [
                            'type' => 'document',
                            'uri' => $uploaded['uri'],
                            'mime_type' => 'application/pdf',
                        ];
                        $sourcesUsed[] = ['type' => 'pdf', 'title' => $doc->title];
                        $seenPdfFingerprints[$fingerprint] = true;
                        $pdfCount++;
                    }
                }

                continue;
            }

            // 3. Tài liệu Word (.docx / .doc)
            if ($doc->isWord() && $doc->file_path && Storage::exists($doc->file_path)) {
                $html = $this->conversionService->extractWordContent($doc->file_path);
                $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $html)) ?? '');
                if ($plain !== '' && ! str_contains($plain, 'Không tìm thấy nội dung văn bản')) {
                    $textParts[] = "=== TÀI LIỆU WORD: {$doc->title} ===\n".mb_substr($plain, 0, 15000);
                    $sourcesUsed[] = ['type' => 'word', 'title' => $doc->title];
                } elseif ($pdfCount < 3 && ($pdfFallback = $doc->converted_pdf_path ?: $doc->pdf_path) && Storage::exists($pdfFallback)) {
                    $uploaded = $this->ensureGeminiFile($doc, $pdfFallback, 'application/pdf', $apiKey, $baseUrl, $timeout);
                    if ($uploaded) {
                        $mediaBlocks[] = [
                            'type' => 'document',
                            'uri' => $uploaded['uri'],
                            'mime_type' => 'application/pdf',
                        ];
                        $sourcesUsed[] = ['type' => 'word_pdf', 'title' => $doc->title];
                        $pdfCount++;
                    }
                }

                continue;
            }

            // 4. Tài liệu PowerPoint (.pptx / .ppt)
            if ($doc->isPowerPoint() && $doc->file_path && Storage::exists($doc->file_path)) {
                $slides = $this->conversionService->extractPptContent($doc->file_path);
                if (! empty($slides)) {
                    $slideText = collect($slides)->map(function (array $slide): string {
                        return 'Slide '.$slide['slide_number'].': '.implode(' ', $slide['content']);
                    })->implode("\n");
                    $textParts[] = "=== SLIDE POWERPOINT: {$doc->title} ===\n".mb_substr($slideText, 0, 15000);
                    $sourcesUsed[] = ['type' => 'ppt', 'title' => $doc->title];
                } elseif ($pdfCount < 3 && ($pdfFallback = $doc->converted_pdf_path ?: $doc->pdf_path) && Storage::exists($pdfFallback)) {
                    $uploaded = $this->ensureGeminiFile($doc, $pdfFallback, 'application/pdf', $apiKey, $baseUrl, $timeout);
                    if ($uploaded) {
                        $mediaBlocks[] = [
                            'type' => 'document',
                            'uri' => $uploaded['uri'],
                            'mime_type' => 'application/pdf',
                        ];
                        $sourcesUsed[] = ['type' => 'ppt_pdf', 'title' => $doc->title];
                        $pdfCount++;
                    }
                }

                continue;
            }

            // 5. Tệp văn bản thuần (.txt / .md / .csv)
            $ext = strtolower(pathinfo($doc->original_name ?? '', PATHINFO_EXTENSION));
            if ($doc->file_path && Storage::exists($doc->file_path) && in_array($ext, ['txt', 'md', 'csv'], true)) {
                $txt = trim((string) Storage::get($doc->file_path));
                if ($txt !== '') {
                    $textParts[] = "=== TÀI LIỆU VĂN BẢN: {$doc->title} ===\n".mb_substr($txt, 0, 12000);
                    $sourcesUsed[] = ['type' => 'text', 'title' => $doc->title];
                }
            }
        }

        $textContext = '';
        if (! empty($textParts)) {
            $textContext = "Dưới đây là nội dung các tài liệu đính kèm của bài học:\n\n".implode("\n\n", $textParts);
        }

        return [$mediaBlocks, $textContext, $sourcesUsed];
    }

    /**
     * Tải tệp lên Gemini Files API (Resumable Upload) và cache URI 40 giờ trong database.
     *
     * @return array{uri: string, name: string}|null
     */
    private function ensureGeminiFile(
        Document $doc,
        string $storagePath,
        string $mimeType,
        string $apiKey,
        string $baseUrl,
        int $timeout
    ): ?array {
        if ($doc->gemini_file_uri && $doc->gemini_file_expires_at && $doc->gemini_file_expires_at->isFuture()) {
            return [
                'uri' => $doc->gemini_file_uri,
                'name' => (string) $doc->gemini_file_name,
            ];
        }

        $fullPath = Storage::path($storagePath);
        if (! is_file($fullPath)) {
            return null;
        }

        $numBytes = filesize($fullPath);
        if (! $numBytes || $numBytes <= 0) {
            return null;
        }

        // Bước 1: Khởi tạo phiên tải lên resumable
        $startResp = Http::timeout($timeout)
            ->withHeaders([
                'x-goog-api-key' => $apiKey,
                'X-Goog-Upload-Protocol' => 'resumable',
                'X-Goog-Upload-Command' => 'start',
                'X-Goog-Upload-Header-Content-Length' => (string) $numBytes,
                'X-Goog-Upload-Header-Content-Type' => $mimeType,
                'Content-Type' => 'application/json',
            ])
            ->post("{$baseUrl}/upload/v1beta/files", [
                'file' => [
                    'display_name' => mb_substr($doc->title ?: 'lesson_file', 0, 120),
                ],
            ]);

        $uploadUrl = $startResp->header('X-Goog-Upload-URL') ?: $startResp->header('x-goog-upload-url');
        if (! $startResp->successful() || ! $uploadUrl) {
            Log::warning('Gemini Files API start upload failed', ['doc_id' => $doc->id, 'status' => $startResp->status()]);

            return null;
        }

        // Bước 2: Gửi dữ liệu nhị phân của tệp
        $stream = fopen($fullPath, 'rb');
        if ($stream === false) {
            return null;
        }

        try {
            $uploadResp = Http::timeout(max($timeout, 180))
                ->withHeaders([
                    'Content-Length' => (string) $numBytes,
                    'X-Goog-Upload-Offset' => '0',
                    'X-Goog-Upload-Command' => 'upload, finalize',
                ])
                ->withBody($stream, $mimeType)
                ->post($uploadUrl);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $uploadResp->successful()) {
            Log::warning('Gemini Files API binary upload failed', ['doc_id' => $doc->id, 'status' => $uploadResp->status()]);

            return null;
        }

        $fileData = $uploadResp->json('file') ?? $uploadResp->json();
        $fileUri = is_array($fileData) ? ($fileData['uri'] ?? null) : null;
        $fileName = is_array($fileData) ? ($fileData['name'] ?? null) : null;
        $state = is_array($fileData) ? ($fileData['state'] ?? 'ACTIVE') : 'PROCESSING';

        if (! $fileUri || ! $fileName) {
            return null;
        }

        // Bước 3: Với video lớn, chờ Gemini xử lý sang trạng thái ACTIVE
        $attempts = 0;
        while ($state === 'PROCESSING' && $attempts < 24) {
            sleep(3);
            $attempts++;
            $statusResp = Http::timeout(30)
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->get("{$baseUrl}/v1beta/{$fileName}");

            if (! $statusResp->successful()) {
                break;
            }
            $state = (string) ($statusResp->json('state') ?? 'ACTIVE');
            if ($state === 'FAILED') {
                return null;
            }
        }

        if ($state !== 'ACTIVE') {
            return null;
        }

        // Lưu cache 40 giờ (Gemini Files API giữ tệp 48 giờ)
        $doc->forceFill([
            'gemini_file_uri' => $fileUri,
            'gemini_file_name' => $fileName,
            'gemini_file_expires_at' => now()->addHours(40),
        ])->save();

        return [
            'uri' => $fileUri,
            'name' => $fileName,
        ];
    }

    private function buildSystemInstruction(Course $course, Lesson $lesson): string
    {
        $subjectName = $course->subject?->name ?? 'Tổng quát';
        $chapterTitle = $lesson->chapter?->title ?? '';

        return <<<PROMPT
Bạn là Trợ lý AI Gia sư thông minh của nền tảng học trực tuyến LearnMate.
Nhiệm vụ của bạn là hỗ trợ học viên hiểu sâu nội dung của bài học hiện tại dựa trên VIDEO bài giảng và TÀI LIỆU đính kèm được cung cấp.

Thông tin bài học:
- Môn học: {$subjectName}
- Khóa học: {$course->title}
- Chương: {$chapterTitle}
- Bài học: {$lesson->title}

Quy tắc trả lời:
1. Trả lời bằng tiếng Việt rõ ràng, thân thiện, dễ hiểu, có cấu trúc gạch đầu dòng hoặc từng bước giải chi tiết.
2. Luôn ưu tiên bám sát nội dung thực tế trong video bài giảng và tài liệu đính kèm của bài học.
3. Khi nhắc tới một đoạn cụ thể trong video, hãy ghi rõ mốc thời gian theo định dạng mm:ss (ví dụ: 01:25, 04:10) để học viên có thể bấm tua ngay tới đúng vị trí đó.
4. Nếu học viên hỏi về một mốc thời gian cụ thể (ví dụ 01:20), hãy tập trung giải thích chính xác nội dung, công thức, hoặc lời giảng xoay quanh mốc đó.
5. Nếu nội dung không có trong video hay tài liệu bài học, hãy nói rõ phần nào không có trong bài giảng trước khi bổ sung kiến thức nền giúp học viên hiểu bài.
6. Nếu học viên gửi kèm hình ảnh (đề bài, công thức, ảnh chụp màn hình hoặc bài làm), hãy đọc kỹ nội dung trong ảnh và giải thích chi tiết từng bước dễ hiểu.
7. Khi viết công thức toán học, vật lý hoặc hóa học, hãy dùng đúng cú pháp LaTeX chuẩn: đặt công thức trong dòng giữa cặp `$ ... $` và công thức nổi bật riêng dòng giữa cặp `$$ ... $$`.
8. Khi học viên hỏi về tài liệu/file đính kèm (ví dụ đọc đề, tóm tắt file, giải Câu 1, Câu 5...), hãy đọc trực tiếp nội dung từ các tệp tài liệu được đính kèm trong tin nhắn và trích dẫn đúng đề bài, số câu, phương án trước khi giải chi tiết.
PROMPT;
    }

    /**
     * Trích xuất văn bản trả lời từ phản hồi JSON của Gemini Interactions API.
     *
     * @param  array<string, mixed>  $body
     */
    private function extractOutputText(array $body): string
    {
        if (is_string($body['output_text'] ?? null) && trim($body['output_text']) !== '') {
            return trim($body['output_text']);
        }

        $chunks = [];

        // Interactions API v1 steps schema
        foreach (($body['steps'] ?? []) as $step) {
            if (($step['type'] ?? null) === 'model_output' && is_array($step['content'] ?? null)) {
                foreach ($step['content'] as $part) {
                    if (($part['type'] ?? null) === 'text' && is_string($part['text'] ?? null)) {
                        $chunks[] = $part['text'];
                    }
                }
            }
        }

        // Fallback cho trường hợp outputs array
        if (empty($chunks) && is_array($body['outputs'] ?? null)) {
            foreach ($body['outputs'] as $output) {
                if (($output['type'] ?? null) === 'text' && is_string($output['text'] ?? null)) {
                    $chunks[] = $output['text'];
                }
            }
        }

        return trim(implode("\n", $chunks));
    }

    private function formatSeconds(int $seconds): string
    {
        $secs = max(0, $seconds);

        return sprintf('%02d:%02d', intdiv($secs, 60), $secs % 60);
    }
}
