<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    /**
     * Display listing of categories
     */
    public function index(Request $request)
    {
        try {
            $query = Category::query();

            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'inactive') {
                $query->inactive();
            }

            $categories = $query->ordered()->get();

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store new category (seller only)
     */
    public function store(Request $request)
    {
        try {
            if (!auth()->user() || auth()->user()->role !== 'seller') {
                return response()->json([
                    'code' => '403',
                    'status' => 'error',
                    'message' => 'Unauthorized access'
                ], 403);
            }

            DB::beginTransaction();
            try {
                $validator = Validator::make($request->all(), [
                    'category_name' => 'required|string|max:255|unique:categories,category_name',
                    'description' => 'required|string',
                    'icon' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
                    'order' => 'nullable|integer|min:0'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'code' => '422',
                        'status' => 'error',
                        'message' => 'Validation failed',
                        'errors' => $validator->errors()
                    ], 422);
                }

                // Cek jika order sudah digunakan
                if ($request->filled('order')) {
                    // Geser semua kategori yang memiliki order >= requested order
                    Category::where('order', '>=', $request->order)
                        ->increment('order');
                } else {
                    // Jika order tidak diisi, letakkan di urutan terakhir
                    $maxOrder = Category::max('order');
                    $request->merge(['order' => $maxOrder + 1]);
                }

                $categoryData = [
                    'category_name' => $request->category_name,
                    'description' => $request->description,
                    'order' => $request->order,
                    'is_active' => true
                ];

                if ($request->hasFile('icon')) {
                    $path = $request->file('icon')->store('categories/icons', 'public');
                    $categoryData['icon'] = Storage::url($path);
                }

                $category = Category::create($categoryData);
                
                DB::commit();

                return response()->json([
                    'code' => '000',
                    'status' => 'success',
                    'message' => 'Category created successfully',
                    'data' => $category
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            if (isset($path)) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to create category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update category (seller only)
     */
    public function update(Request $request, $id)
    {
        try {
            if (!auth()->user() || auth()->user()->role !== 'seller') {
                return response()->json([
                    'code' => '403',
                    'status' => 'error',
                    'message' => 'Unauthorized access'
                ], 403);
            }

            DB::beginTransaction();
            try {
                $category = Category::findOrFail($id);
                $oldOrder = $category->order;
                
                if ($request->filled('order')) {
                    $newOrder = $request->order;
                    
                    if ($newOrder != $oldOrder) {
                        if ($newOrder > $oldOrder) {
                            // Geser ke bawah: semua yang di antara old dan new berkurang 1
                            Category::where('order', '>', $oldOrder)
                                ->where('order', '<=', $newOrder)
                                ->decrement('order');
                        } else {
                            // Geser ke atas: semua yang di antara new dan old bertambah 1
                            Category::where('order', '>=', $newOrder)
                                ->where('order', '<', $oldOrder)
                                ->increment('order');
                        }
                        
                        $category->order = $newOrder;
                        $category->save();
                    }
                }

                // Update data lainnya
                $updateData = [];
                if ($request->filled('category_name')) {
                    $updateData['category_name'] = $request->category_name;
                }
                if ($request->filled('description')) {
                    $updateData['description'] = $request->description;
                }
                if ($request->has('is_active')) {
                    $updateData['is_active'] = $request->boolean('is_active');
                }

                // Handle icon update
                if ($request->hasFile('icon')) {
                    if ($category->icon) {
                        $oldPath = str_replace('/storage/', '', $category->icon);
                        Storage::disk('public')->delete($oldPath);
                    }
                    
                    $path = $request->file('icon')->store('categories/icons', 'public');
                    $updateData['icon'] = Storage::url($path);
                }

                $category->update($updateData);
                
                DB::commit();

                return response()->json([
                    'code' => '000',
                    'status' => 'success',
                    'message' => 'Category updated successfully',
                    'data' => $category->fresh()
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            if (isset($path)) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to update category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get products by category
     */
    public function getProducts($id, Request $request)
    {
        try {
            $category = Category::findOrFail($id);

            if (!$category->is_active) {
                return response()->json([
                    'code' => '404',
                    'status' => 'error',
                    'message' => 'Category not found'
                ], 404);
            }

            $query = $category->products()
                            ->with(['images', 'seller'])
                            ->active();

            if ($request->search) {
                $query->where('product_name', 'like', "%{$request->search}%");
            }

            if ($request->min_price) {
                $query->where('price', '>=', $request->min_price);
            }

            if ($request->max_price) {
                $query->where('price', '<=', $request->max_price);
            }

            switch ($request->sort) {
                case 'price_low':
                    $query->orderBy('price', 'asc');
                    break;
                case 'price_high':
                    $query->orderBy('price', 'desc');
                    break;
                case 'oldest':
                    $query->oldest();
                    break;
                default:
                    $query->latest();
                    break;
            }

            $products = $query->paginate($request->per_page ?? 12);

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'data' => [
                    'category' => $category,
                    'products' => $products
                ]
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
     * seller: Get all categories
     */
    public function sellerIndex()
    {
        try {
            if (!auth()->user() || auth()->user()->role !== 'seller') {
                return response()->json([
                    'code' => '403',
                    'status' => 'error',
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $categories = Category::ordered()
                                ->when(request('status'), function($query) {
                                    return $query->where('is_active', request('status') === 'active');
                                })
                                ->get();

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deactivate category (seller only)
     */
    public function destroy($id)
    {
        try {
            if (!auth()->user() || auth()->user()->role !== 'seller') {
                return response()->json([
                    'code' => '403',
                    'status' => 'error',
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $category = Category::findOrFail($id);
            
            if ($category->products()->active()->exists()) {
                return response()->json([
                    'code' => '400',
                    'status' => 'error',
                    'message' => 'Cannot deactivate category with active products'
                ], 400);
            }

            $category->update(['is_active' => false]);

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'message' => 'Category has been deactivated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to deactivate category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activate category (seller only)
     */
    public function restore($id)
    {
        try {
            if (!auth()->user() || auth()->user()->role !== 'seller') {
                return response()->json([
                    'code' => '403',
                    'status' => 'error',
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $category = Category::findOrFail($id);
            $category->update(['is_active' => true]);

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'message' => 'Category has been activated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to activate category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder all categories to fix any gaps or duplicates
     */
    public function reorderCategories()
    {
        try {
            DB::beginTransaction();
            
            // Get all categories ordered by current order
            $categories = Category::orderBy('order')->get();
            
            // Reassign order values sequentially
            foreach ($categories as $index => $category) {
                $category->update(['order' => $index + 1]);
            }
            
            DB::commit();
            
            return response()->json([
                'code' => '000',
                'status' => 'success',
                'message' => 'Categories reordered successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to reorder categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
