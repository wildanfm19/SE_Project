<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ReviewLike;
use Illuminate\Http\Request;

class ReviewLikeController extends Controller
{
    public function toggleLike(Review $review)
    {
        $like = $review->likes()->where('user_id', auth()->id())->first();

        if ($like) {
            $like->delete();
            $review->decrement('helpful_count');
            $message = 'Review unliked successfully';
        } else {
            $review->likes()->create(['user_id' => auth()->id()]);
            $review->increment('helpful_count');
            $message = 'Review liked successfully';
        }

        return response()->json([
            'message' => $message,
            'helpful_count' => $review->helpful_count
        ]);
    }
}
