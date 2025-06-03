<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\GalleryProductController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use App\Http\Controllers\ReviewCommentController;
use App\Http\Controllers\ReviewResponseController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\ShippingAddressController;
use App\Http\Controllers\AddressController;


// Authentication Routes
Route::post('/register/customer', [AuthController::class, 'registerCustomer']);
// Route::post('/register/seller', [AuthController::class, 'registerSeller']);
// Route::post('/register/admin', [AuthController::class, 'registerAdmin']);
Route::post('/login', [AuthController::class, 'login']);

// Rute untuk mendapatkan profil seller
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/authentication', [AuthController::class, 'getSellerProfile']);
});



// Profile Routes
Route::middleware('auth:sanctum')->group(function () {
    
    Route::put('/customer/biodata', [ProfileController::class, 'updateCustomer']);
    Route::post('/customer/biodata', [ProfileController::class, 'updateCustomer']);
    Route::put('/seller/biodata', [ProfileController::class, 'updateSeller']);
    Route::post('/seller/biodata', [ProfileController::class, 'updateSeller']);
});

Route::get('/customers', [ProfileController::class, 'indexCustomer']);
Route::get('/customers/{id}', [ProfileController::class, 'showCustomer']);
Route::get('/sellers', [ProfileController::class, 'indexSeller']);
Route::get('/sellers/{id}', [ProfileController::class, 'showSeller']);


// Category Routes
// Public Category Routes
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/{id}/products', [CategoryController::class, 'getProducts']);
});

// Admin Category Routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('admin/categories')->group(function () {
        Route::get('/', [CategoryController::class, 'adminIndex']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('/{id}', [CategoryController::class, 'update']);
        Route::delete('/{id}', [CategoryController::class, 'destroy']);
        Route::put('/{id}/restore', [CategoryController::class, 'restore']);
    });
});


// Product Routes

// Public Routes
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{id}', [ProductController::class, 'show']);
    Route::get('/{productId}/reviews', [ReviewController::class, 'publicReviews']);
});

// Protected Routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Seller Routes
    Route::prefix('seller/products')->group(function () {
        Route::get('/', [ProductController::class, 'sellerIndex']);
        Route::post('/', [ProductController::class, 'store']);
        Route::put('/{id}', [ProductController::class, 'update']);
        Route::delete('/{id}', [ProductController::class, 'destroy']);
        Route::put('/{id}/restore', [ProductController::class, 'restore']);
    });

    // Review Routes
    

    // Submit review untuk order
    Route::post('orders/{orderId}/reviews', [ReviewController::class, 'store']);
    
    // Get review history user yang login
    Route::get('user/reviews', [ReviewController::class, 'userReviews']);
    
    // Get review yang bisa dibuat (dari order yang delivered)
    Route::get('user/reviewable-orders', [ReviewController::class, 'reviewableOrders']);
});

// Public Routes
Route::get('products/{id}/gallery', [GalleryProductController::class, 'index']);

// Protected Routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Seller Routes
    Route::prefix('seller/gallery')->group(function () {
        Route::post('/upload/{product_Id}', [GalleryProductController::class, 'store']);
        Route::put('/{id}/main', [GalleryProductController::class, 'setAsMain']);
        Route::delete('/{id}', [GalleryProductController::class, 'destroy']);
    });

    // Admin Routes
    Route::prefix('admin/gallery')->group(function () {
        Route::delete('/{id}', [GalleryProductController::class, 'adminDestroy']);
    });
});






// Article routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('article', [ArticleController::class, 'store']);
    Route::put('article/{id}', [ArticleController::class, 'update']);
    Route::post('article/{id}', [ArticleController::class, 'update']);
    Route::delete('article/{id}', [ArticleController::class, 'destroy']);
});

Route::get('article', [ArticleController::class, 'index']);
Route::get('article/{id}', [ArticleController::class, 'show']);

// Comment Routes 

// Route publik untuk melihat komentar
Route::get('comments', [CommentController::class, 'index']);
Route::get('comments/{id}', [CommentController::class, 'show']);
Route::get('article/{article_id}/comments', [CommentController::class, 'getArticleComments']);
Route::get('comments/{comment_id}/replies', [CommentController::class, 'getReplies']);

Route::middleware('auth:sanctum')->group(function () {
// Route yang membutuhkan autentikasi
Route::post('article/{article_id}/comments', [CommentController::class, 'store']);
Route::put('comments/{id}', [CommentController::class, 'update']);
Route::delete('comments/{id}', [CommentController::class, 'destroy']);
});

//chat routes

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/send-message', [ChatController::class, 'sendMessage']);
    Route::get('/messages/{receiver_id}', [ChatController::class, 'getMessages']);

    Route::get('/chat/rooms', [ChatController::class, 'getRooms']);
    Route::post('/chat/rooms', [ChatController::class, 'createRoom']);
    
    // Messages
    Route::get('/chat/messages/{room_id}', [ChatController::class, 'getMessages']);
    Route::post('/chat/send-message', [ChatController::class, 'sendMessage']);
    
    // Optional: Mark messages as read
    Route::post('/chat/messages/mark-read', [ChatController::class, 'markAsRead']);
});


