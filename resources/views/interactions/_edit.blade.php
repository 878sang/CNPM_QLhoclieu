@if($interaction->user_id === auth()->id())
    <details class="mb-2">
        <summary class="small text-primary">Sửa bình luận</summary>
        <form method="POST" action="{{ route('interactions.update', $interaction) }}" class="mt-2">
            @csrf
            @method('PATCH')
            <label for="edit-comment-{{ $interaction->id }}" class="form-label small">Nội dung bình luận</label>
            <textarea id="edit-comment-{{ $interaction->id }}" name="content" class="form-control mb-2" maxlength="2000" rows="3" required>{{ old('content', $interaction->content) }}</textarea>
            <button type="submit" class="btn btn-sm btn-primary">Lưu thay đổi</button>
        </form>
    </details>
@endif
