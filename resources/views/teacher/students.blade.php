@extends('layouts.teacher')

@section('content')
    <x-page-header
        eyebrow="QUẢN LÝ LỚP"
        title="Học viên"
        description="Danh sách học viên đang tham gia các khóa học của bạn."
    />

    <x-card class="p-3 p-md-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Học viên</th>
                        <th>Email</th>
                        <th>Khóa học của bạn</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $student)
                        <tr>
                            <td class="fw-semibold">{{ $student->name }}</td>
                            <td>{{ $student->email }}</td>
                            <td>{{ $student->teacher_course_count }}</td>
                            <td><x-badge tone="success">Đang hoạt động</x-badge></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-empty-state
                                    title="Chưa có học viên"
                                    message="Học viên được thêm từ trang quản lý của từng khóa học."
                                    icon="people"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $students->links('pagination::bootstrap-5') }}</div>
    </x-card>
@endsection
