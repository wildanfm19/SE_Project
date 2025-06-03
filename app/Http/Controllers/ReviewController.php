<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Seller;
use App\Models\Product;

class ReviewController extends Controller
{
    public function store(Request $request, $orderId)
    {
        try {
            DB::beginTransaction();
            
            $user = Auth::user();
            
            // Validasi order dengan eager loading seller
            $order = Order::with(['orderItems.product', 'orderItems.review', 'seller'])
                ->where('user_id', $user->user_id)
                ->where('order_id', $orderId)
                ->first();

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found'
                ], 404);
            }

            // Cek apakah order sudah delivered
            if ($order->shipping_status !== 'delivered') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot review order that has not been delivered'
                ], 422);
            }

            // Validasi request
            $request->validate([
                'reviews' => 'required|array|min:1',
                'reviews.*.order_item_id' => 'required|exists:order_items,order_item_id',
                'reviews.*.product_id' => 'required|exists:products,product_id',
                'reviews.*.rating' => 'required|integer|min:1|max:5',
                'reviews.*.comment' => 'nullable|string|max:1000'
            ]);

            $reviews = [];
            $sellerId = $order->seller_id;
            
            foreach ($request->reviews as $reviewData) {
                // Validasi order item
                $orderItem = $order->orderItems->firstWhere('order_item_id', $reviewData['order_item_id']);
                
                if (!$orderItem) {
                    throw new \Exception('Invalid order item');
                }

                if ($orderItem->review) {
                    throw new \Exception('Product already reviewed');
                }

                // Buat review
                $review = Review::create([
                    'order_id' => $orderId,
                    'order_item_id' => $reviewData['order_item_id'],
                    'user_id' => $user->user_id,
                    'product_id' => $reviewData['product_id'],
                    'seller_id' => $sellerId,
                    'rating' => $reviewData['rating'],
                    'comment' => $reviewData['comment'] ?? null
                ]);

                $reviews[] = $review;

                // Update product rating
                Product::updateProductRating($reviewData['product_id']);
            }

            // Update seller rating
            if ($sellerId) {
                Review::updateSellerRating($sellerId);
            }

            DB::commit();

            // Load seller data yang fresh untuk response
            $seller = Seller::find($sellerId);

            return response()->json([
                'status' => 'success',
                'message' => 'Review submitted successfully',
                'data' => [
                    'reviews' => $reviews,
                    'seller' => $seller ? [
                        'seller_id' => $seller->seller_id,
                        'store_name' => $seller->store_name,
                        'store_rating' => $seller->store_rating
                    ] : null
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Review Creation Error:', [
                'order_id' => $orderId,
                'user_id' => Auth::id(),
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit review: ' . $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request, $productId = null)
    {
        try {
            $query = Review::with(['user.customer', 'product', 'orderItem'])
                         ->latest();

            if ($productId) {
                $query->where('product_id', $productId);
            }

            $reviews = $query->paginate(10);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'reviews' => $this->formatReviews($reviews),
                    'pagination' => [
                        'current_page' => $reviews->currentPage(),
                        'last_page' => $reviews->lastPage(),
                        'per_page' => $reviews->perPage(),
                        'total' => $reviews->total()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get Reviews Error:', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get reviews: ' . $e->getMessage()
            ], 500);
        }
    }

    private function formatReviews($reviews)
    {
        if ($reviews instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $reviews = $reviews->items();
        }
        
        if (!is_array($reviews) && !($reviews instanceof \Illuminate\Support\Collection)) {
            $reviews = [$reviews];
        }

        return collect($reviews)->map(function($review) {
            $product = $review->product;
            $mainImage = $product->galleries()
                                ->where('is_main', true)
                                ->first()?->image_url 
                        ?? $product->galleries()
                                ->first()?->image_url;

            return [
                'review_id' => $review->review_id,
                'order' => [
                    'order_id' => $review->order_id,
                    'order_item_id' => $review->order_item_id
                ],
                'seller' => [
                    'seller_id' => $review->seller_id,
                    'store_name' => $review->seller->store_name ?? 'Unknown Store',
                    'store_rating' => $review->seller->store_rating
                ],
                'product' => [
                    'product_id' => $product->product_id ?? null,
                    'name' => $product->product_name ?? 'Unknown Product',
                    'image' => $mainImage ? url('storage/' . $mainImage) : null,
                    'gallery_images' => $product->galleries->map(function($gallery) {
                        return url('storage/' . $gallery->image_url);
                    })
                ],
                'user' => [
                    'user_id' => $review->user->user_id ?? null,
                    'name' => $review->user->customer->full_name ?? 'Anonymous',
                ],
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at ? $review->created_at->format('Y-m-d H:i:s') : null
            ];
        })->toArray();
    }

    // Method untuk melihat review public di product
    public function publicReviews(Request $request, $productId)
    {
        try {
            $query = Review::with(['user.customer', 'product'])
                         ->where('product_id', $productId)
                         ->latest();

            // Filter rating jika ada
            if ($request->rating) {
                $query->where('rating', $request->rating);
            }

            $reviews = $query->paginate(10);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'product' => [
                        'product_id' => $productId,
                        'average_rating' => Review::where('product_id', $productId)->avg('rating'),
                        'total_reviews' => Review::where('product_id', $productId)->count(),
                    ],
                    'reviews' => $this->formatReviews($reviews),
                    'pagination' => [
                        'current_page' => $reviews->currentPage(),
                        'last_page' => $reviews->lastPage(),
                        'per_page' => $reviews->perPage(),
                        'total' => $reviews->total()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get Public Reviews Error:', [
                'product_id' => $productId,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get reviews'
            ], 500);
        }
    }

    // Method untuk melihat review history user
    public function userReviews(Request $request)
    {
        try {
            $user = Auth::user();
            
            $reviews = Review::with([
                    'product.galleries',
                    'order',
                    'orderItem',
                    'user.customer'
                ])
                ->where('user_id', $user->user_id)
                ->latest()
                ->paginate(10);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'reviews' => $this->formatReviews($reviews),
                    'pagination' => [
                        'current_page' => $reviews->currentPage(),
                        'last_page' => $reviews->lastPage(),
                        'per_page' => $reviews->perPage(),
                        'total' => $reviews->total()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get user reviews'
            ], 500);
        }
    }

    // Method untuk melihat order yang bisa direview
    public function reviewableOrders(Request $request)
    {
        try {
            $user = Auth::user();
            
            $orders = Order::with(['orderItems.product.galleries', 'orderItems.review'])
                ->where('user_id', $user->user_id)
                ->where('shipping_status', 'delivered')
                ->whereHas('orderItems', function($query) {
                    // Filter order items yang:
                    $query->whereDoesntHave('review')  // belum direview
                          ->whereHas('product', function($q) {  // dan productnya active
                              $q->where('is_active', true);
                          }); 
                })
                ->latest()
                ->get()
                ->map(function($order) {
                    return [
                        'order_id' => $order->order_id,
                        'order_date' => $order->created_at->format('Y-m-d H:i:s'),
                        'total_items' => $order->orderItems->count(),
                        'reviewable_items' => $order->orderItems
                            ->filter(function($item) {
                                // Filter hanya item yang:
                                return !$item->review &&           // belum direview
                                       $item->product &&           // product exists
                                       $item->product->is_active;  // product active
                            })
                            ->map(function($item) {
                                $mainImage = optional($item->product)->galleries()
                                    ->where('is_main', true)
                                    ->first()?->image_url 
                                    ?? optional($item->product)->galleries()
                                    ->first()?->image_url;

                                return [
                                    'order_item_id' => $item->order_item_id,
                                    'product_id' => $item->product_id,
                                    'product_name' => $item->product->product_name,
                                    'product_image' => $mainImage ? url('storage/' . $mainImage) : null,
                                    'quantity' => $item->quantity,
                                    'price' => $item->price,
                                    'product_status' => [
                                        'is_active' => $item->product->is_active,
                                        'can_review' => true
                                    ]
                                ];
                            })
                            ->values()
                    ];
                })
                ->filter(function($order) {
                    return count($order['reviewable_items']) > 0;
                })
                ->values();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'orders' => $orders,
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => count($orders),
                        'total' => count($orders)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Reviewable orders error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get reviewable orders'
            ], 500);
        }
    }
}


