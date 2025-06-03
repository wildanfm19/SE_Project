<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WishlistController extends Controller
{
    /**
     * Menampilkan wishlist user
     */
    public function index()
    {
        try {
            // Validasi user terautentikasi
            if (!Auth::check()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access'
                ], 401);
            }

            $wishlists = Wishlist::with('product')
                ->where('user_id', Auth::id())
                ->get()
                ->map(function($wishlist) {
                    return [
                        'wishlist_id' => $wishlist->wishlist_id,
                        'product' => [
                            'product_id' => $wishlist->product->product_id,
                            'category_id' => $wishlist->product->category_id,
                            'seller_id' => $wishlist->product->seller_id,
                            'product_name' => $wishlist->product->product_name,
                            'description' => $wishlist->product->description,
                            'price' => $wishlist->product->price,
                            'stock_quantity' => $wishlist->product->stock_quantity,
                            'is_active' => $wishlist->product->is_active
                        ],
                        'created_at' => $wishlist->created_at,
                        'updated_at' => $wishlist->updated_at
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $wishlists
            ]);

        } catch (\Exception $e) {
            Log::error('Wishlist index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch wishlist'
            ], 500);
        }
    }

    /**
     * Menambah produk ke wishlist
     */
    public function add(Request $request)
    {
        try {
            // Validasi input
            $validated = $request->validate([
                'product_id' => 'required|integer|exists:products,product_id'
            ], [
                'product_id.required' => 'Product ID is required',
                'product_id.integer' => 'Product ID must be a number',
                'product_id.exists' => 'Product not found'
            ]);

            DB::beginTransaction();

            // Validasi produk exists dan active
            $product = Product::find($validated['product_id']);
            if (!$product || !$product->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product is not available'
                ], 422);
            }

            // Cek limit wishlist (opsional)
            $wishlistCount = Wishlist::where('user_id', Auth::id())->count();
            if ($wishlistCount >= 100) { // Contoh limit 100 item
                return response()->json([
                    'status' => 'error',
                    'message' => 'Wishlist limit reached (maximum 100 items)'
                ], 422);
            }

            // Cek apakah sudah ada di wishlist
            $exists = Wishlist::where('user_id', Auth::id())
                ->where('product_id', $validated['product_id'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product already in wishlist'
                ], 422);
            }

            // Tambah ke wishlist
            $wishlist = Wishlist::create([
                'user_id' => Auth::id(),
                'product_id' => $validated['product_id']
            ]);

            DB::commit();

            // Load relasi product untuk response
            $wishlist->load('product');

            return response()->json([
                'status' => 'success',
                'message' => 'Product added to wishlist successfully',
                'data' => [
                    'wishlist_id' => $wishlist->wishlist_id,
                    'product' => [
                        'product_id' => $wishlist->product->product_id,
                        'category_id' => $wishlist->product->category_id,
                        'seller_id' => $wishlist->product->seller_id,
                        'product_name' => $wishlist->product->product_name,
                        'description' => $wishlist->product->description,
                        'price' => $wishlist->product->price,
                        'stock_quantity' => $wishlist->product->stock_quantity,
                        'is_active' => $wishlist->product->is_active
                    ]
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Add to wishlist error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add product to wishlist'
            ], 500);
        }
    }

    /**
     * Hapus produk dari wishlist
     */
    public function remove($wishlist_id)
    {
        try {
            // Validasi wishlist_id
            if (!is_numeric($wishlist_id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid wishlist ID'
                ], 422);
            }

            DB::beginTransaction();

            // Cek kepemilikan wishlist
            $wishlist = Wishlist::where('user_id', Auth::id())
                ->where('wishlist_id', $wishlist_id)
                ->first();

            if (!$wishlist) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Wishlist item not found or unauthorized'
                ], 404);
            }

            $wishlist->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Product removed from wishlist successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Remove from wishlist error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove product from wishlist'
            ], 500);
        }
    }

    /**
     * Cek status produk di wishlist
     */
    public function checkStatus($product_id)
    {
        try {
            // Validasi product_id
            if (!is_numeric($product_id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid product ID'
                ], 422);
            }

            $exists = Wishlist::where('user_id', Auth::id())
                ->where('product_id', $product_id)
                ->exists();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'in_wishlist' => $exists
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Check wishlist status error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check wishlist status'
            ], 500);
        }
    }
} 