<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\GalleryProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use App\Models\Seller;

class GalleryProductController extends Controller
{
    /**
     * Get product gallery
     * GET /products/{id}/gallery
     */
    public function index($productId)
    {
        try {
            $product = Product::with(['mainImage', 'images'])
                            ->active()
                            ->findOrFail($productId);

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'data' => [
                    'product_id' => $product->product_id,
                    'product_name' => $product->product_name,
                    'main_image' => $product->mainImage,
                    'gallery' => $product->images->where('is_main', false)
                ]
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'code' => '404',
                'status' => 'error',
                'message' => 'Product not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to fetch gallery',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload additional images
     * POST /seller/gallery/upload/{productId}
     */
    public function store(Request $request, $productId)
    {
        try {
            Log::info('Current seller ID: ' . auth()->user()->seller->seller_id);
            Log::info('Trying to find product with ID: ' . $productId);
            Log::info('User is authenticated: ' . (auth()->check() ? 'Yes' : 'No'));

            // Ambil seller_id dengan benar
            $sellerId = auth()->user()->seller->seller_id; // Pastikan ini mengarah ke seller yang benar

            // Validate product ownership
            $product = Product::where('seller_id', $sellerId)->findOrFail($productId);

            // Validasi request
            $validator = Validator::make($request->all(), [
                'image' => 'required|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => '422',
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Cek apakah ada file yang diupload
            if (!$request->hasFile('image')) {
                return response()->json([
                    'code' => '422',
                    'status' => 'error',
                    'message' => 'No image file uploaded'
                ], 422);
            }

            // Upload dan simpan gambar
            $image = $request->file('image');
            $path = $image->store('products/' . $productId . '/gallery', 'public');

            // Cek apakah ini gambar pertama
            $isFirstImage = !GalleryProduct::where('product_id', $productId)->exists();

            // Simpan ke database
            $gallery = GalleryProduct::create([
                'product_id' => $productId,
                'seller_id' => $sellerId,
                'image_url' => Storage::url($path),
                'is_main' => $isFirstImage // Otomatis jadi main image jika pertama
            ]);

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'message' => 'Image uploaded successfully',
                'data' => $gallery
            ]);

        } catch (ModelNotFoundException $e) {
            Log::error('Product not found: ' . $e->getMessage());
            return response()->json([
                'code' => '404',
                'status' => 'error',
                'message' => 'Product not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error: ' . $e->getMessage());
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'An error occurred',
            ], 500);
        }
    }

    /**
     * Set image as main
     * PUT /seller/gallery/{id}/main
     */
    public function setAsMain($id)
    {
        try {
            $gallery = GalleryProduct::where('gallery_id', $id)
                                   ->where('seller_id', Auth::user()->seller->seller_id)
                                   ->firstOrFail();

            DB::beginTransaction();

            // Reset semua gambar produk menjadi non-main
            GalleryProduct::where('product_id', $gallery->product_id)
                         ->update(['is_main' => false]);

            // Set gambar yang dipilih sebagai main
            $gallery->update(['is_main' => true]);

            DB::commit();

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'message' => 'Image set as main successfully',
                'data' => $gallery->fresh()
            ]);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'code' => '404',
                'status' => 'error',
                'message' => 'Gallery image not found',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to set main image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete image
     * DELETE /seller/gallery/{id}
     */
    public function destroy($id)
    {
        try {
            $gallery = GalleryProduct::where('gallery_id', $id)
                                   ->where('seller_id', Auth::user()->seller->seller_id)
                                   ->firstOrFail();

            if ($gallery->is_main) {
                return response()->json([
                    'code' => '422',
                    'status' => 'error',
                    'message' => 'Cannot delete main image. Set another image as main first.'
                ], 422);
            }

            // Hapus file
            $path = str_replace('/storage/', '', $gallery->image_url);
            Storage::disk('public')->delete($path);

            // Hapus record
            $gallery->delete();

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'message' => 'Image deleted successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'code' => '404',
                'status' => 'error',
                'message' => 'Gallery image not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to delete image',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
