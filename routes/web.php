<?php

use Illuminate\Support\Facades\Route;

// Serve the Vue app for the root route
Route::get('/', function () {
    return view('welcome');
});

// Catch-all route for Vue Router (SPA routing)
// This must be the last route to catch all unmatched routes
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
