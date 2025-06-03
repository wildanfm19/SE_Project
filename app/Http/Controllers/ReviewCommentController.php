<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ReviewComment;
use Illuminate\Http\Request;

class ReviewCommentController extends Controller
{
    public function index(Review $review)
    {
        return response()->json(
            $review->comments()->with('user')->latest()->paginate(10)
        );
    }

    public function store(Request $request, Review $review)
    {
        $request->validate([
            'comment' => 'required|string|max:500'
        ]);

        $comment = $review->comments()->create([
            'user_id' => auth()->id(),
            'comment' => $request->comment
        ]);

        return response()->json($comment->load('user'), 201);
    }

    public function update(Request $request, ReviewComment $comment)
    {
        if ($comment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'comment' => 'required|string|max:500'
        ]);

        $comment->update([
            'comment' => $request->comment
        ]);

        return response()->json($comment->load('user'));
    }

    public function destroy(ReviewComment $comment)
    {
        if ($comment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->delete();
        return response()->json(['message' => 'Comment deleted successfully']);
    }
}
