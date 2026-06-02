<?php

use Illuminate\Support\Facades\Route;

Route::get("/", function () {
    return "Laravel 13 is working!";
});

Route::get("/test", function() {
    return response()->json(["message" => "Test endpoint works"]);
});
