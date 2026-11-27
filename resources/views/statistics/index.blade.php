@extends($layout)

@section('content')
@php
    $indexRoute = $isAdmin ? 'admin.statistics' : 'teacher.statistics';
    $exportRoute = $isAdmin ? 'admin.statistics.export' : 'teacher.statistics.export';
    $queryParams = array_filter([
        'course_id' => $selectedCourseId,
        'teacher_id' => request('teacher_id'),
        'period' => $period !== 'custom' ? $period : null,
        'from' => $period === 'custom' ? $fromDate : null,
        'to' => $period === 'custom' ? $toDate : null,
    ], fn ($v) => $v !== null && $v !== '');
@endphp

<x-page-header
    :eyebrow="$isAdmin ? 'QUẢN TRỊ / THỐNG KÊ & BÁO CÁO' : 'GIẢNG VIÊN / THỐNG KÊ & BÁO CÁO'"
    title="Thống kê học liệu & Tiến độ học tập"
    description="Theo dõi lượt xem, lượt tải từ MaterialLogs, tiến độ hoàn thành của học viên và xuất báo cáo Excel chi tiết."
>
    <x-slot:actions>
        <a href="{{ route($exportRoute, $queryParams) }}" class="btn btn-success fw-semibold shadow-sm">
            <i class="bi bi-file-earmark-excel-fill me-1"></i>Xuất báo cáo Excel (.xlsx)
        </a>
    </x-slot:actions>
</x-page-header>

