@php
    $likedByMe = $item->likedByUsers->contains('id', $user->id);
@endphp
<div id="interaction-{{ $item->id }}" class="border rounded-3 p-3 bg-white {{ $item->is_pinned ? 'border-primary' : '' }}">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
        <div class="min-w-0">
            <span class="fw-semibold text-dark">{{ $item->user?->name }}</span>
            @if($item->user?->hasRole(['admin', 'teacher']))
                <span class="badge bg-primary ms-1">Giảng viên</span>
            @endif
            @if($item->is_pinned)
                <span class="badge bg-primary-subtle text-primary border border-primary ms-1"><i class="bi bi-pin-angle-fill"></i> Ghim</span>
            @endif
            @if($item->isQuestion())
                <span class="badge {{ $item->statusBadgeClass() }} ms-1">{{ $item->statusLabel() }}</span>
            @endif
            @if($item->formattedVideoTimestamp())
                <button
                    type="button"
                    class="btn btn-dark btn-sm font-monospace py-0 px-2 ms-1 jump-to-video-ts-btn"
                    data-seconds="{{ $item->video_timestamp_seconds }}"
                    title="Nhảy tới đoạn {{ $item->formattedVideoTimestamp() }} trên video bài giảng"
                >
                    <i class="bi bi-play-fill"></i> {{ $item->formattedVideoTimestamp() }}
                </button>
            @endif
            <div class="small text-muted">{{ $item->created_at->diffForHumans() }}</div>
        </div>
        <div class="d-flex align-items-center gap-1 flex-shrink-0">
            <form method="POST" action="{{ route('interactions.like', $item) }}">
                @csrf
                <button type="submit" class="btn btn-sm {{ $likedByMe ? 'btn-primary' : 'btn-outline-secondary' }} py-0 px-2">
                    <i class="bi {{ $likedByMe ? 'bi-hand-thumbs-up-fill' : 'bi-hand-thumbs-up' }} me-1"></i>{{ $item->likes_count }}
                </button>
            </form>
            @if($item->user_id === $user->id)
                <form method="POST" action="{{ route('interactions.destroy', $item) }}" onsubmit="return confirm('Xóa nội dung này?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2"><i class="bi bi-trash"></i></button>
                </form>
            @endif
        </div>
    </div>

    <div class="mb-2 text-dark" style="white-space: pre-line;">{{ $item->content }}</div>
    @include('interactions._edit', ['interaction' => $item])

    @if($item->replies->isNotEmpty())
        <div class="ms-3 ps-3 border-start border-2 mt-2 mb-2">
            @foreach($item->replies as $reply)
                <div class="p-2 px-3 bg-light rounded border mb-2 small">
                    <div class="mb-1">
                        <strong>{{ $reply->user?->name }}</strong>
                        @if($reply->user?->hasRole(['admin', 'teacher']))
                            <span class="badge bg-primary ms-1">Giảng viên</span>
                        @endif
                        <span class="text-muted ms-2">{{ $reply->created_at->diffForHumans() }}</span>
                    </div>
                    <div style="white-space: pre-line;">{{ $reply->content }}</div>
                    @include('interactions._edit', ['interaction' => $reply])
                    @if($reply->user_id === $user->id)
                        <form method="POST" action="{{ route('interactions.destroy', $reply) }}" onsubmit="return confirm('Xóa phản hồi này?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit">Xóa phản hồi</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('interactions.store', [$course, $currentLesson]) }}" class="mt-2">
        @csrf
        <input type="hidden" name="parent_id" value="{{ $item->id }}">
        <input type="hidden" name="type" value="{{ $item->isQuestion() ? 'question' : 'discussion' }}">
        <div class="input-group input-group-sm">
            <input type="text" name="content" class="form-control" placeholder="Viết phản hồi..." required>
            <button type="submit" class="btn btn-outline-primary">Trả lời</button>
        </div>
    </form>
</div>
