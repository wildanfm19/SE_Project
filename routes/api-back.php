<?php

#use Illuminate\Http\Request;
#use Illuminate\Support\Facades\Route;
#
#Route::get('/user', function (Request $request) {
 #   return $request->user();
 #})->middleware('auth:sanctum');

// use App\Http\Controllers\AuthController;
// use App\Http\Controllers\ProductController;

// // Route untuk login
// Route::post('/login', [AuthController::class, 'login']);

// // Route untuk register (opsional)
// Route::post('register', [AuthController::class, 'register']);
// Authentication Routes
use App\Http\Controllers\AuthController;

Route::post('/register/customer', [AuthController::class, 'registerCustomer']);
Route::post('/register/seller', [AuthController::class, 'registerSeller']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Profile Routes
use App\Http\Controllers\ProfileController;

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/customer/biodata', [ProfileController::class, 'updateCustomer']);
    Route::put('/seller/biodata', [ProfileController::class, 'updateSeller']);
});

// Category Routes
use App\Http\Controllers\CategoryController;

Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/categories', [CategoryController::class, 'store']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);
Route::put('/categories/{id}', [CategoryController::class, 'update']);
Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

// Product Routes
use App\Http\Controllers\ProductController;

Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::put('/products/{id}', [ProductController::class, 'update']);
Route::delete('/products/{id}', [ProductController::class, 'destroy']);


// Route::get('product_list', [ProductController::class, 'product_list']);
// Route::get('/products', [ProductController::class, 'index']);
// Route::post('/products', [ProductController::class, 'store']);
// Route::get('/products/{id}', [ProductController::class, 'show']);
// Route::put('/products/{id}', [ProductController::class, 'update']);
// Route::delete('/products/{id}', [ProductController::class, 'destroy']);


