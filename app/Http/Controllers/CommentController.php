<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CommentController extends Controller
{
   
    /**
     * Mendapatkan semua komentar
     */
    public function index()
    {
        try {
            $comments = Comment::with(['user:id,name', 'article:id,title'])
                             ->latest()
                             ->get();

            return response()->json([
                'status' => 'success',
                'data' => $comments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch comments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan komentar untuk artikel tertentu
     */
    public function getArticleComments($article_id)
    {
        try {
            // Validasi artikel exists
            if (!Article::find($article_id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Article not found'
                ], 404);
            }

            // Ambil komentar utama dengan balasannya
            $comments = Comment::where('article_id', $article_id)
                             ->whereNull('parent_id')
                             ->with(['user:id,name', 
                                    'replies.user:id,name',
                                    'replies' => function($query) {
                                        $query->latest();
                                    }])
                             ->latest()
                             ->get();

            return response()->json([
                'status' => 'success',
                'data' => $comments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch comments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan balasan untuk komentar tertentu
     */
    public function getReplies($comment_id)
    {
        try {
            $comment = Comment::findOrFail($comment_id);
            
            $replies = Comment::where('parent_id', $comment_id)
                            ->with('user:id,name')
                            ->latest()
                            ->get();

            return response()->json([
                'status' => 'success',
                'data' => $replies
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Comment not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch replies',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menyimpan komentar baru
     */
    public function store(Request $request, $article_id)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'comment_text' => 'required|string|max:1000',
                'parent_id' => 'nullable|exists:comments,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validasi artikel exists
            if (!Article::find($article_id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Article not found'
                ], 404);
            }

            // Validasi parent comment jika ada
            if ($request->parent_id) {
                $parentComment = Comment::find($request->parent_id);
                if (!$parentComment || $parentComment->article_id != $article_id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid parent comment'
                    ], 400);
                }
            }

            // Buat komentar
            $comment = Comment::create([
                'article_id' => $article_id,
                'user_id' => auth()->id(),
                'parent_id' => $request->parent_id,
                'comment_text' => $request->comment_text
            ]);

            // Load relasi
            $comment->load('user:id,name');

            return response()->json([
                'status' => 'success',
                'message' => $request->parent_id ? 'Reply created successfully' : 'Comment created successfully',
                'data' => $comment
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan komentar spesifik
     */
    public function show($comment_id)
    {
        try {
            $comment = Comment::with(['user', 'article', 'replies.user'])
                ->where('comment_id', $comment_id)
                ->first();

            // Jika comment tidak ditemukan, return data kosong
            if (!$comment) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No comment found',
                    'data' => null
                ]);
            }

            // Jika article sudah dihapus, set article ke null
            if (!$comment->article) {
                $comment->article = null;
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Comment retrieved successfully',
                'data' => $comment
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in show comment: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update komentar
     */
    public function update(Request $request, $comment_id)
    {
        try {
            $comment = Comment::findOrFail($comment_id);

            // Cek kepemilikan komentar
            if ($comment->user_id !== auth()->id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Validasi input
            $validator = Validator::make($request->all(), [
                'comment_text' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $comment->update([
                'comment_text' => $request->comment_text
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Comment updated successfully',
                'data' => $comment
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Comment not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hapus komentar
     */
    public function destroy($comment_id)
    {
        try {
            $comment = Comment::findOrFail($comment_id);

            // Cek kepemilikan komentar
            if ($comment->user_id !== auth()->id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Hapus semua balasan jika ini adalah komentar utama
            if (is_null($comment->parent_id)) {
                $comment->replies()->delete();
            }

            $comment->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Comment deleted successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Comment not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
