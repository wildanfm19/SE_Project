<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ReviewResponse;
use Illuminate\Http\Request;

class ReviewResponseController extends Controller
{
    public function store(Request $request, Review $review)
    {
        // Verify if user is the seller of the product
        if ($review->product->seller_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if response already exists
        if ($review->sellerResponse()->exists()) {
            return response()->json(['message' => 'Response already exists'], 400);
        }

        $request->validate([
            'response' => 'required|string|max:1000'
        ]);

        $response = $review->sellerResponse()->create([
            'seller_id' => auth()->id(),
            'response' => $request->response
        ]);

        return response()->json($response, 201);
    }

    public function update(Request $request, Review $review)
    {
        if ($review->product->seller_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $response = $review->sellerResponse;
        if (!$response) {
            return response()->json(['message' => 'Response not found'], 404);
        }

        $request->validate([
            'response' => 'required|string|max:1000'
        ]);

        $response->update([
            'response' => $request->response
        ]);

        return response()->json($response);
    }

    public function destroy(Review $review)
    {
        if ($review->product->seller_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $response = $review->sellerResponse;
        if (!$response) {
            return response()->json(['message' => 'Response not found'], 404);
        }

        $response->delete();
        return response()->json(['message' => 'Response deleted successfully']);
    }
}