<!-- THANH BỘ LỌC THỐNG KÊ -->
<x-card class="p-3 p-md-4 mb-4">
    <form method="GET" action="{{ route($indexRoute) }}" class="row g-3 align-items-end">
        <div class="col-12 {{ $isAdmin ? 'col-lg-3' : 'col-lg-4' }}">
            <label for="stat_course_id" class="form-label small fw-semibold text-muted mb-1">Khóa học</label>
            <select name="course_id" id="stat_course_id" class="form-select">
                <option value="">Tất cả khóa học ({{ $courses->count() }})</option>
                @foreach($courses as $c)
                    <option value="{{ $c->id }}" @selected($selectedCourseId === $c->id)>
                        {{ $c->title }} {{ $c->subject ? '— '.$c->subject->name : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        @if($isAdmin)
        <div class="col-12 col-lg-2">
            <label for="stat_teacher_id" class="form-label small fw-semibold text-muted mb-1">Giảng viên</label>
            <select name="teacher_id" id="stat_teacher_id" class="form-select">
                <option value="">Tất cả giảng viên</option>
                @foreach($teachers as $t)
                    <option value="{{ $t->id }}" @selected((int) request('teacher_id') === $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        <div class="col-12 col-md-4 {{ $isAdmin ? 'col-lg-2' : 'col-lg-3' }}">
            <label for="stat_period" class="form-label small fw-semibold text-muted mb-1">Khoảng thời gian</label>
            <select name="period" id="stat_period" class="form-select">
                <option value="7d" @selected($period === '7d')>7 ngày gần nhất</option>
                <option value="30d" @selected($period === '30d')>30 ngày gần nhất</option>
                <option value="90d" @selected($period === '90d')>90 ngày gần nhất</option>
                <option value="all" @selected($period === 'all')>Toàn thời gian</option>
                @if($period === 'custom')
                    <option value="custom" selected>Tùy chọn ngày</option>
                @endif
            </select>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <label for="stat_from" class="form-label small fw-semibold text-muted mb-1">Từ ngày</label>
            <input type="date" name="from" id="stat_from" value="{{ request('from') }}" class="form-control">
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <label for="stat_to" class="form-label small fw-semibold text-muted mb-1">Đến ngày</label>
            <input type="date" name="to" id="stat_to" value="{{ request('to') }}" class="form-control">
        </div>

        <div class="col-12 {{ $isAdmin ? 'col-lg-1' : 'col-lg-1' }} d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100" title="Áp dụng bộ lọc">
                <i class="bi bi-funnel-fill"></i>
            </button>
            <a href="{{ route($indexRoute) }}" class="btn btn-outline-secondary" title="Đặt lại bộ lọc">
                <i class="bi bi-arrow-counterclockwise"></i>
            </a>
        </div>
    </form>
</x-card>

<!-- 4 THẺ CHỈ SỐ TỔNG HỢP (KPI CARDS) -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <x-card class="p-3 h-100 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small fw-semibold text-uppercase">Tổng lượt xem</span>
                <span class="badge bg-primary-subtle text-primary rounded-pill p-2"><i class="bi bi-eye-fill fs-6"></i></span>
            </div>
            <div class="display-6 fw-bold font-mono mt-2 text-dark">{{ number_format($totalViews) }}</div>
            <div class="small text-muted mt-1">
                Kỳ: <span class="fw-semibold">{{ $periodLabel }}</span> · {{ $totalDocuments }} học liệu
            </div>
        </x-card>
    </div>

    <div class="col-sm-6 col-xl-3">
        <x-card class="p-3 h-100 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small fw-semibold text-uppercase">Tổng lượt tải về</span>
                <span class="badge bg-success-subtle text-success rounded-pill p-2"><i class="bi bi-cloud-arrow-down-fill fs-6"></i></span>
            </div>
            <div class="display-6 fw-bold font-mono mt-2 text-dark">{{ number_format($totalDownloads) }}</div>
            <div class="small text-muted mt-1">
                Tổng tương tác tệp: <span class="fw-semibold">{{ number_format($totalViews + $totalDownloads) }}</span> lượt
            </div>
        </x-card>
    </div>

    <div class="col-sm-6 col-xl-3">
        <x-card class="p-3 h-100 border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small fw-semibold text-uppercase">Tiến độ trung bình</span>
                <span class="badge bg-info-subtle text-info-emphasis rounded-pill p-2"><i class="bi bi-graph-up-arrow fs-6"></i></span>
            </div>
            <div class="display-6 fw-bold font-mono mt-2 text-primary">{{ $avgProgress }}%</div>
            <div class="progress mt-2" style="height: 6px;">
                <div class="progress-bar bg-primary" style="width: {{ $avgProgress }}%"></div>
            </div>
            <div class="small text-muted mt-1">{{ $totalEnrollments }} lượt ghi danh đang học</div>
        </x-card>
    </div>

    <div class="col-sm-6 col-xl-3">
        <x-card class="p-3 h-100 border-start border-4 border-warning">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small fw-semibold text-uppercase">Hoàn thành & Điểm TB</span>
                <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill p-2"><i class="bi bi-award-fill fs-6"></i></span>
            </div>
            <div class="display-6 fw-bold font-mono mt-2 text-dark">{{ number_format($completedLessonsCount) }}</div>
            <div class="small text-muted mt-1">
                lượt bài hoàn thành · Điểm BT:
                <span class="fw-bold text-dark">{{ $avgAssignmentScore !== null ? $avgAssignmentScore.'/10' : '—' }}</span>
            </div>
        </x-card>
    </div>
</div>

<!-- KHU VỰC BIỂU ĐỒ TRỰC QUAN (TS5-02) -->
<div class="row g-4 mb-4">
    <!-- Biểu đồ xu hướng Lượt xem & Lượt tải -->
    <div class="col-lg-8">
        <x-card class="p-4 h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h2 class="h5 fw-bold mb-1">Biểu đồ Lượt xem &amp; Lượt tải học liệu theo ngày</h2>
                    <p class="small text-muted mb-0">Dữ liệu trích xuất trực tiếp từ bảng nhật ký truy cập <code>material_logs</code>.</p>
                </div>
                <div class="d-flex gap-3 small">
                    <span><i class="bi bi-circle-fill text-primary me-1"></i>Lượt xem</span>
                    <span><i class="bi bi-circle-fill text-success me-1"></i>Lượt tải</span>
                </div>
            </div>
            <div style="position: relative; height: 290px;">
                <canvas id="dailyActivityChart"></canvas>
            </div>
        </x-card>
    </div>

    <!-- Biểu đồ phân bố Tiến độ học viên -->
    <div class="col-lg-4">
        <x-card class="p-4 h-100">
            <h2 class="h5 fw-bold mb-1">Phân bố tiến độ học viên</h2>
            <p class="small text-muted mb-3">Tỷ lệ hoàn thành trên {{ $totalEnrollments }} lượt học viên ghi danh.</p>

            @if($totalEnrollments > 0)
                <div style="position: relative; height: 210px;" class="mb-3">
                    <canvas id="progressDistributionChart"></canvas>
                </div>
                <div class="d-flex flex-column gap-1 small border-top pt-2">
                    <div class="d-flex justify-content-between">
                        <span><i class="bi bi-circle-fill text-success me-1"></i>Đã hoàn thành (100%)</span>
                        <span class="fw-bold font-mono">{{ $progressDistribution['completed'] }} HV</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><i class="bi bi-circle-fill text-primary me-1"></i>Đang học (1% – 99%)</span>
                        <span class="fw-bold font-mono">{{ $progressDistribution['in_progress'] }} HV</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><i class="bi bi-circle-fill text-secondary me-1"></i>Chưa bắt đầu (0%)</span>
                        <span class="fw-bold font-mono">{{ $progressDistribution['not_started'] }} HV</span>
                    </div>
                </div>
            @else
                <x-empty-state
                    icon="pie-chart"
                    title="Chưa có học viên ghi danh"
                    message="Biểu đồ phân bố tiến độ sẽ hiển thị khi khóa học có học viên tham gia."
                />
            @endif
        </x-card>
    </div>
</div>

<!-- TOP HỌC LIỆU NỔI BẬT -->
@if($topMaterials->isNotEmpty() && $topMaterials->sum('total_interactions') > 0)
<x-card class="p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Top học liệu được truy cập nhiều nhất</h2>
            <p class="small text-muted mb-0">So sánh lượt xem trực tuyến và lượt tải về của các tài liệu, video tiêu biểu.</p>
        </div>
    </div>
    <div style="position: relative; height: 250px;">
        <canvas id="topMaterialsChart"></canvas>
    </div>
</x-card>
@endif

<!-- BẢNG DỮ LIỆU CHI TIẾT (2 TAB: LƯỢT XEM/TẢI HỌC LIỆU & TIẾN ĐỘ HỌC VIÊN) -->
<x-card class="p-0 overflow-hidden">
    <div class="bg-light border-bottom px-3 px-md-4 pt-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <ul class="nav nav-tabs border-bottom-0" id="statDetailTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold py-2 px-3" id="tab-materials-btn" data-bs-toggle="tab" data-bs-target="#tab-materials-pane" type="button" role="tab">
                    <i class="bi bi-folder2-open text-primary me-1"></i>Lượt xem &amp; Tải học liệu
                    <span class="badge bg-primary ms-1">{{ $materialStats->count() }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold py-2 px-3" id="tab-students-btn" data-bs-toggle="tab" data-bs-target="#tab-students-pane" type="button" role="tab">
                    <i class="bi bi-people text-success me-1"></i>Bảng tiến độ học viên
                    <span class="badge bg-success ms-1">{{ $studentProgressRows->count() }}</span>
                </button>
            </li>
            @if(!$selectedCourse)
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold py-2 px-3" id="tab-courses-btn" data-bs-toggle="tab" data-bs-target="#tab-courses-pane" type="button" role="tab">
                    <i class="bi bi-journal-bookmark text-info me-1"></i>Tổng hợp theo khóa học
                    <span class="badge bg-info text-dark ms-1">{{ $courseSummaries->count() }}</span>
                </button>
            </li>
            @endif
        </ul>

        <div class="pb-2">
            <a href="{{ route($exportRoute, $queryParams) }}" class="btn btn-sm btn-outline-success fw-semibold">
                <i class="bi bi-download me-1"></i>Tải xuống toàn bộ bảng (.xlsx)
            </a>
        </div>
    </div>

    <div class="tab-content p-3 p-md-4">
        <!-- TAB 1: BẢNG THỐNG KÊ HỌC LIỆU -->
        <div class="tab-pane fade show active" id="tab-materials-pane" role="tabpanel">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Học liệu</th>
                            <th>Khóa học / Bài giảng</th>
                            <th>Loại</th>
                            <th class="text-center">Lượt xem</th>
                            <th class="text-center">Lượt tải</th>
                            <th class="text-center">HV truy cập</th>
                            <th>Truy cập cuối</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($materialStats as $idx => $item)
                            <tr>
                                <td class="text-muted small font-mono">{{ $idx + 1 }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi {{ $item['type_icon'] }} fs-5"></i>
                                        <div>
                                            <div class="fw-semibold text-dark">{{ $item['title'] }}</div>
                                            @if($item['original_name'])
                                                <div class="small text-muted">{{ $item['original_name'] }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark">{{ $item['course_title'] }}</div>
                                    <div class="small text-muted">{{ $item['lesson_label'] }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $item['type_label'] }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary-subtle text-primary font-mono px-2 py-1">
                                        <i class="bi bi-eye me-1"></i>{{ number_format($item['views_count']) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success-subtle text-success font-mono px-2 py-1">
                                        <i class="bi bi-download me-1"></i>{{ number_format($item['downloads_count']) }}
                                    </span>
                                </td>
                                <td class="text-center font-mono small fw-semibold">
                                    {{ $item['unique_users_count'] }}
                                </td>
                                <td class="small text-muted">
                                    {{ $item['last_accessed_at'] ?: 'Chưa có' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <x-empty-state
                                        icon="folder2-open"
                                        title="Chưa có dữ liệu học liệu"
                                        message="Hãy tải lên tài liệu hoặc video bài giảng vào khóa học để bắt đầu theo dõi lượt xem và lượt tải."
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: BẢNG TIẾN ĐỘ HỌC VIÊN -->
        <div class="tab-pane fade" id="tab-students-pane" role="tabpanel">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Học viên</th>
                            <th>Khóa học</th>
                            <th class="text-center">Bài hoàn thành</th>
                            <th style="min-width: 180px;">Tiến độ xem (%)</th>
                            <th class="text-center">Tương tác video</th>
                            <th class="text-center">Điểm bài tập TB</th>
                            <th>Trạng thái</th>
                            <th>Hoạt động cuối</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($studentProgressRows as $idx => $row)
                            <tr>
                                <td class="text-muted small font-mono">{{ $idx + 1 }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $row['student_name'] }}</div>
                                    <div class="small text-muted">{{ $row['student_email'] }}</div>
                                </td>
                                <td>
                                    <a href="{{ route('courses.show', ['course' => $row['course_id'], 'tab' => 'progress']) }}" class="text-decoration-none fw-semibold">
                                        {{ $row['course_title'] }}
                                    </a>
                                </td>
                                <td class="text-center font-mono small">
                                    <span class="badge bg-light text-dark border">
                                        {{ $row['completed_lessons'] }} / {{ $row['total_lessons'] }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 8px;">
                                            <div class="progress-bar {{ $row['progress_percent'] >= 100 ? 'bg-success' : 'bg-primary' }}" role="progressbar" style="width: {{ $row['progress_percent'] }}%"></div>
                                        </div>
                                        <span class="fw-bold small font-mono text-primary" style="min-width: 40px;">{{ $row['progress_percent'] }}%</span>
                                    </div>
                                </td>
                                <td class="text-center small">
                                    @if($row['video_quiz_score'] !== null)
                                        <span class="badge bg-info-subtle text-info-emphasis border font-mono">
                                            {{ number_format($row['video_quiz_score'], 1) }}/10
                                        </span>
                                        <div class="text-muted" style="font-size: 0.72rem;">({{ $row['video_quiz_detail'] }})</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center small">
                                    @if($row['avg_submission_score'] !== null)
                                        <span class="badge bg-success-subtle text-success border font-mono">
                                            {{ number_format($row['avg_submission_score'], 1) }}/10
                                        </span>
                                        <div class="text-muted" style="font-size: 0.72rem;">({{ $row['submissions_count'] }} bài)</div>
                                    @elseif($row['submissions_count'] > 0)
                                        <span class="badge bg-warning-subtle text-warning-emphasis border">Chờ chấm ({{ $row['submissions_count'] }})</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $row['status_tone'] }}">{{ $row['status_label'] }}</span>
                                </td>
                                <td class="small text-muted">
                                    {{ $row['last_activity_at'] ?: 'Chưa học' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <x-empty-state
                                        icon="people"
                                        title="Chưa có dữ liệu tiến độ học viên"
                                        message="Khi học viên ghi danh và bắt đầu học, tiến độ xem bài giảng và điểm số sẽ xuất hiện tại đây."
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 3: TỔNG HỢP THEO KHÓA HỌC -->
        @if(!$selectedCourse)
        <div class="tab-pane fade" id="tab-courses-pane" role="tabpanel">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Khóa học</th>
                            <th>Môn học</th>
                            @if($isAdmin)<th>Giảng viên</th>@endif
                            <th class="text-center">Học viên</th>
                            <th class="text-center">Học liệu</th>
                            <th class="text-center">Lượt xem</th>
                            <th class="text-center">Lượt tải</th>
                            <th style="min-width: 160px;">Tiến độ TB</th>
                            <th class="text-end">Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($courseSummaries as $cs)
                            <tr>
                                <td class="fw-semibold text-dark">{{ $cs['title'] }}</td>
                                <td><span class="badge bg-primary-subtle text-primary">{{ $cs['subject'] }}</span></td>
                                @if($isAdmin)<td class="small">{{ $cs['teacher'] }}</td>@endif
                                <td class="text-center font-mono">{{ $cs['students_count'] }}</td>
                                <td class="text-center font-mono">{{ $cs['documents_count'] }}</td>
                                <td class="text-center font-mono text-primary fw-semibold">{{ number_format($cs['views_count']) }}</td>
                                <td class="text-center font-mono text-success fw-semibold">{{ number_format($cs['downloads_count']) }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 7px;">
                                            <div class="progress-bar bg-success" style="width: {{ $cs['avg_progress'] }}%"></div>
                                        </div>
                                        <span class="small fw-bold font-mono">{{ $cs['avg_progress'] }}%</span>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route($indexRoute, ['course_id' => $cs['id'], 'period' => $period]) }}" class="btn btn-sm btn-outline-primary">
                                        Lọc khóa này
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdmin ? 9 : 8 }}">
                                    <x-empty-state title="Chưa có khóa học" message="Hãy tạo khóa học mới để theo dõi báo cáo." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</x-card>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    const dailyData = @json($dailyChart);
    const distData = @json($progressDistribution);
    const topMaterials = @json($topMaterials);

    const dailyCanvas = document.getElementById('dailyActivityChart');
    if (dailyCanvas) {
        new Chart(dailyCanvas, {
            type: 'bar',
            data: {
                labels: dailyData.labels,
                datasets: [
                    {
                        label: 'Lượt xem',
                        data: dailyData.views,
                        backgroundColor: 'rgba(29, 78, 216, 0.78)',
                        borderColor: '#1d4ed8',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: 'Lượt tải',
                        data: dailyData.downloads,
                        backgroundColor: 'rgba(22, 163, 74, 0.78)',
                        borderColor: '#16a34a',
                        borderWidth: 1,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    const distCanvas = document.getElementById('progressDistributionChart');
    if (distCanvas) {
        new Chart(distCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Đã hoàn thành (100%)', 'Đang học (1% - 99%)', 'Chưa bắt đầu (0%)'],
                datasets: [{
                    data: [distData.completed || 0, distData.in_progress || 0, distData.not_started || 0],
                    backgroundColor: ['#16a34a', '#2563eb', '#9ca3af'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: { legend: { display: false } }
            }
        });
    }

    const topCanvas = document.getElementById('topMaterialsChart');
    if (topCanvas && topMaterials.length > 0) {
        new Chart(topCanvas, {
            type: 'bar',
            data: {
                labels: topMaterials.map(m => m.title.length > 34 ? m.title.substring(0, 34) + '…' : m.title),
                datasets: [
                    {
                        label: 'Lượt xem',
                        data: topMaterials.map(m => m.views_count),
                        backgroundColor: 'rgba(37, 99, 235, 0.8)',
                        borderRadius: 4
                    },
                    {
                        label: 'Lượt tải',
                        data: topMaterials.map(m => m.downloads_count),
                        backgroundColor: 'rgba(22, 163, 74, 0.8)',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }
});
</script>
@endpush
@endsection
