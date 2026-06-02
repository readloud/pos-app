<?php

use Illuminate\Support\Facades\Route;

// Welcome page
Route::get("/", function () {
    return view("welcome");
});

// Test route
Route::get("/test", function() { 
    return "Working!"; 
});

// Auth routes (if using Laravel UI)
// Auth::routes();

// Home route
// Route::get("/home", [App\Http\Controllers\HomeController::class, "index"])->name("home");

// API routes are in api.php
Route::get("/api-simple-test", function() { return response()->json(["message" => "API test works!"]); });

Route::get("/debug", function() {
    return response()->json([
        "message" => "Debug endpoint works",
        "url" => url()->current(),
        "api_url" => route("api.health", [], false),
        "routes" => array_keys(Route::getRoutes()->getRoutesByName())
    ]);
});

// Test API route in web.php
Route::get("/web-api-test", function() {
    return response()->json([
        "message" => "This is from web.php, not api.php",
        "api_route_working" => true
    ]);
});
