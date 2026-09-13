<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\SocialiteController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/{provider}', [SocialiteController::class, 'redirectToProvider']);
Route::get('/auth/{provider}/callback', [SocialiteController::class, 'handleProviderCallback']);