// Cart Routes
Route::middleware('auth:sanctum')->prefix('cart')->group(function () {
    // Menampilkan cart
    Route::get('/', [CartController::class, 'index']);
    
    // Menambah item ke cart
    Route::post('/add', [CartController::class, 'addItem']);
    
    Route::put('/decrement/{cart_item_id}', [CartController::class, 'decrementItem']);
    // Update quantity item di cart
    Route::put('/items/{cart_item_id}', [CartController::class, 'updateItem']);
    
    // Hapus item dari cart
    Route::delete('/items/{cart_item_id}', [CartController::class, 'removeItem']);
    
    // Kosongkan cart
    Route::post('/clear', [CartController::class, 'clear']);
    
    // Pindahkan dari wishlist ke cart
    Route::post('/add-from-wishlist', [CartController::class, 'addFromWishlist']);
    
    // Pindahkan multiple items dari wishlist ke cart
    Route::post('/add-multiple-from-wishlist', [CartController::class, 'addMultipleFromWishlist']);
});

// Wishlist Routes
Route::middleware('auth:sanctum')->prefix('wishlist')->group(function () {
    // Menampilkan wishlist
    Route::get('/', [WishlistController::class, 'index']);
    
    // Menambah produk ke wishlist
    Route::post('/add', [WishlistController::class, 'add']);
    
    // Hapus produk dari wishlist
    Route::delete('/{wishlist_id}', [WishlistController::class, 'remove']);
    
    // Cek status produk di wishlist
    Route::get('/check/{product_id}', [WishlistController::class, 'checkStatus']);
});



// Order Routes


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{orderId}/shipping-status', [OrderController::class, 'updateShippingStatus']);
    // Route::patch('/orders/{orderId}/payment-status', [OrderController::class, 'updatePaymentStatus']);
    Route::put('/orders/{orderId}/status', [OrderController::class, 'updateStatus']);
    Route::get('orders/export/excel', [OrderController::class, 'exportExcel']);
    Route::get('orders/{order}/export/pdf', [OrderController::class, 'exportPDF']);
    Route::post('products/import', [OrderController::class, 'importProducts']);
});

// Address Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/addresses', [AddressController::class, 'index']); // Menampilkan semua alamat
    Route::post('/addresses', [AddressController::class, 'store']); // Menyimpan alamat baru
    Route::get('/addresses/{id}', [AddressController::class, 'show']); // Menampilkan alamat berdasarkan ID
    Route::put('/addresses/{id}', [AddressController::class, 'update']); // Mengupdate alamat
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy']); // Menghapus alamat
    Route::put('/addresses/{id}/main', [AddressController::class, 'setAsMain']); // Mengatur alamat sebagai utama
});
Route::get('/districts', [AddressController::class, 'getDistricts']);
Route::get('/districts/{district_id}/poscodes', [AddressController::class, 'getPosCodesByDistrict']);

// Midtrans webhook


// Get notification history
Route::get('notifications', [OrderController::class, 'notificationHistory']);

// Get order with payment notifications
Route::get('orders/{orderId}/payment', [OrderController::class, 'getOrderWithPayment']);

// Midtrans Notification Webhook
Route::post('notification', [OrderController::class, 'notification']);

// Get Notification History
Route::get('notifications/{orderId?}', [OrderController::class, 'getNotificationHistory']);

// Test Notification (development only)
if (config('app.env') !== 'production') {
    Route::get('test-notification', [OrderController::class, 'testNotification']);
}

// Reviews














// // Review Routes
// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('/reviews', [ReviewController::class, 'store']);
//     Route::put('/reviews/{id}', [ReviewController::class, 'update']);
//     Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
    
//     // Review Images
//     Route::post('/reviews/{review}/images', [ReviewController::class, 'uploadImages']);
//     Route::delete('/reviews/{review}/images/{image}', [ReviewController::class, 'deleteImage']);
    
//     // Review Likes
//     Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'like']);
//     Route::delete('/reviews/{review}/like', [ReviewLikeController::class, 'unlike']);
    
//     // Review Comments
//     Route::post('/reviews/{review}/comments', [ReviewCommentController::class, 'store']);
//     Route::put('/reviews/{review}/comments/{comment}', [ReviewCommentController::class, 'update']);
//     Route::delete('/reviews/{review}/comments/{comment}', [ReviewCommentController::class, 'destroy']);
    
//     // Seller Response to Review
//     Route::post('/reviews/{review}/response', [ReviewResponseController::class, 'store']);
//     Route::put('/reviews/{review}/response/{response}', [ReviewResponseController::class, 'update']);
//     Route::delete('/reviews/{review}/response/{response}', [ReviewResponseController::class, 'destroy']);
// });

// // Public Review Routes
// Route::get('/reviews', [ReviewController::class, 'index']);
// Route::get('/reviews/{id}', [ReviewController::class, 'show']);
// Route::get('/reviews/{review}/comments', [ReviewCommentController::class, 'index']);
