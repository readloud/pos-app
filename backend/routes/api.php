<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\SaleController;
use Illuminate\Http\Request;

// Health check
Route::get("/health", function() {
    return response()->json([
        "status" => "ok",
        "message" => "POS API is running",
        "version" => "1.0.0"
    ]);
});

// Public routes
Route::post("/login", [AuthController::class, "login"]);

// Protected routes (require authentication)
Route::middleware(["auth:sanctum"])->group(function () {
    // User management
    Route::get("/user", [AuthController::class, "user"]);
    Route::post("/logout", [AuthController::class, "logout"]);
    
    // Products
    Route::get("/products", [ProductController::class, "index"]);
    Route::get("/products/search", [ProductController::class, "search"]);
    Route::get("/products/barcode/{barcode}", [ProductController::class, "getByBarcode"]);
    Route::get("/products/{id}", [ProductController::class, "show"]);
    
    // Shopping Cart
    Route::get("/cart", [CartController::class, "index"]);
    Route::post("/cart/items", [CartController::class, "addItem"]);
    Route::put("/cart/items/{itemId}", [CartController::class, "updateItem"]);
    Route::delete("/cart/items/{itemId}", [CartController::class, "removeItem"]);
    Route::delete("/cart/clear", [CartController::class, "clear"]);
    
    // Sales/Transactions
    Route::get("/sales", [SaleController::class, "index"]);
    Route::get("/sales/{id}", [SaleController::class, "show"]);
    Route::get("/sales/{id}/invoice", [SaleController::class, "invoice"]);
    Route::post("/sales", [SaleController::class, "store"]);
});
