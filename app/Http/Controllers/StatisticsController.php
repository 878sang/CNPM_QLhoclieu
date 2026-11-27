<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Document;
use App\Models\MaterialLog;
use App\Models\Progress;
use App\Models\Submission;
use App\Models\User;
use App\Services\ExcelExportService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatisticsController extends Controller
{
    public function __construct(private ExcelExportService $excelExporter) {}

    public function index(Request $request): View
    {
        $report = $this->buildReportData($request);

        return view('statistics.index', $report);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $report = $this->buildReportData($request);

        /** @var User $user */
        $user = Auth::user();
        $selectedCourse = $report['selectedCourse'];
        $periodLabel = $report['periodLabel'];

        // 1. Sheet Tổng quan
        $overviewRows = [
            ['BÁO CÁO THỐNG KÊ HỌC LIỆU & TIẾN ĐỘ HỌC TẬP', ''],
            ['Người xuất báo cáo', $user->name.' ('.$user->email.')'],
            ['Vai trò', $user->hasRole('admin') ? 'Quản trị viên' : 'Giảng viên'],
            ['Phạm vi khóa học', $selectedCourse ? $selectedCourse->title : 'Tất cả khóa học phụ trách'],
            ['Kỳ thống kê lượt xem/tải', $periodLabel],
            ['Thời điểm xuất báo cáo', now()->format('d/m/Y H:i:s')],
            ['', ''],
            ['CHỈ SỐ TỔNG HỢP', 'GIÁ TRỊ'],
            ['Tổng số khóa học trong phạm vi', $report['coursesCount']],
            ['Tổng số học liệu đã tải lên', $report['totalDocuments']],
            ['Tổng lượt xem học liệu (MaterialLogs)', $report['totalViews']],
            ['Tổng lượt tải học liệu (MaterialLogs)', $report['totalDownloads']],
            ['Tổng số lượt ghi danh học viên đang học', $report['totalEnrollments']],
            ['Tiến độ hoàn thành trung bình (%)', $report['avgProgress']],
            ['Số học viên đã hoàn thành 100%', $report['progressDistribution']['completed']],
            ['Số học viên đang học (1% - 99%)', $report['progressDistribution']['in_progress']],
            ['Số học viên chưa bắt đầu (0%)', $report['progressDistribution']['not_started']],
            ['Tổng số lượt bài học đã hoàn thành', $report['completedLessonsCount']],
            ['Điểm trung bình bài tập (thang 10)', $report['avgAssignmentScore'] ?? 'Chưa có bài chấm'],
        ];

        // Thêm bảng tóm tắt theo từng khóa học ở cuối Sheet Tổng quan
        $overviewRows[] = ['', ''];
        $overviewRows[] = ['THỐNG KÊ THEO KHÓA HỌC', 'Môn học', 'Giảng viên', 'Học viên', 'Học liệu', 'Lượt xem', 'Lượt tải', 'Tiến độ TB (%)'];
        foreach ($report['courseSummaries'] as $cs) {
            $overviewRows[] = [
                $cs['title'],
                $cs['subject'],
                $cs['teacher'],
                $cs['students_count'],
                $cs['documents_count'],
                $cs['views_count'],
                $cs['downloads_count'],
                $cs['avg_progress'],
            ];
        }

        // 2. Sheet Thống kê lượt xem & lượt tải học liệu
        $materialHeaders = [
            'STT',
            'Tên học liệu',
            'Khóa học',
            'Chương / Bài học',
            'Loại học liệu',
            'Trạng thái duyệt',
            'Lượt xem (Views)',
            'Lượt tải (Downloads)',
            'Tổng tương tác',
            'Học viên truy cập (Unique)',
            'Truy cập gần nhất',
        ];
        $materialRows = [];
        foreach ($report['materialStats'] as $idx => $m) {
            $materialRows[] = [
                $idx + 1,
                $m['title'],
                $m['course_title'],
                $m['lesson_label'],
                $m['type_label'],
                $m['status_label'],
                $m['views_count'],
                $m['downloads_count'],
                $m['total_interactions'],
                $m['unique_users_count'],
                $m['last_accessed_at'] ?: 'Chưa có',
            ];
        }

        // 3. Sheet Tiến độ học viên
        $progressHeaders = [
            'STT',
            'Họ và tên học viên',
            'Username',
            'Email',
            'Khóa học',
            'Bài đã hoàn thành',
            'Tổng số bài học',
            'Tiến độ xem (%)',
            'Điểm tương tác video (/10)',
            'Bài tập đã nộp',
            'Điểm bài tập TB (/10)',
            'Trạng thái học tập',
            'Hoạt động gần nhất',
        ];
        $progressRows = [];
        foreach ($report['studentProgressRows'] as $idx => $sp) {
            $progressRows[] = [
                $idx + 1,
                $sp['student_name'],
                $sp['student_username'],
                $sp['student_email'],
                $sp['course_title'],
                $sp['completed_lessons'],
                $sp['total_lessons'],
                $sp['progress_percent'],
                $sp['video_quiz_score'] !== null ? $sp['video_quiz_score'] : '—',
                $sp['submissions_count'],
                $sp['avg_submission_score'] !== null ? $sp['avg_submission_score'] : '—',
                $sp['status_label'],
                $sp['last_activity_at'] ?: 'Chưa học',
            ];
        }

        $slugSuffix = $selectedCourse ? '-khoa-'.$selectedCourse->id : '-toan-bo';
        $filename = 'Bao-cao-thong-ke'.$slugSuffix.'-'.now()->format('Y-m-d').'.xlsx';

        return $this->excelExporter->download($filename, [
            [
                'title' => 'Tổng quan',
                'headers' => ['Chỉ tiêu thống kê', 'Giá trị / Chi tiết', 'Thông tin 3', 'Thông tin 4', 'Thông tin 5', 'Thông tin 6', 'Thông tin 7', 'Thông tin 8'],
                'rows' => $overviewRows,
                'widths' => [38, 30, 18, 14, 14, 14, 14, 16],
            ],
            [
                'title' => 'Lượt xem & Tải học liệu',
                'headers' => $materialHeaders,
                'rows' => $materialRows,
                'widths' => [8, 36, 28, 28, 16, 16, 16, 18, 16, 22, 20],
            ],
            [
                'title' => 'Tiến độ học viên',
                'headers' => $progressHeaders,
                'rows' => $progressRows,
                'widths' => [8, 26, 18, 28, 28, 18, 16, 16, 22, 16, 20, 18, 20],
            ],
        ]);
    }

    /**
     * Xây dựng toàn bộ dữ liệu thống kê (dùng chung cho giao diện Dashboard và Xuất Excel).
     *
     * @return array<string, mixed>
     */
    private function buildReportData(Request $request): array
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');

        // Danh sách khóa học trong phạm vi quyền hạn
        $accessibleCourses = Course::with(['subject', 'teacher'])
            ->unless($isAdmin, fn (Builder $q) => $q->where('teacher_id', $user->id))
            ->when($isAdmin && $request->filled('teacher_id'), fn (Builder $q) => $q->where('teacher_id', $request->integer('teacher_id')))
            ->orderBy('title')
            ->get();

        $selectedCourseId = $request->filled('course_id') ? $request->integer('course_id') : null;
        $selectedCourse = $selectedCourseId ? $accessibleCourses->firstWhere('id', $selectedCourseId) : null;
        if ($selectedCourseId && ! $selectedCourse) {
            abort(403, 'Bạn không có quyền xem thống kê khóa học này.');
        }

        $scopedCourses = $selectedCourse ? collect([$selectedCourse]) : $accessibleCourses;
        $courseIds = $scopedCourses->pluck('id')->all();

        // Giải quyết bộ lọc khoảng thời gian cho MaterialLogs
        [$fromDate, $toDate, $period, $periodLabel] = $this->resolveDateRange($request);

        // Truy vấn gốc MaterialLog theo khóa học và thời gian
        $baseLogsQuery = fn (): Builder => MaterialLog::query()
            ->whereIn('course_id', $courseIds ?: [-1])
            ->when($fromDate, fn (Builder $q) => $q->where('created_at', '>=', $fromDate->copy()->startOfDay()))
            ->when($toDate, fn (Builder $q) => $q->where('created_at', '<=', $toDate->copy()->endOfDay()));

        $actionTotals = $baseLogsQuery()
            ->select('action', DB::raw('COUNT(*) as total'))
            ->groupBy('action')
            ->pluck('total', 'action');

        $totalViews = (int) ($actionTotals['view'] ?? 0);
        $totalDownloads = (int) ($actionTotals['download'] ?? 0);

        // Biểu đồ xu hướng theo ngày (mặc định 14 ngày nếu chọn 'all' để biểu đồ gọn đẹp)
        $chartEnd = $toDate ? $toDate->copy()->startOfDay() : now()->startOfDay();
        $chartStart = $fromDate
            ? $fromDate->copy()->startOfDay()
            : $chartEnd->copy()->subDays(13);
        if ($chartStart->gt($chartEnd)) {
            $chartStart = $chartEnd->copy();
        }
        // Giới hạn tối đa 60 điểm ngày trên biểu đồ để tránh quá dày
        if ($chartStart->diffInDays($chartEnd) > 59) {
            $chartStart = $chartEnd->copy()->subDays(59);
        }

        $dailyRaw = MaterialLog::query()
            ->whereIn('course_id', $courseIds ?: [-1])
            ->whereBetween('created_at', [$chartStart->copy()->startOfDay(), $chartEnd->copy()->endOfDay()])
            ->select(
                DB::raw('DATE(created_at) as log_date'),
                'action',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy(DB::raw('DATE(created_at)'), 'action')
            ->get();

        $dailyMap = [];
        foreach ($dailyRaw as $row) {
            $d = (string) $row->log_date;
            $dailyMap[$d][$row->action] = (int) $row->total;
        }

        $dailyLabels = [];
        $dailyViews = [];
        $dailyDownloads = [];
        foreach (CarbonPeriod::create($chartStart, $chartEnd) as $day) {
            $key = $day->format('Y-m-d');
            $dailyLabels[] = $day->format('d/m');
            $dailyViews[] = $dailyMap[$key]['view'] ?? 0;
            $dailyDownloads[] = $dailyMap[$key]['download'] ?? 0;
        }

        // Thống kê chi tiết từng học liệu
        $logAggByDoc = $baseLogsQuery()
            ->select(
                'document_id',
                DB::raw("SUM(CASE WHEN action = 'view' THEN 1 ELSE 0 END) as views_count"),
                DB::raw("SUM(CASE WHEN action = 'download' THEN 1 ELSE 0 END) as downloads_count"),
                DB::raw('COUNT(DISTINCT user_id) as unique_users_count'),
                DB::raw('MAX(created_at) as last_accessed_at')
            )
            ->groupBy('document_id')
            ->get()
            ->keyBy('document_id');

        $documents = Document::with(['course:id,title', 'lesson:id,chapter_id,title', 'lesson.chapter:id,title'])
            ->whereIn('course_id', $courseIds ?: [-1])
            ->latest()
            ->get();

        $materialStats = $documents->map(function (Document $doc) use ($logAggByDoc): array {
            $agg = $logAggByDoc->get($doc->id);
            $views = (int) ($agg->views_count ?? 0);
            $downloads = (int) ($agg->downloads_count ?? 0);
            $lastRaw = $agg->last_accessed_at ?? null;

            $lessonLabel = 'Tài liệu chung';
            if ($doc->lesson) {
                $chapterPrefix = $doc->lesson->chapter ? $doc->lesson->chapter->title.' - ' : '';
                $lessonLabel = $chapterPrefix.$doc->lesson->title;
            }

            return [
                'id' => $doc->id,
                'title' => $doc->title,
                'original_name' => $doc->original_name,
                'course_id' => $doc->course_id,
                'course_title' => $doc->course?->title ?? '—',
                'lesson_label' => $lessonLabel,
                'type_label' => $doc->typeLabel(),
                'type_icon' => $doc->typeIcon(),
                'status_label' => $doc->statusLabel(),
                'status_badge_class' => $doc->statusBadgeClass(),
                'views_count' => $views,
                'downloads_count' => $downloads,
                'total_interactions' => $views + $downloads,
                'unique_users_count' => (int) ($agg->unique_users_count ?? 0),
                'last_accessed_at' => $lastRaw ? Carbon::parse($lastRaw)->format('d/m/Y H:i') : null,
            ];
        })->sortByDesc('total_interactions')->values();

        $topMaterials = $materialStats->take(8)->values();

        // Tải dữ liệu đầy đủ cho các khóa học để tính Tiến độ học viên không bị N+1
        $richCourses = Course::with([
            'subject',
            'teacher',
            'chapters.lessons.documents',
            'progresses',
            'videoInteractions.responses',
            'enrollments' => fn ($q) => $q->where('status', 'active')->with('user'),
        ])
            ->whereIn('id', $courseIds ?: [-1])
            ->orderBy('title')
            ->get()
            ->keyBy('id');

        // Điểm bài tập của học viên theo khóa học
        $submissionsByCourseUser = Submission::query()
            ->join('assignments', 'submissions.assignment_id', '=', 'assignments.id')
            ->whereIn('assignments.course_id', $courseIds ?: [-1])
            ->select(
                'assignments.course_id',
                'submissions.user_id',
                DB::raw('COUNT(submissions.id) as submissions_count'),
                DB::raw('AVG(CASE WHEN submissions.score IS NOT NULL AND submissions.max_score > 0 THEN (submissions.score * 10.0 / submissions.max_score) ELSE NULL END) as avg_score_10')
            )
            ->groupBy('assignments.course_id', 'submissions.user_id')
            ->get()
            ->keyBy(fn ($r) => $r->course_id.'_'.$r->user_id);

        $allScaledScores = [];
        $studentProgressRows = [];
        $progressDistribution = [
            'completed' => 0,
            'in_progress' => 0,
            'not_started' => 0,
        ];
        $sumProgressPercent = 0;
        $totalEnrollments = 0;

        // Tổng hợp lượt xem/tải theo từng khóa
        $courseLogTotals = $baseLogsQuery()
            ->select(
                'course_id',
                DB::raw("SUM(CASE WHEN action = 'view' THEN 1 ELSE 0 END) as views_count"),
                DB::raw("SUM(CASE WHEN action = 'download' THEN 1 ELSE 0 END) as downloads_count")
            )
            ->groupBy('course_id')
            ->get()
            ->keyBy('course_id');

        $courseSummaries = [];

        foreach ($richCourses as $course) {
            $publishedLessons = $course->chapters
                ->pluck('lessons')
                ->flatten()
                ->filter(fn ($l) => ($l->status ?? 'draft') === 'published');
            $totalPublishedLessons = $publishedLessons->count();
            $publishedLessonIds = $publishedLessons->pluck('id')->all();

            $courseProgressSum = 0;
            $courseActiveCount = 0;

            foreach ($course->enrollments as $enrollment) {
                $student = $enrollment->user;
                if (! $student) {
                    continue;
                }

                $totalEnrollments++;
                $courseActiveCount++;

                $percent = $course->progressPercentageFor($student);
                $sumProgressPercent += $percent;
                $courseProgressSum += $percent;

                $statusKey = $course->progressStatusFor($student);
                $progressDistribution[$statusKey] = ($progressDistribution[$statusKey] ?? 0) + 1;

                $studentCourseProgresses = $course->progresses
                    ->where('user_id', $student->id)
                    ->whereIn('lesson_id', $publishedLessonIds);

                $completedLessons = $studentCourseProgresses->where('is_completed', true)->count();
                $lastProgUpdate = $studentCourseProgresses->max('updated_at');

                $videoStats = $course->videoInteractionStatsFor($student);
                $videoScore = ($videoStats['total'] > 0 && $videoStats['answered'] > 0)
                    ? $videoStats['score_10']
                    : null;

                $subStat = $submissionsByCourseUser->get($course->id.'_'.$student->id);
                $subCount = (int) ($subStat->submissions_count ?? 0);
                $avgSubScore = ($subStat && $subStat->avg_score_10 !== null)
                    ? round((float) $subStat->avg_score_10, 1)
                    : null;

                if ($avgSubScore !== null) {
                    $allScaledScores[] = $avgSubScore;
                }

                $studentProgressRows[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'student_username' => $student->username,
                    'student_email' => $student->email,
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                    'completed_lessons' => $completedLessons,
                    'total_lessons' => $totalPublishedLessons,
                    'progress_percent' => $percent,
                    'video_quiz_score' => $videoScore,
                    'video_quiz_detail' => $videoStats['total'] > 0
                        ? "{$videoStats['correct']}/{$videoStats['total']}"
                        : null,
                    'submissions_count' => $subCount,
                    'avg_submission_score' => $avgSubScore,
                    'status_key' => $statusKey,
                    'status_label' => $course->progressStatusLabel($student),
                    'status_tone' => $course->progressStatusTone($student),
                    'last_activity_at' => $lastProgUpdate ? Carbon::parse($lastProgUpdate)->format('d/m/Y H:i') : null,
                ];
            }

            $cLog = $courseLogTotals->get($course->id);
            $courseSummaries[] = [
                'id' => $course->id,
                'title' => $course->title,
                'subject' => $course->subject?->name ?? '—',
                'teacher' => $course->teacher?->name ?? '—',
                'students_count' => $courseActiveCount,
                'documents_count' => $documents->where('course_id', $course->id)->count(),
                'views_count' => (int) ($cLog->views_count ?? 0),
                'downloads_count' => (int) ($cLog->downloads_count ?? 0),
                'avg_progress' => $courseActiveCount > 0 ? (int) round($courseProgressSum / $courseActiveCount) : 0,
            ];
        }

        // Sắp xếp danh sách tiến độ học viên theo % giảm dần
        usort($studentProgressRows, fn (array $a, array $b) => $b['progress_percent'] <=> $a['progress_percent']);

        $avgProgress = $totalEnrollments > 0 ? (int) round($sumProgressPercent / $totalEnrollments) : 0;
        $completedLessonsCount = Progress::whereIn('course_id', $courseIds ?: [-1])
            ->where('is_completed', true)
            ->count();

        $avgAssignmentScore = count($allScaledScores) > 0
            ? round(array_sum($allScaledScores) / count($allScaledScores), 1)
            : null;

        $teachers = $isAdmin
            ? User::whereRelation('role', 'name', 'teacher')->orderBy('name')->get()
            : collect();

        return [
            'isAdmin' => $isAdmin,
            'layout' => $isAdmin ? 'layouts.admin' : 'layouts.teacher',
            'courses' => $accessibleCourses,
            'teachers' => $teachers,
            'selectedCourse' => $selectedCourse,
            'selectedCourseId' => $selectedCourseId,
            'period' => $period,
            'periodLabel' => $periodLabel,
            'fromDate' => $fromDate?->format('Y-m-d'),
            'toDate' => $toDate?->format('Y-m-d'),
            'coursesCount' => $scopedCourses->count(),
            'totalDocuments' => $documents->count(),
            'totalViews' => $totalViews,
            'totalDownloads' => $totalDownloads,
            'totalEnrollments' => $totalEnrollments,
            'avgProgress' => $avgProgress,
            'completedLessonsCount' => $completedLessonsCount,
            'avgAssignmentScore' => $avgAssignmentScore,
            'progressDistribution' => $progressDistribution,
            'dailyChart' => [
                'labels' => $dailyLabels,
                'views' => $dailyViews,
                'downloads' => $dailyDownloads,
            ],
            'topMaterials' => $topMaterials,
            'materialStats' => $materialStats,
            'studentProgressRows' => collect($studentProgressRows),
            'courseSummaries' => collect($courseSummaries),
        ];
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon, 2: string, 3: string}
     */
    private function resolveDateRange(Request $request): array
    {
        $period = (string) $request->query('period', '30d');

        if ($request->filled('from') || $request->filled('to')) {
            try {
                $from = $request->filled('from') ? Carbon::parse((string) $request->query('from'))->startOfDay() : null;
                $to = $request->filled('to') ? Carbon::parse((string) $request->query('to'))->endOfDay() : null;
                $label = ($from ? $from->format('d/m/Y') : 'Từ đầu').' đến '.($to ? $to->format('d/m/Y') : 'nay');

                return [$from, $to, 'custom', $label];
            } catch (\Throwable) {
                // Rơi về mặc định nếu ngày không hợp lệ
            }
        }

        return match ($period) {
            '7d' => [now()->subDays(6)->startOfDay(), now()->endOfDay(), '7d', '7 ngày gần nhất'],
            '90d' => [now()->subDays(89)->startOfDay(), now()->endOfDay(), '90d', '90 ngày gần nhất'],
            'all' => [null, null, 'all', 'Toàn thời gian'],
            default => [now()->subDays(29)->startOfDay(), now()->endOfDay(), '30d', '30 ngày gần nhất'],
        };
    }
}
