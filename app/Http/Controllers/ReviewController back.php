<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    /**
     * Menampilkan semua review
     */
    public function index()
    {
        $reviews = Review::with('product', 'user')->get(); // Mengambil review beserta relasi produk dan user
        return response()->json($reviews, 200);
    }

    /**
     * Menyimpan review baru
     */
    public function store(Request $request)
    {
        // Validasi data input
        $request->validate([
            'rating' => 'required|numeric|min:1|max:5',
            'review_text' => 'required|string|max:1000',
        ]);

        // Menentukan product_id secara otomatis (misalnya semua untuk produk dengan ID 1)
        $product_id = 1; // Sesuaikan nilai ini dengan produk yang sesuai

        // Mendapatkan user yang sedang login (jika menggunakan autentikasi)
        $user_id = Auth::id(); // Atau isi manual jika tidak menggunakan autentikasi

        // Membuat review baru
        $review = Review::create([
            'product_id' => $product_id, // Product ID diisi otomatis
            'user_id' => $user_id,       // User ID (jika pakai autentikasi)
            'rating' => $request->rating,
            'review_text' => $request->review_text,
        ]);

        return response()->json($review, 201); // 201 Created
    }

    /**
     * Menampilkan detail review berdasarkan ID
     */
    public function show($id)
    {
        $review = Review::with('product', 'user')->find($id);

        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        return response()->json($review, 200);
    }

    /**
     * Memperbarui review berdasarkan ID
     */
    public function update(Request $request, $id)
    {
        $review = Review::find($id);

        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        // Validasi data input
        $request->validate([
            'rating' => 'required|numeric|min:1|max:5',
            'review_text' => 'required|string|max:1000',
        ]);

        // Hanya user yang membuat review yang bisa mengeditnya
        if ($review->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $review->update([
            'rating' => $request->rating,
            'review_text' => $request->review_text,
        ]);

        return response()->json($review, 200);
    }

    /**
     * Menghapus review berdasarkan ID
     */
    public function destroy($id)
    {
        $review = Review::find($id);

        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        // Hanya user yang membuat review yang bisa menghapusnya
        if ($review->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $review->delete();
        return response()->json(['message' => 'Review deleted successfully'], 200);
    }
}
