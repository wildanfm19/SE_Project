<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ArticleController extends Controller
{
    public function index()
    {
        $articles = Article::with('seller:seller_id')->latest()->get();
        return response()->json([
            'code' => '000',
            'articles' => $articles
        ], 200);

    }

    public function store(Request $request) 
    {
        // Auth check
        if (!Auth::check()) {
            return response()->json([
                'code' => '102',
                'message' => 'Unauthorized access, please login first',
            ], 401); // Changed to 401 for unauthorized
        }

        $user = Auth::user();

        if (!$user->isSeller()) {
            return response()->json([
                'code' => '103',
                'message' => 'Only seller can add articles',
            ], 403);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => '101',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if seller exists
            if (!$user->seller) {
                return response()->json([
                    'code' => '104',
                    'message' => 'Seller profile not found',
                ], 404);
            }

            // Upload and validate image
            if (!$request->hasFile('image')) {
                return response()->json([
                    'code' => '105',
                    'message' => 'Image file is required',
                ], 422);
            }

            $imagePath = $request->file('image')->store('articles', 'public');

            // Create article with DB transaction
            DB::beginTransaction();
            
            $article = Article::create([
                'title' => trim($request->title),
                'content' => trim($request->content),
                'image' => '/storage/' . $imagePath,
                'seller_id' => $user->seller->seller_id,
                'created_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'code' => '000',
                'message' => 'Article created successfully!',
                'article' => $article,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Delete uploaded image if article creation fails
            if (isset($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            return response()->json([
                'code' => '500',
                'message' => 'Failed to create article',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => '101',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $article = Article::find($id);

        if (!$article) {
            return response()->json([
                'code' => '404',
                'message' => 'Article not found',
            ], 404);
        }

        if (Auth::user()->seller->seller_id !== $article->seller_id) {
            return response()->json([
                'code' => '103',
                'message' => 'Action not allowed, you are not the owner of this article'
            ], 403);
        }

        try {
            // Handle image update
            if ($request->hasFile('image')) {
                // Delete old image
                if ($article->image) {
                    $oldPath = str_replace('/storage/', '', $article->image);
                    Storage::disk('public')->delete($oldPath);
                }
                
                // Upload new image
                $imagePath = $request->file('image')->store('articles', 'public');
                $article->image = '/storage/' . $imagePath;
            }

            $article->title = $request->title ?? $article->title;
            $article->content = $request->content ?? $article->content;
            $article->save();

            return response()->json([
                'code' => '000',
                'message' => 'Article updated successfully',
                'article' => $article,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'message' => 'Failed to update article',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $article = Article::find($id);

        if (!$article) {
            return response()->json([
                'code' => '404',
                'message' => 'Article not found',
            ], 404);
        }

        if (Auth::user()->seller->seller_id !== $article->seller_id) {
            return response()->json([
                'code' => '103',
                'message' => 'Action not allowed, you are not the owner of this article'
            ], 403);
        }

        try {
            // Delete image
            if ($article->image) {
                $imagePath = str_replace('/storage/', '', $article->image);
                Storage::disk('public')->delete($imagePath);
            }

            $article->delete();

            return response()->json([
                'code' => '000',
                'message' => 'Article deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'message' => 'Failed to delete article',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
