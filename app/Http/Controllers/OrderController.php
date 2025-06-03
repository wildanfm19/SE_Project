<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\PaymentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;
use App\Exports\OrdersExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Review;

class OrderController extends Controller
{
    public function __construct()
    {
        // Konfigurasi Midtrans
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    private function formatOrders($orders)
    {
        return $orders->map(function($order) {
            return [
                'order_id' => $order->order_id,
                'seller' => [
                    'seller_id' => $order->seller_id,
                    'store_name' => $order->seller->store_name ?? 'Unknown Store',
                    'store_rating' => $order->seller->store_rating ?? '-',
                ],
                'customer' => [
                    'user_id' => $order->user->user_id ?? null,
                    'username' => $order->user->username ?? null,
                    'full_name' => $order->user->customer->full_name ?? 'N/A',
                    'email' => $order->user->customer->email ?? '',
                    'phone_number' => $order->user->customer->phone_number ?? ''
                ],
                'items' => $order->orderItems->map(function($item) {
                    $product = $item->product;

                    // Get product status
                    $productStatus = !$product ? 'deleted' : 
                        (!$product->is_active ? 'inactive' : 'active');

                    // Get main image or first available image
                    $mainImage = null;
                    if ($product) {
                        $mainImage = $product->galleries()
                            ->where('is_main', true)
                            ->first()?->image_url 
                            ?? $product->galleries()
                            ->first()?->image_url;
                    }

                    return [
                        'product_id' => $item->product_id,
                        'product_name' => $product?->product_name ?? 'Unknown Product',
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'subtotal' => $item->subtotal,
                        'image_url' => $mainImage ? url('storage/' . $mainImage) : null,
                        'product_status' => $productStatus,
                        'is_active' => $product?->is_active ?? false
                    ];
                }),
                'shipping_address' => $order->address ? [
                    'address' => $order->address->address ?? '',
                    'district_id' => $order->address->district_id ?? '',
                    'poscode_id' => $order->address->poscode_id ?? ''
                ] : null,
                'total_amount' => $order->total_amount,
                'status' => $order->status,
                'shipping_status' => $order->shipping_status,
                'payment_type' => $order->payment_type,
                'transaction_id' => $order->transaction_id,
                'snap_token' => $order->snap_token,
                'payment_url' => $order->payment_url ?? null,
                'created_at' => $order->created_at ? (is_string($order->created_at) ? $order->created_at : $order->created_at->format('Y-m-d H:i:s')) : null,
                'updated_at' => $order->updated_at ? (is_string($order->updated_at) ? $order->updated_at : $order->updated_at->format('Y-m-d H:i:s')) : null
            ];
        });
    }

    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            Log::info('User accessing orders:', [
                'user_id' => $user->user_id,
                'role' => $user->role
            ]);

            $query = Order::with([
                'orderItems.product' => function($query) {
                    // Include inactive products
                    $query->withoutGlobalScopes()->withTrashed();
                },
                'orderItems.product.galleries',
                'address', 
                'user.customer',
                'seller'
            ])->latest();
            
            // Jika seller, bisa lihat semua order + filter
            if ($user->role === 'seller') {
                $query->where('seller_id', $user->seller->seller_id);
                
                // Filter by status
                if ($request->status) {
                    $query->where('status', $request->status);
                }
                
                // Filter by shipping_status
                if ($request->shipping_status) {
                    $query->where('shipping_status', $request->shipping_status);
                }

                // Filter by date range
                if ($request->date_from) {
                    $query->whereDate('created_at', '>=', $request->date_from);
                }
                if ($request->date_to) {
                    $query->whereDate('created_at', '<=', $request->date_to);
                }

                // Filter by customer name/email
                if ($request->search) {
                    $query->whereHas('user.customer', function($q) use ($request) {
                        $q->where('full_name', 'like', '%' . $request->search . '%')
                          ->orWhere('email', 'like', '%' . $request->search . '%');
                    });
                }

                // Filter by order_id
                if ($request->order_id) {
                    $query->where('order_id', $request->order_id);
                }

            } else {
                $query->where('user_id', $user->user_id);
            }

            $orders = $query->paginate(10);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'orders' => $this->formatOrders($orders),
                    'pagination' => [
                        'current_page' => $orders->currentPage(),
                        'last_page' => $orders->lastPage(),
                        'per_page' => $orders->perPage(),
                        'total' => $orders->total()
                    ],
                    'filter_options' => $user->role === 'seller' ? [
                        'status_options' => ['pending', 'success', 'failed', 'expired', 'cancel', 'challenge'],
                        'shipping_status_options' => ['processing', 'shipped', 'delivered', 'cancelled']
                    ] : null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get Orders Error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get orders: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'address_id' => 'required|exists:addresses,address_id',
        ]);

        DB::beginTransaction();
        try {
            // Ambil cart dan items
            $cart = Cart::where('user_id', Auth::id())
                ->with(['items.product'])  // Eager load items dan products
                ->first();
            
            if (!$cart || $cart->items->isEmpty()) {
                return response()->json([
                    'code' => '400',
                    'status' => 'error',
                    'message' => 'Cart is empty'
                ], 400);
            }

            // Validasi products
            foreach ($cart->items as $item) {
                if (!$item->product) {
                    return response()->json([
                        'code' => '400',
                        'status' => 'error',
                        'message' => 'Some products in your cart no longer exist'
                    ], 400);
                }
            }

            // Ambil seller_id dari product pertama
            $firstItem = $cart->items->first();
            $sellerId = $firstItem->product->seller_id;

            // Validasi semua product dari seller yang sama
            $differentSeller = $cart->items->contains(function($item) use ($sellerId) {
                return $item->product->seller_id !== $sellerId;
            });

            if ($differentSeller) {
                return response()->json([
                    'code' => '400',
                    'status' => 'error',
                    'message' => 'All products must be from the same seller'
                ], 400);
            }

            // Calculate total
            $total = 0;
            $orderItems = [];

            foreach ($cart->items as $item) {
                $subtotal = $item->product->price * $item->quantity;
                $total += $subtotal;

                $orderItems[] = [
                    'product_id' => $item->product->product_id,
                    'product_name' => $item->product->product_name,
                    'quantity' => $item->quantity,
                    'price' => $item->product->price,
                    'subtotal' => $subtotal
                ];
            }

            // Create order dengan seller_id
            $order = Order::create([
                'user_id' => Auth::id(),
                'seller_id' => $sellerId,  // Tambahkan seller_id
                'address_id' => $request->address_id,
                'total_amount' => $total,
                'status' => 'pending'
            ]);

            // Create order items
            foreach ($orderItems as $item) {
                OrderItem::create([
                    'order_id' => $order->order_id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => $item['subtotal']
                ]);
            }

            // Generate Midtrans params
            $params = [
                'transaction_details' => [
                    'order_id' => (string) $order->order_id,
                    'gross_amount' => (int) $total,
                ],
                'customer_details' => [
                    'first_name' => strtok($order->user->customer->full_name, ' '),
                    'last_name' => trim(strstr($order->user->customer->full_name, ' ')),
                    'email' => $order->user->customer->email,
                    'phone' => $order->user->customer->phone_number,
                ],
                'item_details' => [

                ]
            ];

            // Tambahkan item details
            foreach ($cart->items as $item) {
                $params['item_details'][] = [
                    'id' => (string) $item->product->product_id,
                    'name' => (string) $item->product->product_name,
                    'price' => (int) $item->product->price,
                    'quantity' => (int) $item->quantity,
                    'brand' => 'Brand',
                    'category' => 'Category',
                ];
            }

            // Verifikasi total amount
            $itemTotal = array_sum(array_map(function($item) {
                return $item['price'] * $item['quantity'];
            }, $params['item_details']));

            // Pastikan total sama
            if ($itemTotal !== (int) $total) {
                throw new \Exception('Total amount mismatch with items total');
            }

            // Get snap token dan redirect URL
            $snapResponse = Snap::createTransaction($params);
            $order->update([
                'snap_token' => $snapResponse->token,
                'payment_url' => $snapResponse->redirect_url
            ]);

            // Clear cart
            CartItem::where('cart_id', $cart->cart_id)->delete();
            $cart->delete();

            DB::commit();

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'message' => 'Order created successfully',
                'data' => [
                    'order_id' => $order->order_id,
                    'total_amount' => $order->total_amount,
                    'snap_token' => $order->snap_token,
                    'payment_url' => $order->payment_url,
                    'status' => $order->status
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Order creation error: ' . $e->getMessage());
            \Log::error('Midtrans params: ' . json_encode($params ?? []));
            
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to create order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($order_id)
    {
        try {
            $user = Auth::user();
            
            $query = Order::with([
                'orderItems.product', 
                'address', 
                'user.customer'
            ]);

            if ($user->role === 'customer') {
                $query->where('user_id', $user->user_id);
            }

            $order = $query->findOrFail($order_id);

            $formattedOrder = [
                'order_id' => $order->order_id,
                'customer' => [
                    'user_id' => $order->user->user_id ?? null,
                    'username' => $order->user->username ?? null,
                    'full_name' => $order->user->customer->full_name ?? 'N/A',
                    'email' => $order->user->customer->email ?? '',
                    'phone_number' => $order->user->customer->phone_number ?? ''
                ],
                'items' => $order->orderItems->map(function($item) {
                    return [
                        'product_name' => $item->product->product_name ?? 'Unknown Product',
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'subtotal' => $item->subtotal
                    ];
                }),
                'shipping_address' => $order->address ? [
                    'address' => $order->address->address ?? '',
                    'district_id' => $order->address->district_id ?? '',
                    'poscode_id' => $order->address->poscode_id ?? ''
                ] : null,
                'total_amount' => $order->total_amount,
                'status' => $order->status,
                'shipping_status' => $order->shipping_status,
                'payment_type' => $order->payment_type,
                'transaction_id' => $order->transaction_id,
                'snap_token' => $order->snap_token,
                'paid_at' => $order->paid_at ? (is_string($order->paid_at) ? $order->paid_at : $order->paid_at->format('Y-m-d H:i:s')) : null,
                'created_at' => $order->created_at ? (is_string($order->created_at) ? $order->created_at : $order->created_at->format('Y-m-d H:i:s')) : null,
                'updated_at' => $order->updated_at ? (is_string($order->updated_at) ? $order->updated_at : $order->updated_at->format('Y-m-d H:i:s')) : null
            ];

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'message' => 'Order retrieved successfully',
                'data' => $formattedOrder
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching order:', [
                'order_id' => $order_id,
                'user_id' => Auth::id(),
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'code' => '404',
                'status' => 'error',
                'message' => 'Order not found'
            ], 404);
        }
    }

    public function notification(Request $request)
    {
        try {
            Log::info('Midtrans Notification Received:', [
                'raw_request' => $request->all(),
                'raw_content' => $request->getContent()
            ]);

            $notification = json_decode($request->getContent(), true);

            // Validasi data yang diperlukan
            $requiredFields = ['order_id', 'transaction_status', 'transaction_id', 'payment_type'];
            foreach ($requiredFields as $field) {
                if (!isset($notification[$field])) {
                    throw new \Exception("Missing required field: {$field}");
                }
            }

            // Simpan notifikasi ke database
            $paymentNotif = PaymentNotification::create([
                'order_id' => $notification['order_id'],
                'transaction_id' => $notification['transaction_id'],
                'status' => $notification['transaction_status'],
                'payment_type' => $notification['payment_type'],
                'gross_amount' => $notification['gross_amount'] ?? 0,
                'raw_response' => $notification
            ]);

            $order = Order::where('order_id', $notification['order_id'])
                         ->orWhere('order_id', preg_replace('/^ORDER-/', '', $notification['order_id']))
                         ->first();

            if ($order) {
                // Map status Midtrans ke status order
                $statusMapping = [
                    'capture' => [
                        'status' => 'success',
                        'shipping_status' => 'processing'
                    ],
                    'settlement' => [
                        'status' => 'success',
                        'shipping_status' => 'processing'
                    ],
                    'pending' => [
                        'status' => 'pending',
                        'shipping_status' => null
                    ],
                    'deny' => [
                        'status' => 'failed',
                        'shipping_status' => null
                    ],
                    'expire' => [
                        'status' => 'expired',
                        'shipping_status' => null
                    ],
                    'cancel' => [
                        'status' => 'cancel',
                        'shipping_status' => null
                    ],
                    'failure' => [
                        'status' => 'failed',
                        'shipping_status' => null
                    ]
                ];

                $newStatus = $statusMapping[$notification['transaction_status']] ?? [
                    'status' => 'pending',
                    'shipping_status' => null
                ];

                // Mulai transaction untuk update order dan stock
                DB::beginTransaction();
                try {
                    // Update order
                    $order->update([
                        'status' => $newStatus['status'],
                        'shipping_status' => $newStatus['shipping_status'],
                        'payment_type' => $notification['payment_type'],
                        'transaction_id' => $notification['transaction_id'],
                        'paid_at' => in_array($newStatus['status'], ['success']) ? now() : null
                    ]);

                    // Jika status success, kurangi stock_quantity
                    if ($newStatus['status'] === 'success') {
                        foreach ($order->orderItems as $item) {
                            $product = $item->product;
                            
                            // Validasi stock_quantity mencukupi
                            if ($product->stock_quantity < $item->quantity) {
                                DB::rollBack();
                                throw new \Exception("Insufficient stock for product: {$product->product_name}");
                            }

                            // Kurangi stock_quantity
                            $product->update([
                                'stock_quantity' => $product->stock_quantity - $item->quantity
                            ]);

                            Log::info('Stock updated for product:', [
                                'product_id' => $product->product_id,
                                'old_stock' => $product->getOriginal('stock_quantity'),
                                'new_stock' => $product->stock_quantity,
                                'quantity_reduced' => $item->quantity
                            ]);
                        }
                    }

                    DB::commit();

                    Log::info('Order updated from Midtrans:', [
                        'order_id' => $order->order_id,
                        'transaction_status' => $notification['transaction_status'],
                        'old_status' => $order->getOriginal('status'),
                        'new_status' => $newStatus['status'],
                        'old_shipping_status' => $order->getOriginal('shipping_status'),
                        'new_shipping_status' => $newStatus['shipping_status']
                    ]);

                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Failed to update order and stock:', [
                        'order_id' => $order->order_id,
                        'error' => $e->getMessage()
                    ]);
                    throw $e;
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Notification processed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Notification Error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateShippingStatus(Request $request, $orderId)
    {
        try {
            // 1. Validasi role seller
            if ($request->user()->role !== 'seller') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized. Only seller can update shipping status'
                ], 403);
            }

            // 2. Pastikan user memiliki seller data
            $seller = $request->user()->seller;
            if (!$seller) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Seller data not found'
                ], 404);
            }

            // Debug log seller
            \Log::info('Seller Data:', [
                'user_id' => $request->user()->user_id,
                'seller' => $seller->toArray()
            ]);

            DB::beginTransaction();
            try {
                // 3. Load order dengan eager loading
                $order = Order::where('order_id', $orderId)
                    ->where('seller_id', $seller->seller_id)
                    ->first();

                if (!$order) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Order not found or not authorized'
                    ], 404);
                }

                // Debug log order
                \Log::info('Order Data:', [
                    'order' => $order->toArray()
                ]);

                // 4. Validasi alur status
                $validStatusFlow = [
                    null => ['processing'],
                    'processing' => ['shipped', 'cancelled'],
                    'shipped' => ['delivered', 'cancelled'],
                    'delivered' => [], // End state
                    'cancelled' => [] // End state
                ];

                if (!in_array($request->shipping_status, $validStatusFlow[$order->shipping_status] ?? [])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Cannot change shipping status from '{$order->shipping_status}' to '{$request->shipping_status}'",
                        'valid_next_statuses' => $validStatusFlow[$order->shipping_status] ?? []
                    ], 422);
                }

                // 5. Update shipping status
                $previousStatus = $order->shipping_status;
                $order->shipping_status = $request->shipping_status;
                $order->save();

                // 6. Update total_sales jika delivered
                if ($request->shipping_status === 'delivered') {
                    try {
                        // Update total sales seller
                        $totalSales = Order::where('seller_id', $seller->seller_id)
                            ->where('status', 'success')
                            ->where('shipping_status', 'delivered')
                            ->sum('total_amount');
                        
                        \Log::info('Total Sales Calculation:', [
                            'seller_id' => $seller->seller_id,
                            'total_sales' => $totalSales
                        ]);

                        $seller->update([
                            'total_sales' => $totalSales
                        ]);

                        // Tambahan: Update total sales untuk setiap produk
                        foreach ($order->orderItems as $item) {
                            $product = Product::find($item->product_id);
                            if ($product) {
                                // Hitung total sales produk
                                $productTotalSales = OrderItem::whereHas('order', function($query) {
                                    $query->where('status', 'success')
                                        ->where('shipping_status', 'delivered');
                                })
                                ->where('product_id', $product->product_id)
                                ->sum('quantity');

                                \Log::info('Product Total Sales Update:', [
                                    'product_id' => $product->product_id,
                                    'product_name' => $product->product_name,
                                    'total_sales' => $productTotalSales
                                ]);

                                $product->update([
                                    'total_sales' => $productTotalSales
                                ]);
                            }
                        }

                    } catch (\Exception $e) {
                        \Log::error('Error updating sales:', [
                            'error' => $e->getMessage(),
                            'seller_id' => $seller->seller_id
                        ]);
                        // Lanjutkan eksekusi meski update stats gagal
                    }
                }

                DB::commit();

                // 7. Siapkan response data
                $responseData = [
                    'status' => 'success',
                    'message' => 'Shipping status updated successfully',
                    'data' => [
                        'order_id' => $order->order_id,
                        'previous_status' => $previousStatus,
                        'current_status' => $order->shipping_status,
                        'updated_at' => $order->updated_at,
                        'valid_next_statuses' => $validStatusFlow[$request->shipping_status] ?? []
                    ]
                ];

                // Tambahkan seller data jika ada
                if ($seller) {
                    $responseData['data']['seller'] = [
                        'seller_id' => $seller->seller_id,
                        'store_name' => $seller->store_name,
                        'total_sales' => $seller->total_sales,
                        'store_rating' => $seller->store_rating
                    ];
                }

                return response()->json($responseData);

            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Transaction Error:', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            \Log::error('Update Shipping Status Error:', [
                'order_id' => $orderId,
                'user_id' => $request->user()->user_id,
                'seller_id' => $seller->seller_id ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update shipping status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getNotificationHistory($orderId = null)
    {
        try {
            $query = PaymentNotification::with('order')
                ->latest();

            if ($orderId) {
                $query->where('order_id', $orderId);
            }

            $notifications = $query->paginate(10);

            return response()->json([
                'status' => 'success',
                'data' => $notifications
            ]);

        } catch (\Exception $e) {
            Log::error('Get Notification History Error:', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get notification history'
            ], 500);
        }
    }

    public function testNotification()
    {
        try {
            $testData = [
                'transaction_time' => date('Y-m-d H:i:s'),
                'transaction_status' => 'settlement',
                'transaction_id' => 'test-' . time(),
                'status_message' => 'midtrans payment notification',
                'status_code' => '200',
                'signature_key' => 'test-signature',
                'payment_type' => 'gopay',
                'order_id' => 'ORDER-' . time(),
                'merchant_id' => 'TEST',
                'gross_amount' => '10000.00',
                'fraud_status' => 'accept',
                'currency' => 'IDR'
            ];

            return $this->notification(new Request($testData));

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Export to Excel
    public function exportExcel(Request $request)
    {
        try {
            $export = new OrdersExport(
                $request->date_from,
                $request->date_to,
                $request->status
            );

            return Excel::download($export, 'orders.xlsx');
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export: ' . $e->getMessage()
            ], 500);
        }
    }

    // Export to PDF
    public function exportPDF($orderId)
    {
        try {
            $order = Order::with([
                'user.customer', 
                'orderItems.product', 
                'address'
            ])->findOrFail($orderId);

            $pdf = Pdf::loadView('pdf.order', compact('order'));
            return $pdf->download("order-{$orderId}.pdf");
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    // Import Products from CSV
    public function importProducts(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:csv,txt|max:2048'
            ]);

            Excel::import(new ProductsImport, $request->file('file'));

            return response()->json([
                'status' => 'success',
                'message' => 'Products imported successfully'
            ]);

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->failures()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to import products: ' . $e->getMessage()
            ], 500);
        }
    }

    // Update status pembayaran
    public function updateStatus(Request $request, $orderId)
    {
        try {
            if ($request->user()->role !== 'seller') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized. Only seller can update payment status'
                ], 403);
            }

            // Validasi input
            $request->validate([
                'status' => 'required|in:pending,success,failed,expired,cancel,challenge'
            ]);

            DB::beginTransaction();
            try {
                $order = Order::with('orderItems.product')->findOrFail($orderId);

                // Update status dan shipping_status
                $updateData = [
                    'status' => $request->status,
                    'paid_at' => $request->status === 'success' ? now() : null,
                    'shipping_status' => $request->status === 'success' ? 'processing' : null
                ];

                $order->update($updateData);

                // Jika status success, kurangi stock_quantity
                if ($request->status === 'success') {
                    foreach ($order->orderItems as $item) {
                        $product = $item->product;
                        
                        // Validasi stock_quantity mencukupi
                        if ($product->stock_quantity < $item->quantity) {
                            DB::rollBack();
                            return response()->json([
                                'status' => 'error',
                                'message' => "Insufficient stock for product: {$product->product_name}"
                            ], 422);
                        }

                        // Kurangi stock_quantity
                        $product->update([
                            'stock_quantity' => $product->stock_quantity - $item->quantity
                        ]);
                    }
                }

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment status updated successfully',
                    'data' => [
                        'order_id' => $order->order_id,
                        'status' => $order->status,
                        'shipping_status' => $order->shipping_status,
                        'paid_at' => $order->paid_at,
                        'updated_at' => $order->updated_at
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Update Payment Status Error:', [
                'order_id' => $orderId,
                'message' => $e->getMessage()
            ]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

} 
