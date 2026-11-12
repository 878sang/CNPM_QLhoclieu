<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordProgressRequest;
use App\Models\Document;
use App\Models\DocumentProgress;
use Illuminate\Http\JsonResponse;

class DocumentProgressController extends Controller
{
    /**
     * Ghi nhận vị trí đang xem của học viên với một video đã duyệt trong khóa học đã ghi danh.
     */
    public function store(RecordProgressRequest $request, Document $document): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $document->isVideo()
            && $document->allow_view
            && $document->isApproved()
            && $document->course->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists(),
            403,
        );

        $progress = DocumentProgress::record($user, $document, $request->integer('position'), $request->integer('duration'));

        return response()->json([
            'percent' => $progress->percent,
            'position' => $progress->position_seconds,
            'completed' => $progress->completed_at !== null,
        ]);
    }
}
