<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\GalleryProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductController extends Controller
{
    /**
     * Display listing of active products
     * GET /products
     */
    public function index(Request $request)
    {
        try {
            $query = Product::with(['category', 'mainImage'])
                           ->active();

            // Filter
            if ($request->search) {
                $query->where('product_name', 'like', "%{$request->search}%");
            }

            if ($request->category_id) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->min_price) {
                $query->where('price', '>=', $request->min_price);
            }

            if ($request->max_price) {
                $query->where('price', '<=', $request->max_price);
            }

            if ($request->min_rating) {
                $query->where('average_rating', '>=', $request->min_rating);
            }

            if ($request->in_stock) {
                $query->inStock();
            }

            // Sorting
            switch ($request->sort) {
                case 'price_low':
                    $query->orderBy('price', 'asc');
                    break;
                case 'price_high':
                    $query->orderBy('price', 'desc');
                    break;
                case 'rating_high':
                    $query->orderBy('average_rating', 'desc');
                    break;
                case 'best_seller':
                    $query->orderBy('total_sales', 'desc');
                    break;
                case 'oldest':
                    $query->oldest();
                    break;
                default:
                    $query->latest();
                    break;
            }

            $products = $query->paginate($request->per_page ?? 12);

            // Add out_of_stock indicator
            $products->getCollection()->transform(function ($product) {
                $product->out_of_stock = $product->stock_quantity <= 0;
                return $product;
            });

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'data' => $products
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to fetch products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display product detail
     * GET /products/{id}
     */
    public function show($id)
    {
        try {
            $product = Product::with(['category', 'seller', 'mainImage', 'additionalImages', 'reviews'])
                            ->active()
                            ->findOrFail($id);

            $ratingBreakdown = [
                5 => $product->reviews()->where('rating', 5)->count(),
                4 => $product->reviews()->where('rating', 4)->count(),
                3 => $product->reviews()->where('rating', 3)->count(),
                2 => $product->reviews()->where('rating', 2)->count(),
                1 => $product->reviews()->where('rating', 1)->count(),
            ];

            $product->rating_breakdown = $ratingBreakdown;
            $product->out_of_stock = $product->stock_quantity <= 0;

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'data' => $product
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to fetch product detail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display seller's products
     * GET /seller/products
     */
    public function sellerIndex(Request $request)
    {
        try {
            $query = Product::with(['category', 'mainImage'])
                           ->where('seller_id', Auth::user()->seller->seller_id)
                           ->withTrashed();

            // Filter status
            if ($request->status === 'active') {
                $query->whereNull('deleted_at')->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where(function($q) {
                    $q->whereNotNull('deleted_at')
                      ->orWhere('is_active', false);
                });
            }

            $products = $query->latest()
                            ->paginate($request->per_page ?? 12);

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'data' => $products
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to fetch seller products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store new product
     * POST /seller/products
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'category_id' => 'required|exists:categories,category_id',
                'product_name' => 'required|string|max:255',
                'description' => 'required|string',
                'price' => 'required|numeric|min:0',
                'stock_quantity' => 'required|integer|min:0',
                'main_image' => 'required|image|mimes:jpeg,png,jpg|max:5120'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => '422',
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Create product
            $product = Product::create([
                'category_id' => $request->category_id,
                'seller_id' => Auth::user()->seller->seller_id,
                'product_name' => $request->product_name,
                'description' => $request->description,
                'price' => $request->price,
                'stock_quantity' => $request->stock_quantity,
                'is_active' => $request->stock_quantity > 0
            ]);

            // Upload main image
            $path = $request->file('main_image')
                           ->store("products/{$product->product_id}", 'public');

            // Create main image record
            GalleryProduct::create([
                'product_id' => $product->product_id,
                'seller_id' => Auth::user()->seller->seller_id,
                'image_url' => Storage::url($path),
                'is_main' => true,
                'uploaded_at' => now()
            ]);

            DB::commit();

            // Load relationships
            $product->load(['category', 'mainImage']);

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'message' => 'Product created successfully',
                'data' => $product
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            // Cleanup uploaded file if exists
            if (isset($path)) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to create product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update product
     * PUT /seller/products/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            // Cek kepemilikan produk
            $product = Product::where('product_id', $id)
                            ->where('seller_id', Auth::user()->seller->seller_id)
                            ->firstOrFail();

            // Validasi input
            $validator = Validator::make($request->all(), [
                'category_id' => 'sometimes|exists:categories,category_id',
                'product_name' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'price' => 'sometimes|numeric|min:0',
                'stock_quantity' => 'sometimes|integer|min:0',
                'main_image' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => '422',
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Update product data
            $product->update($request->only([
                'category_id',
                'product_name',
                'description',
                'price',
                'stock_quantity'
            ]));

            // Check if stock is 0, set is_active to false
            if ($product->stock_quantity == 0) {
                $product->update(['is_active' => false]);
            }

            // Jika ada upload gambar baru
            if ($request->hasFile('main_image')) {
                // Upload gambar baru
                $path = $request->file('main_image')
                               ->store("products/{$product->product_id}", 'public');

                // Cek gambar utama yang ada
                $currentMain = $product->mainImage;

                if ($currentMain) {
                    // Hapus file lama
                    $oldPath = str_replace('/storage/', '', $currentMain->image_url);
                    Storage::disk('public')->delete($oldPath);
                    
                    // Update record
                    $currentMain->update([
                        'image_url' => Storage::url($path)
                    ]);
                } else {
                    // Buat record gambar utama baru
                    GalleryProduct::create([
                        'product_id' => $product->product_id,
                        'seller_id' => Auth::user()->seller->seller_id,
                        'image_url' => Storage::url($path),
                        'is_main' => true,
                        'uploaded_at' => now()
                    ]);
                }
            }

            DB::commit();

            // Load relationships
            $product->load(['category', 'mainImage']);

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'message' => 'Product updated successfully',
                'data' => $product
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            // Cleanup uploaded file if exists
            if (isset($path)) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to update product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deactivate product
     * DELETE /seller/products/{id}
     */
    public function destroy($id)
    {
        try {
            $product = Product::where('product_id', $id)
                            ->where('seller_id', Auth::user()->seller->seller_id)
                            ->firstOrFail();

            $product->update(['is_active' => false]);
            $product->delete();

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'message' => 'Product deactivated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to deactivate product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore product
     * PUT /seller/products/{id}/restore
     */
    public function restore($id)
    {
        try {
            $product = Product::onlyTrashed()
                            ->where('product_id', $id)
                            ->where('seller_id', Auth::user()->seller->seller_id)
                            ->firstOrFail();

            $product->restore();
            $product->update(['is_active' => true]);

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'message' => 'Product restored successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to restore product',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

    /**
     * Admin: Get all products
     * GET /admin/products
     */
//     public function adminIndex(Request $request)
//     {
//         try {
//             if (!auth()->user() || auth()->user()->role !== 'admin') {
//                 return response()->json([
//                     'code' => '403',
//                     'status' => 'error',
//                     'message' => 'Unauthorized access'
//                 ], 403);
//             }

//             $query = Product::with(['category', 'seller', 'mainImage']);

//             // Filter
//             if ($request->seller_id) {
//                 $query->where('seller_id', $request->seller_id);
//             }

//             if ($request->status === 'active') {
//                 $query->active();
//             } elseif ($request->status === 'inactive') {
//                 $query->inactive();
//             }

//             if ($request->trashed) {
//                 $query->onlyTrashed();
//             }

//             $products = $query->latest()
//                             ->paginate($request->per_page ?? 20);

//             return response()->json([
//                 'code' => '000',
//                 'status' => 'success',
//                 'data' => $products
//             ]);

//         } catch (\Exception $e) {
//             return response()->json([
//                 'code' => '500',
//                 'status' => 'error',
//                 'message' => 'Failed to fetch products',
//                 'error' => $e->getMessage()
//             ], 500);
//         }
//     }

//     /**
//      * Admin: Force delete product
//      * DELETE /admin/products/{id}/force
//      */
//     public function forceDelete($id)
//     {
//         try {
//             if (!auth()->user() || auth()->user()->role !== 'admin') {
//                 return response()->json([
//                     'code' => '403',
//                     'status' => 'error',
//                     'message' => 'Unauthorized access'
//                 ], 403);
//             }

//             $product = Product::withTrashed()->findOrFail($id);

//             // Delete all images
//             foreach ($product->images as $image) {
//                 $path = str_replace('/storage/', '', $image->image_url);
//                 Storage::disk('public')->delete($path);
//             }

//             $product->forceDelete();

//             return response()->json([
//                 'code' => '000',
//                 'status' => 'success',
//                 'message' => 'Product permanently deleted'
//             ]);

//         } catch (\Exception $e) {
//             return response()->json([
//                 'code' => '500',
//                 'status' => 'error',
//                 'message' => 'Failed to delete product',
//                 'error' => $e->getMessage()
//             ], 500);
//         }
//     }
// }
