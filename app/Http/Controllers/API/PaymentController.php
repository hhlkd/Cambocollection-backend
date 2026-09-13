<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
class PaymentController extends Controller
{
    /**
     * Get all payment settings (Public - for customers during checkout)
     */
    public function getSettings()
    {
        $settings = PaymentSetting::first();

        // No settings configured yet — return empty defaults instead of crashing.
        if (!$settings) {
            return response()->json([
                'khqr_image_url' => null,
                'aba_holder_name' => '',
                'aba_account' => '',
            ]);
        }

        return response()->json([
            'khqr_image_url' => $settings->khqr_image ? Storage::url($settings->khqr_image) : null,
            'aba_holder_name' => $settings->aba_holder_name ?? '',
            'aba_account' => $settings->aba_account ?? '',
        ]);
    }

    /**
     * Update payment settings (Admin only)
     */
    public function updateSettings(Request $request)
    {
        $settings = PaymentSetting::firstOrNew();
        if ($request->hasFile('khqr_image')) {
            $path = $request->file('khqr_image')->store('khqr', 'public');
            $settings->khqr_image = $path;
        }
        if ($request->filled('aba_holder_name')) {
            $settings->aba_holder_name = $request->aba_holder_name;
        }
        if ($request->filled('aba_account')) {
            $settings->aba_account = $request->aba_account;
        }
        $settings->save();

        return response()->json([
            'message' => 'Updated',
            'khqr_image_url' => $settings->khqr_image ? Storage::url($settings->khqr_image) : null,
            'aba_holder_name' => $settings->aba_holder_name,
            'aba_account' => $settings->aba_account,
        ]);
    }
    public function handleCallback(Request $request) 
{
    // Log the incoming callback for debugging
    Log::info('ABA Payment Callback Received', $request->all());

    $tranId = $request->input('tran_id');
    $status = $request->input('status');

    // IMPORTANT: For security, you MUST verify the status with ABA's Check API here.
    // You can find the PHP package for the API on Packagist[reference:7].

    if ($status === '00') { // Assuming '00' is the success code from their docs[reference:8]
        // Update your order to 'paid' or 'processing' using the $tranId
        Order::where('transaction_id', $tranId)->update(['payment_status' => 'paid']);
        Log::info("Order for transaction {$tranId} has been marked as paid.");
    }

    return response()->json(['message' => 'OK']);
}
}
