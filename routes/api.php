<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\CouponController;
use App\Http\Controllers\API\ReviewController;
use Illuminate\Support\Facades\Password;
use App\Http\Controllers\API\SocialiteController;
/*
|--------------------------------------------------------------------------
| Public Routes (no authentication required)
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/categories', [CategoryController::class, 'index']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/featured', [ProductController::class, 'featured']);
Route::get('/products/latest', [ProductController::class, 'latest']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/{id}/reviews', [ReviewController::class, 'getForProduct']);
Route::get('/payment/settings', [PaymentController::class, 'getSettings']);

// Route::get('/auth/google', [SocialiteController::class, 'redirectToProvider']);
// Route::get('/auth/google/callback', [SocialiteController::class, 'handleProviderCallback']);
// Route::get('/auth/facebook', [SocialiteController::class, 'redirectToProvider']);
// Route::get('/auth/facebook/callback', [SocialiteController::class, 'handleProviderCallback']);
// password reset

Route::post('/forgot-password', function (Request $request) {
    $request->validate(['email' => 'required|email']);
    $status = Password::sendResetLink($request->only('email'));
    return $status === Password::RESET_LINK_SENT
        ? response()->json(['message' => __($status)], 200)
        : response()->json(['message' => __($status)], 400);
})->name('password.email');

Route::post('/reset-password', function (Request $request) {
    $request->validate([
        'token' => 'required',
        'email' => 'required|email',
        'password' => 'required|min:8|confirmed',
    ]);
    $status = Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function ($user, $password) {
            $user->forceFill(['password' => bcrypt($password)])->save();
        }
    );
    return $status === Password::PASSWORD_RESET
        ? response()->json(['message' => __($status)], 200)
        : response()->json(['message' => __($status)], 400);
})->name('password.update');

/*
|--------------------------------------------------------------------------
| Authenticated Routes (require valid token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/profile', [AuthController::class, 'updateProfile']);

    // Coupons
    Route::post('/coupons/validate', [CouponController::class, 'validateCoupon']);

    // Orders
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/my', [OrderController::class, 'myOrders']);

    // Customers / Users (non‑admin users list)

    // Reviews (authenticated user can create a review)
     Route::post('/reviews', [ReviewController::class, 'store']);

    /*
    |--------------------------------------------------------------------------
    | Admin Only Routes (require 'admin' middleware)
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->group(function () {

        // Categories management (except index which is public)
        Route::apiResource('categories', CategoryController::class)->except(['index']);

        Route::get('/customers', [UserController::class, 'customers']);
        Route::put('/customers/{user}', [UserController::class, 'update']);
        Route::get('/users', [UserController::class, 'index']);

        // Products management (except index/show which are public)
        Route::apiResource('products', ProductController::class)->except(['index', 'show']);
        Route::patch('/products/{id}/stock', [ProductController::class, 'updateStock']);

        Route::apiResource('coupons', CouponController::class);

        // Orders management
        Route::get('/orders/statistics', [OrderController::class, 'statistics']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
        Route::put('/orders/{id}/payment', [OrderController::class, 'updatePaymentStatus']);
        Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);

        // Customers (admin view)
        Route::get('/customers/list', [OrderController::class, 'customers']);

        // Payment settings (admin update)
        Route::post('/payment/settings', [PaymentController::class, 'updateSettings']);

        // Reviews admin (list, approve, delete)
         Route::get('/admin/reviews', [ReviewController::class, 'index']);
    Route::patch('/reviews/{id}/approve', [ReviewController::class, 'approve']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
    });
});
