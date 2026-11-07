@extends('layouts.admin')

@section('content')
    <x-page-header
        eyebrow="QUẢN TRỊ / DANH MỤC"
        title="Môn học"
        description="Quản lý các môn học dùng chung cho những khóa ôn thi THPT."
    >
        <x-slot:actions>
            <x-button :href="route('admin.subjects.create')">
                <i class="bi bi-plus-lg me-2" aria-hidden="true"></i>
                Thêm môn học
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="p-3 p-md-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Môn học</th>
                        <th>Mô tả</th>
                        <th>Khóa học</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subjects as $subject)
                        <tr>
                            <td class="fw-semibold">{{ $subject->name }}</td>
                            <td class="text-muted">{{ $subject->description ?: 'Chưa có mô tả' }}</td>
                            <td>{{ $subject->courses_count }}</td>
                            <td class="text-end text-nowrap">
                                <x-button :href="route('admin.subjects.edit', $subject)" variant="outline-secondary" class="btn-sm">
                                    Sửa
                                </x-button>
                                <form class="d-inline" method="POST" action="{{ route('admin.subjects.destroy', $subject) }}" onsubmit="return confirm('Xóa môn học này?')">
                                    @csrf
                                    @method('DELETE')
                                    <x-button variant="outline-danger" class="btn-sm">Xóa</x-button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-empty-state
                                    title="Chưa có môn học"
                                    message="Hãy thêm môn học đầu tiên cho hệ thống."
                                    icon="book"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $subjects->links('pagination::bootstrap-5') }}</div>
    </x-card>
@endsection
