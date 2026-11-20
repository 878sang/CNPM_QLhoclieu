@extends(auth()->user()->hasRole('admin') ? 'layouts.admin' : 'layouts.teacher')

@section('content')
<x-page-header
    eyebrow="TƯƠNG TÁC TRÊN VIDEO"
    title="Quản lý tương tác video"
    description="Chọn video bài giảng để thêm/sửa câu hỏi trắc nghiệm, đúng/sai tại các mốc thời gian và theo dõi kết quả trả lời của học viên."
/>

@if (session('status'))
    <x-alert type="success" class="mb-3">{{ session('status') }}</x-alert>
@endif

<!-- THỐNG KÊ TƯƠNG TÁC VIDEO -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border rounded-3 p-3 bg-white shadow-2xs h-100">
            <div class="small text-muted">Video đã gắn câu hỏi</div>
            <div class="fs-3 fw-bold text-primary">{{ $stats['videos_with_questions'] }} <span class="fs-6 fw-normal text-muted">/ {{ $stats['total_videos'] }} video</span></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border rounded-3 p-3 bg-white shadow-2xs h-100">
            <div class="small text-muted">Tổng câu hỏi trên video</div>
            <div class="fs-3 fw-bold text-danger">{{ $stats['total_questions'] }}</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border rounded-3 p-3 bg-white shadow-2xs h-100">
            <div class="small text-muted">Tổng lượt học viên trả lời</div>
            <div class="fs-3 fw-bold text-dark">{{ $stats['total_responses'] }}</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border rounded-3 p-3 bg-white shadow-2xs h-100">
            <div class="small text-muted">Tỷ lệ trả lời đúng</div>
            <div class="fs-3 fw-bold text-success">{{ $stats['correct_rate'] }}%</div>
        </div>
    </div>
</div>

<!-- BỘ LỌC -->
<x-card class="p-3 mb-4">
    <form method="GET" action="{{ route('video-interactions.index') }}" class="row g-2 align-items-center">
        <div class="col-md-4">
            <select name="course_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- Tất cả khóa học --</option>
                @foreach($courses as $c)
                    <option value="{{ $c->id }}" @selected(request('course_id') == $c->id)>{{ $c->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="question_type" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- Tất cả loại câu hỏi --</option>
                @foreach(\App\Models\VideoInteraction::TYPES as $typeKey => $typeLabel)
                    <option value="{{ $typeKey }}" @selected(request('question_type') === $typeKey)>{{ $typeLabel }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Tìm theo tên video hoặc câu hỏi...">
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search me-1"></i>Lọc</button>
            @if(request()->hasAny(['course_id', 'question_type', 'q']))
                <a href="{{ route('video-interactions.index') }}" class="btn btn-sm btn-outline-secondary">Xóa</a>
            @endif
        </div>
    </form>
</x-card>

<!-- PHẦN 1: DANH SÁCH VIDEO BÀI GIẢNG ĐỂ CẤU HÌNH NHANH -->
<x-card class="p-3 p-md-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold text-uppercase mb-0">
            <i class="bi bi-camera-video-fill text-danger me-1"></i>Danh sách video bài giảng ({{ $videoDocuments->count() }})
        </h2>
        <span class="small text-muted">Chọn video để thêm hoặc chỉnh sửa câu hỏi tương tác</span>
    </div>

    @if($videoDocuments->isNotEmpty())
        <div class="row g-3">
            @foreach($videoDocuments as $vdoc)
                <div class="col-md-6 col-xl-4">
                    <div class="border rounded-3 p-3 bg-light h-100 d-flex flex-column justify-content-between gap-2">
                        <div>
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                <span class="fw-semibold text-dark text-truncate" title="{{ $vdoc->title }}">
                                    <i class="bi bi-play-circle-fill text-danger me-1"></i>{{ $vdoc->title }}
                                </span>
                                <span class="badge {{ $vdoc->video_interactions_count > 0 ? 'bg-primary' : 'bg-secondary' }} flex-shrink-0">
                                    {{ $vdoc->video_interactions_count }} câu hỏi
                                </span>
                            </div>
                            <div class="small text-muted">
                                {{ $vdoc->course?->title }}
                                @if($vdoc->lesson) · {{ $vdoc->lesson->title }} @endif
                            </div>
                        </div>
                        @if($vdoc->course)
                            <div class="d-flex justify-content-end pt-2 border-top">
                                <a href="{{ route('courses.video-interactions.manage', [$vdoc->course, $vdoc]) }}" class="btn btn-sm {{ $vdoc->video_interactions_count > 0 ? 'btn-outline-primary' : 'btn-primary' }}">
                                    <i class="bi {{ $vdoc->video_interactions_count > 0 ? 'bi-sliders' : 'bi-plus-circle' }} me-1"></i>
                                    {{ $vdoc->video_interactions_count > 0 ? 'Quản lý câu hỏi' : 'Thêm câu hỏi tương tác' }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <x-empty-state
            icon="camera-video-off"
            title="Chưa có video bài giảng nào"
            message="Hãy tải lên video bài giảng trong khóa học trước, sau đó bạn có thể gắn câu hỏi tương tác trực tiếp tại đây."
        />
    @endif
</x-card>

<!-- PHẦN 2: BẢNG TỔNG HỢP CÁC CÂU HỎI TƯƠNG TÁC TRÊN VIDEO -->
<x-card class="p-3 p-md-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold text-uppercase mb-0">
            <i class="bi bi-patch-question-fill text-primary me-1"></i>Tất cả câu hỏi tương tác trên video ({{ $videoInteractions->total() }})
        </h2>
    </div>

    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Mốc</th>
                    <th>Câu hỏi & Loại</th>
                    <th>Khóa học / Video</th>
                    <th class="text-center">Lượt trả lời</th>
                    <th class="text-center">Tỷ lệ đúng</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse($videoInteractions as $vi)
                    @php
                        $rate = $vi->total_responses_count > 0
                            ? (int) round($vi->correct_responses_count * 100 / $vi->total_responses_count)
                            : null;
                    @endphp
                    <tr>
                        <td><span class="badge bg-dark font-monospace">{{ $vi->formattedTimestamp() }}</span></td>
                        <td>
                            <div class="fw-semibold text-dark">{{ Str::limit($vi->question, 80) }}</div>
                            <span class="badge {{ $vi->typeBadgeClass() }} mt-1">{{ $vi->typeLabel() }}</span>
                        </td>
                        <td>
                            <div class="small fw-semibold">{{ $vi->course?->title }}</div>
                            <div class="small text-muted">{{ $vi->document?->title }} @if($vi->lesson) · {{ $vi->lesson->title }} @endif</div>
                        </td>
                        <td class="text-center fw-semibold">{{ $vi->total_responses_count }}</td>
                        <td class="text-center">
                            @if($rate !== null)
                                <span class="badge {{ $rate >= 70 ? 'bg-success' : ($rate >= 40 ? 'bg-warning text-dark' : 'bg-danger') }}">{{ $rate }}%</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if($vi->course && $vi->document)
                                <a href="{{ route('courses.video-interactions.manage', [$vi->course, $vi->document]) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil-square me-1"></i>Chỉnh sửa
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-empty-state
                                icon="play-btn"
                                title="Chưa có câu hỏi tương tác nào"
                                message="Chọn một video ở khung phía trên và bấm 'Thêm câu hỏi tương tác' để bắt đầu tạo câu hỏi trắc nghiệm, đúng/sai."
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $videoInteractions->links('pagination::bootstrap-5') }}</div>
</x-card>
@endsection
