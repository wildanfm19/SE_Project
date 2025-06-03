<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    /**
     * Menampilkan cart user yang sedang login
     */
    public function index()
    {
        try {
            $cart = Cart::with(['items.product' => function($query) {
                    // Include soft deleted products
                    $query->withTrashed();
                }, 'items.product.mainImage', 'user'])
                ->where('user_id', Auth::id())
                ->firstOrCreate(['user_id' => Auth::id()]);

            // Filter dan format items
            $validItems = $cart->items->filter(function($item) {
                return $item->product && $item->product->is_active && !$item->product->trashed();
            });

            // Hapus items yang tidak valid dari database
            $invalidItems = $cart->items->filter(function($item) {
                return !$item->product || 
                       !$item->product->is_active || 
                       $item->product->trashed() ||
                       $item->product->stock_quantity <= 0; // Tambah pengecekan stok
            });
            
            if ($invalidItems->isNotEmpty()) {
                foreach ($invalidItems as $item) {
                    $item->delete();
                }
            }

            $cartSummary = [
                'total_items' => $validItems->sum('quantity'),
                'subtotal' => $validItems->sum(function($item) {
                    return $item->price * $item->quantity;
                }),
                'items' => $validItems->map(function($item) {
                    return [
                        'cart_item_id' => $item->cart_item_id,
                        'product' => [
                            'product_id' => $item->product->product_id,
                            'name' => $item->product->product_name,
                            'price' => $item->price,
                            'image_url' => $item->product->mainImage->image_url ?? null,
                            'stock_quantity' => $item->product->stock_quantity, // Tambahkan info stok
                            'out_of_stock' => $item->product->stock_quantity <= 0 // Tambahkan flag out of stock
                        ],
                        'quantity' => $item->quantity,
                        'total_price' => $item->price * $item->quantity,
                        'stock_warning' => $item->quantity > $item->product->stock_quantity ? // Tambahkan warning stok
                            "Only {$item->product->stock_quantity} items available" : null
                    ];
                }),
                'removed_items' => $invalidItems->map(function($item) {
                    return [
                        'product_id' => $item->product_id,
                        'name' => $item->product ? $item->product->product_name : 'Product not available',
                        'reason' => !$item->product ? 'Product deleted' : 
                                   (!$item->product->is_active ? 'Product inactive' : 
                                   ($item->product->stock_quantity <= 0 ? 'Product out of stock' : 
                                   'Product not available'))
                    ];
                })
            ];

            // Tambahkan warning jika ada item yang melebihi stok
            $stockWarnings = $validItems->filter(function($item) {
                return $item->quantity > $item->product->stock_quantity;
            })->map(function($item) {
                return [
                    'product_name' => $item->product->product_name,
                    'requested_quantity' => $item->quantity,
                    'available_stock' => $item->product->stock_quantity
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $cartSummary,
                'stock_warnings' => $stockWarnings->isEmpty() ? null : $stockWarnings->values(),
                'message' => $this->generateCartMessage($invalidItems, $stockWarnings)
            ]);

        } catch (\Exception $e) {
            Log::error('Cart index error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch cart details'
            ], 500);
        }
    }

    /**
     * Generate cart message based on issues
     */
    private function generateCartMessage($invalidItems, $stockWarnings)
    {
        $messages = [];
        
        if ($invalidItems->isNotEmpty()) {
            $messages[] = 'Some items were removed from your cart because they are no longer available';
        }
        
        if ($stockWarnings->isNotEmpty()) {
            $messages[] = 'Some items in your cart exceed available stock';
        }
        
        return $messages ? implode('. ', $messages) : null;
    }

    /**
     * Menambahkan item ke cart
     */
    public function addItem(Request $request)
{
    try {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,product_id',
            'quantity' => 'required|integer|min:1|max:99'
        ]);

        DB::beginTransaction();

        $product = Product::findOrFail($validated['product_id']);

        // Cek stok produk
        if ($product->stock_quantity < $validated['quantity']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient stock. Available: ' . $product->stock_quantity
            ], 422);
        }

        $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);

        $cartItem = CartItem::where('cart_id', $cart->cart_id)
                          ->where('product_id', $product->product_id)
                          ->first();

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $validated['quantity'];
            
            // Cek lagi total quantity tidak melebihi stok
            if ($newQuantity > $product->stock_quantity) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Total quantity exceeds available stock'
                ], 422);
            }

            $cartItem->update([
                'quantity' => $newQuantity,
                'price' => $product->price // Update harga jika ada perubahan
            ]);
        } else {
            CartItem::create([
                'cart_id' => $cart->cart_id,
                'product_id' => $product->product_id,
                'quantity' => $validated['quantity'],
                'price' => $product->price
            ]);
        }

        // Hapus pengurangan stok karena masih dalam cart
        // $product->decrement('stock_quantity', $validated['quantity']);

        DB::commit();

        // Reload cart dengan relasi
        $cart = Cart::with(['items.product'])
                   ->find($cart->cart_id);

        return response()->json([
            'status' => 'success',
            'message' => 'Product added to cart successfully',
            'data' => $cart
        ]);

    } catch (ValidationException $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Validation error',
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Add to cart error: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to add item to cart'
        ], 500);
    }
}

    /**
     * Update quantity item di cart
     */
    public function updateItem(Request $request, $cart_item_id)
    {
        try {
            $validated = $request->validate([
                'quantity' => 'required|integer|min:1|max:99'
            ]);

            DB::beginTransaction();

            $cartItem = CartItem::with('product')
                              ->findOrFail($cart_item_id);

            // Cek kepemilikan cart
            if ($cartItem->cart->user_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access'
                ], 403);
            }

            // Cek stok
            if ($cartItem->product->stock_quantity < $validated['quantity']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient stock. Available: ' . $cartItem->product->stock_quantity
                ], 422);
            }

            $cartItem->update([
                'quantity' => $validated['quantity'],
                'price' => $cartItem->product->price // Update harga jika ada perubahan
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Cart item updated successfully',
                'data' => $cartItem->cart->load('items.product')
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update cart item error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update cart item'
            ], 500);
        }
    }

    /**
     * Hapus item dari cart
     */
    public function removeItem($cart_item_id)
    {
        try {
            DB::beginTransaction();

            $cartItem = CartItem::findOrFail($cart_item_id);

            if ($cartItem->cart->user_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $cartItem->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Item removed from cart successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Remove cart item error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove item from cart'
            ], 500);
        }
    }

    /**
     * Kosongkan cart
     */
    public function clear()
    {
        try {
            DB::beginTransaction();

            $cart = Cart::where('user_id', Auth::id())->first();
            
            if ($cart) {
                $cart->items()->delete();
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Cart cleared successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Clear cart error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clear cart'
            ], 500);
        }
    }

    /**
     * Mendapatkan jumlah item di cart (untuk badge/notifikasi)
     */
    public function getItemCount()
    {
        try {
            $cart = Cart::where('user_id', Auth::id())->first();
            
            $itemCount = $cart ? $cart->items->sum('quantity') : 0;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'item_count' => $itemCount
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get cart item count error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get cart item count'
            ], 500);
        }
    }

    /**
     * Cek ketersediaan stok untuk item di cart
     */
    public function checkStock()
    {
        try {
            $cart = Cart::with(['items.product'])
                       ->where('user_id', Auth::id())
                       ->first();

            if (!$cart) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'is_valid' => true,
                        'messages' => []
                    ]
                ]);
            }

            $stockIssues = [];
            $isValid = true;

            foreach ($cart->items as $item) {
                if ($item->quantity > $item->product->stock_quantity) {
                    $isValid = false;
                    $stockIssues[] = [
                        'product_name' => $item->product->product_name,
                        'requested_quantity' => $item->quantity,
                        'available_stock' => $item->product->stock_quantity
                    ];
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'is_valid' => $isValid,
                    'stock_issues' => $stockIssues
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Check cart stock error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check cart stock'
            ], 500);
        }
    }

    /**
     * Menambahkan item dari wishlist ke cart
     */
    public function addFromWishlist(Request $request)
    {
        try {
            $validated = $request->validate([
                'wishlist_id' => 'required|exists:wishlists,wishlist_id',
                'quantity' => 'required|integer|min:1|max:99'
            ]);

            DB::beginTransaction();

            $wishlistItem = Wishlist::with('product')
                ->where('user_id', Auth::id())
                ->where('wishlist_id', $validated['wishlist_id'])
                ->firstOrFail();

            // Cek stok produk
            if ($wishlistItem->product->stock_quantity < $validated['quantity']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient stock. Available: ' . $wishlistItem->product->stock_quantity
                ], 422);
            }

            $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);

            $cartItem = CartItem::where('cart_id', $cart->cart_id)
                              ->where('product_id', $wishlistItem->product_id)
                              ->first();

            if ($cartItem) {
                $newQuantity = $cartItem->quantity + $validated['quantity'];
                
                if ($newQuantity > $wishlistItem->product->stock_quantity) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Total quantity exceeds available stock'
                    ], 422);
                }

                $cartItem->update([
                    'quantity' => $newQuantity,
                    'price' => $wishlistItem->product->price
                ]);
            } else {
                CartItem::create([
                    'cart_id' => $cart->cart_id,
                    'product_id' => $wishlistItem->product_id,
                    'quantity' => $validated['quantity'],
                    'price' => $wishlistItem->product->price
                ]);
            }

            // Hapus dari wishlist
            $wishlistItem->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Product moved from wishlist to cart successfully',
                'data' => $cart->load('items.product')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Add from wishlist error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to move product from wishlist to cart'
            ], 500);
        }
    }

    /**
     * Menambahkan multiple items dari wishlist ke cart
     */
    public function addMultipleFromWishlist(Request $request)
    {
        try {
            $request->validate([
                'items' => 'required|array|min:1',
                'items.*.wishlist_id' => 'required|exists:wishlists,wishlist_id',
                'items.*.quantity' => 'required|integer|min:1|max:99'
            ], [
                'items.required' => 'Items are required',
                'items.array' => 'Items must be an array',
                'items.min' => 'At least one item is required',
                'items.*.wishlist_id.required' => 'Wishlist ID is required for each item',
                'items.*.wishlist_id.exists' => 'One or more wishlist items not found',
                'items.*.quantity.required' => 'Quantity is required for each item',
                'items.*.quantity.integer' => 'Quantity must be a number',
                'items.*.quantity.min' => 'Quantity must be at least 1',
                'items.*.quantity.max' => 'Quantity cannot exceed 99'
            ]);

            DB::beginTransaction();

            $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);
            $results = [];

            foreach ($request->items as $item) {
                // Ambil wishlist item dengan product
                $wishlistItem = Wishlist::with('product')
                    ->where('user_id', Auth::id())
                    ->where('wishlist_id', $item['wishlist_id'])
                    ->first();

                if (!$wishlistItem) {
                    $results[] = [
                        'wishlist_id' => $item['wishlist_id'],
                        'status' => 'error',
                        'message' => 'Wishlist item not found or unauthorized'
                    ];
                    continue;
                }

                // Cek stok produk
                if ($wishlistItem->product->stock_quantity < $item['quantity']) {
                    $results[] = [
                        'wishlist_id' => $item['wishlist_id'],
                        'status' => 'error',
                        'message' => "Insufficient stock for {$wishlistItem->product->product_name}. Available: {$wishlistItem->product->stock_quantity}"
                    ];
                    continue;
                }

                // Cek apakah produk sudah ada di cart
                $cartItem = CartItem::where('cart_id', $cart->cart_id)
                                  ->where('product_id', $wishlistItem->product_id)
                                  ->first();

                if ($cartItem) {
                    // Update quantity jika sudah ada
                    $newQuantity = $cartItem->quantity + $item['quantity'];
                    
                    if ($newQuantity > $wishlistItem->product->stock_quantity) {
                        $results[] = [
                            'wishlist_id' => $item['wishlist_id'],
                            'status' => 'error',
                            'message' => "Total quantity exceeds available stock for {$wishlistItem->product->product_name}"
                        ];
                        continue;
                    }

                    $cartItem->update([
                        'quantity' => $newQuantity,
                        'price' => $wishlistItem->product->price
                    ]);
                } else {
                    // Buat item baru di cart
                    CartItem::create([
                        'cart_id' => $cart->cart_id,
                        'product_id' => $wishlistItem->product_id,
                        'quantity' => $item['quantity'],
                        'price' => $wishlistItem->product->price
                    ]);
                }

                // Hapus dari wishlist
                $wishlistItem->delete();

                $results[] = [
                    'wishlist_id' => $item['wishlist_id'],
                    'status' => 'success',
                    'message' => "Product {$wishlistItem->product->product_name} moved to cart"
                ];
            }

            DB::commit();

            // Load cart dengan items untuk response
            $cart->load('items.product');

            return response()->json([
                'status' => 'success',
                'message' => 'Products processed',
                'data' => [
                    'results' => $results,
                    'cart' => [
                        'total_items' => $cart->total_items,
                        'subtotal' => $cart->total,
                        'items' => $cart->items->map(function($item) {
                            return [
                                'cart_item_id' => $item->cart_item_id,
                                'product' => [
                                    'product_id' => $item->product->product_id,
                                    'category_id' => $item->product->category_id,
                                    'seller_id' => $item->product->seller_id,
                                    'product_name' => $item->product->product_name,
                                    'description' => $item->product->description,
                                    'price' => $item->product->price,
                                    'stock_quantity' => $item->product->stock_quantity,
                                    'is_active' => $item->product->is_active
                                ],
                                'quantity' => $item->quantity,
                                'total_price' => $item->total_price
                            ];
                        })
                    ]
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Add multiple from wishlist error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process wishlist items'
            ], 500);
        }
    }

    /**
     * Mengurangi quantity item di cart
     */
    public function decrementItem(Request $request, $cart_item_id)
    {
        try {
            DB::beginTransaction();

            $cartItem = CartItem::with('product')
                              ->findOrFail($cart_item_id);

            // Cek kepemilikan cart
            if ($cartItem->cart->user_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access'
                ], 403);
            }

            // Jika quantity = 1, hapus item
            if ($cartItem->quantity <= 1) {
                $cartItem->delete();
                DB::commit();
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Item removed from cart',
                    'data' => $cartItem->cart->load('items.product')
                ]);
            }

            // Kurangi quantity
            $cartItem->update([
                'quantity' => $cartItem->quantity - 1,
                'price' => $cartItem->product->price // Update harga jika ada perubahan
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Cart item quantity decreased',
                'data' => $cartItem->cart->load('items.product')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Decrement cart item error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to decrease item quantity'
            ], 500);
        }
    }
} 