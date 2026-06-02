<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\SaleController;

// API health check
Route::get("/health", function() {
    return response()->json([
        "status" => "ok",
        "message" => "API is working",
        "timestamp" => now()->toDateTimeString()
    ]);
});

// Test endpoint
Route::get("/test", function() {
    return response()->json([
        "message" => "API test endpoint works!"
    ]);
});

// Public routes
Route::post("/login", [AuthController::class, "login"]);

// Protected routes
Route::middleware(["auth:sanctum"])->group(function () {
    // User routes
    Route::get("/user", [AuthController::class, "user"]);
    Route::post("/logout", [AuthController::class, "logout"]);
    
    // Product routes  
    Route::get("/products", [ProductController::class, "index"]);
    Route::get("/products/search", [ProductController::class, "search"]);
    Route::get("/products/{id}", [ProductController::class, "show"]);
    
    // Cart routes
    Route::get("/cart", [CartController::class, "index"]);
    Route::post("/cart/items", [CartController::class, "addItem"]);
    Route::delete("/cart/items/{itemId}", [CartController::class, "removeItem"]);
    Route::delete("/cart", [CartController::class, "clear"]);
    
    // Sale routes
    Route::get("/sales", [SaleController::class, "index"]);
    Route::get("/sales/{id}", [SaleController::class, "show"]);
    Route::post("/sales", [SaleController::class, "store"]);
});
require __DIR__ . "/api_simple.php";
