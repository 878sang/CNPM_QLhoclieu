<?php

namespace App\Http\Requests;

use App\Models\Chapter;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user && $user->can('update', $this->route('course'));
    }

    protected function prepareForValidation(): void
    {
        $title = trim((string) $this->input('title'));
        if ($title === '' && $this->hasFile('file')) {
            $orig = $this->file('file')->getClientOriginalName();
            $title = pathinfo($orig, PATHINFO_FILENAME) ?: $orig;
        }

        $this->merge([
            'title' => $title !== '' ? $title : ($this->filled('url') ? $this->input('url') : 'Tài liệu'),
            'type' => $this->input('type', 'file'),
            'allow_view' => $this->boolean('allow_view'),
            'allow_download' => $this->boolean('allow_download'),
            'lesson_id' => $this->input('lesson_id') ?: null,
            'url' => $this->filled('url') ? trim((string) $this->input('url')) : null,
        ]);
    }

    public function rules(): array
    {
        $course = $this->route('course');
        $document = $this->route('document');
        $type = $this->input('type', 'file');

        $rules = [
            'type' => ['required', 'in:file,video,link'],
            'title' => ['nullable', 'string', 'max:255'],
            'lesson_id' => [
                'nullable',
                Rule::exists('lessons', 'id')->whereIn(
                    'chapter_id',
                    Chapter::where('course_id', $course->id)->select('id')
                ),
            ],
            'allow_view' => ['boolean'],
            'allow_download' => ['boolean'],
            'redirect_to' => ['nullable', 'string'],
        ];

        if ($type === 'link') {
            $rules['url'] = ['required', 'url', 'max:2048'];
        } elseif ($type === 'video') {
            if ($this->hasFile('file')) {
                $rules['file'] = ['file', 'mimes:mp4,webm,ogg,mkv,mov', 'max:512000']; // 500MB
            } elseif (! $document && ! $this->filled('url')) {
                $rules['url'] = ['required_without:file', 'url', 'max:2048'];
            } elseif ($this->filled('url')) {
                $rules['url'] = ['url', 'max:2048'];
            }
        } else {
            // type == 'file'
            $fileRule = $document ? ['nullable', 'file'] : ['required', 'file'];
            $fileRule[] = 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt';
            $fileRule[] = 'max:20480'; // 20MB
            $rules['file'] = $fileRule;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tên tài liệu.',
            'title.max' => 'Tên tài liệu không được vượt quá 255 ký tự.',
            'type.required' => 'Vui lòng chọn loại tài liệu.',
            'type.in' => 'Loại tài liệu không hợp lệ.',
            'file.required' => 'Vui lòng chọn tệp tài liệu.',
            'file.mimes' => 'Định dạng tệp không được hỗ trợ.',
            'file.max' => 'Dung lượng tệp vượt quá giới hạn cho phép.',
            'file.uploaded' => 'Tải tệp lên thất bại. Vui lòng kiểm tra dung lượng và thử lại.',
            'url.required' => 'Vui lòng nhập liên kết (URL).',
            'url.required_without' => 'Vui lòng tải lên tệp video hoặc dán link video.',
            'url.url' => 'Địa chỉ liên kết (URL) không hợp lệ.',
            'lesson_id.exists' => 'Bài học không thuộc khóa học này.',
        ];
    }
}
