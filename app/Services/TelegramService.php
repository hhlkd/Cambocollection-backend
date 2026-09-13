<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private $botToken;
    private $chatId;
    private $baseUrl = 'https://api.telegram.org/bot';

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->chatId = config('services.telegram.chat_id');
    }

    public function sendMessage($message)
    {
        if (!$this->botToken || !$this->chatId) {
            Log::warning('Telegram bot token or chat ID not configured');
            return false;
        }
        try {
            $response = Http::post("{$this->baseUrl}{$this->botToken}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Telegram notification failed: ' . $e->getMessage());
            return false;
        }
    }

    public function sendPhoto($photoUrl, $caption = '')
    {
        if (!$this->botToken || !$this->chatId) {
            Log::warning('Telegram bot token or chat ID not configured');
            return false;
        }
        try {
            $response = Http::post("{$this->baseUrl}{$this->botToken}/sendPhoto", [
                'chat_id' => $this->chatId,
                'photo' => $photoUrl,
                'caption' => $caption,
                'parse_mode' => 'HTML'
            ]);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Telegram photo notification failed: ' . $e->getMessage());
            return false;
        }
    }

    public function notifyOrderPlaced($order)
    {
        $message = $this->buildOrderMessage($order);
        return $this->sendMessage($message);
    }

    private function buildOrderMessage($order)
    {
        $itemsList = '';
        foreach ($order->items as $item) {
            $itemsList .= "• {$item->product_name} x{$item->quantity} = \${$item->subtotal}\n";
        }

        $message = "<b>🛒 New Order Placed!</b>\n\n";
        $message .= "<b>Order ID:</b> {$order->order_number}\n";
        $message .= "<b>Customer:</b> {$order->customer_name}\n";
        $message .= "<b>Email:</b> {$order->customer_email}\n";
        $message .= "<b>Phone:</b> {$order->customer_phone}\n\n";
        $message .= "<b>Items:</b>\n{$itemsList}\n";
        $message .= "<b>Subtotal:</b> \${$order->subtotal}\n";

        // ✅ Show discount if any (and optionally the coupon code)
        if ($order->discount > 0) {
            $couponCode = $order->coupon ? $order->coupon->code : 'Coupon';
            $message .= "<b>Discount ({$couponCode}):</b> - \${$order->discount}\n";
        }

        $message .= "<b>Tax:</b> \${$order->tax}\n";
        $message .= "<b>Shipping:</b> \${$order->shipping}\n";
        $message .= "<b>Total:</b> \${$order->total}\n\n";
        $message .= "<b>Payment Method:</b> {$order->payment_method}\n";
        $message .= "<b>Payment Status:</b> <code>{$order->payment_status}</code>\n\n";
        $message .= "<b>Shipping Address:</b>\n{$order->shipping_address}\n{$order->city}, {$order->zip_code}\n{$order->country}\n\n";
        $message .= "<b>Time:</b> {$order->created_at->format('Y-m-d H:i:s')}";

        return $message;
    }
}