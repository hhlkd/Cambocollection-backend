<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Coupon;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // Get all orders (Admin)
    public function index(Request $request)
    {
        try {
            $query = Order::with(['user', 'items.product']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%");
                });
            }

            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = strtolower($request->get('sort_order', 'desc'));
            $sortBy = in_array($sortBy, ['created_at', 'total', 'status', 'payment_status'], true) ? $sortBy : 'created_at';
            $sortOrder = in_array($sortOrder, ['asc', 'desc'], true) ? $sortOrder : 'desc';
            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->get('per_page', 15);
            $orders = $query->paginate($perPage);
            $orders->load(['items.product']);   // <-- must be after paginate
            return response()->json(['success' => true, 'data' => $orders]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // Get all customers (non-admins) with order counts
    public function customers()
    {
        try {
            $customers = User::where('is_admin', false)
                ->withCount('orders')
                ->latest()
                ->get()
                ->map(function ($user) {
                    $user->is_active = true;
                    return $user;
                });
            return response()->json(['success' => true, 'data' => $customers]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error fetching customers: ' . $e->getMessage()], 500);
        }
    }

    // Get user's orders
    public function myOrders(Request $request)
    {
        $orders = Order::with(['items.product'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);
        return response()->json(['success' => true, 'data' => $orders]);
    }

    // Get single order
    public function show($id)
    {
        $order = Order::with(['user', 'items.product'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $order]);
    }

    // Create order (Checkout) – with coupon support
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.selected_size' => 'nullable|string|max:10',
            'items.*.selected_color' => 'nullable|string|max:50',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email',
            'customer_phone' => 'required|string',
            'shipping_address' => 'required|string',
            'city' => 'required|string',
            'zip_code' => 'required|string',
            'country' => 'nullable|string',
            'payment_method' => 'required|string',
            'notes' => 'nullable|string',
            'khqr_confirmed' => 'nullable|boolean',
            'payment_receipt' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'coupon_code' => 'nullable|string|exists:coupons,code'
        ]);

        DB::beginTransaction();

        try {
            // Calculate subtotal
            $subtotal = 0;
            $orderItemsData = [];

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product: {$product->name}");
                }

                $price = $product->sale_price ?? $product->price;
                $itemSubtotal = $price * $item['quantity'];
                $subtotal += $itemSubtotal;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'product_image' => $product->images[0] ?? null,
                    'selected_size' => $item['selected_size'] ?? null,
                    'selected_color' => $item['selected_color'] ?? null,
                    'quantity' => $item['quantity'],
                    'price' => $price,
                    'subtotal' => $itemSubtotal
                ];

                $product->decrementStock($item['quantity']);
            }

            $discount = 0;
            $appliedCoupon = null;

            if ($request->filled('coupon_code')) {
                $coupon = Coupon::where('code', $request->coupon_code)->first();

                if (!$coupon || !$coupon->isValid()) {
                    throw new \Exception('Invalid or expired coupon code.');
                }

                if ($coupon->min_order_amount && $subtotal < $coupon->min_order_amount) {
                    throw new \Exception("Minimum order amount of \${$coupon->min_order_amount} required for this coupon.");
                }

                if ($coupon->discount_type === 'percentage') {
                    $discount = $subtotal * ($coupon->discount_value / 100);
                } else {
                    $discount = $coupon->discount_value;
                }
                $discount = min($discount, $subtotal);
                $coupon->increment('used_count');
                $appliedCoupon = $coupon;
            }

            // Apply discount to subtotal
            $subtotalAfterDiscount = max(0, $subtotal - $discount);

            // Calculate total quantity for shipping
            $totalQuantity = 0;
            foreach ($request->items as $item) {
                $totalQuantity += $item['quantity'];
            }

            $shipping = $totalQuantity >= 2 ? 0 : 2.00;
            $tax = 0;
            $total = $subtotalAfterDiscount + $tax + $shipping;

            $khqrConfirmed = $request->boolean('khqr_confirmed') && $request->payment_method === 'KHQR';

            $receiptPath = null;
            if ($request->hasFile('payment_receipt')) {
                $receiptPath = $request->file('payment_receipt')->store('receipts', 'public');
            }

            // Create order – including discount column
            $order = Order::create([
                'user_id' => $request->user()->id ?? null,
                'subtotal' => $subtotalAfterDiscount,
                'discount' => $discount,
                'tax' => $tax,
                'shipping' => $shipping,
                'total' => $total,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'shipping_address' => $request->shipping_address,
                'city' => $request->city,
                'state' => $request->state,
                'zip_code' => $request->zip_code,
                'country' => $request->country ?? 'Cambodia',
                'payment_method' => $request->payment_method,
                'transaction_id' => $khqrConfirmed ? 'KHQR-' . strtoupper(uniqid()) : null,
                'payment_receipt' => $receiptPath ? \Illuminate\Support\Facades\Storage::url($receiptPath) : null,
                'notes' => $request->notes,
                'status' => $khqrConfirmed ? 'processing' : 'pending',
                'payment_status' => $khqrConfirmed ? 'paid' : 'pending',
                'paid_at' => $khqrConfirmed ? now() : null,
                'coupon_id' => $appliedCoupon ? $appliedCoupon->id : null
            ]);

            // Create order items
            foreach ($orderItemsData as $itemData) {
                $order->items()->create($itemData);
            }

            DB::commit();

            // Load relationships for response and notification
            $order->load(['items', 'coupon']);

            // Send Telegram notification (now includes discount and coupon details)
            try {
                $telegramService = new TelegramService();
                $telegramService->notifyOrderPlaced($order);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Telegram notification failed: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully',
                'data' => $order
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Order creation failed: ' . $e->getMessage()
            ], 400);
        }
    }

    // Update order status (Admin)
    public function updateStatus(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $request->validate(['status' => 'required|in:pending,processing,shipped,delivered,cancelled,refunded']);
        $order->update(['status' => $request->status]);

        if ($request->status === 'shipped' && !$order->shipped_at) $order->update(['shipped_at' => now()]);
        if ($request->status === 'delivered' && !$order->delivered_at) $order->update(['delivered_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Order status updated', 'data' => $order]);
    }

    // Update payment status (Admin)
    public function updatePaymentStatus(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $request->validate(['payment_status' => 'required|in:pending,paid,failed,refunded', 'transaction_id' => 'nullable|string']);
        $updateData = ['payment_status' => $request->payment_status];
        if ($request->payment_status === 'paid' && !$order->paid_at) $updateData['paid_at'] = now();
        if ($request->has('transaction_id')) $updateData['transaction_id'] = $request->transaction_id;
        $order->update($updateData);
        return response()->json(['success' => true, 'message' => 'Payment status updated', 'data' => $order]);
    }

    // Cancel order
    public function cancel($id)
    {
        $order = Order::findOrFail($id);
        if (!$order->canBeCancelled()) {
            return response()->json(['success' => false, 'message' => 'This order cannot be cancelled'], 400);
        }
        DB::beginTransaction();
        try {
            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);
                if ($product) $product->increment('stock', $item->quantity);
            }
            $order->update(['status' => 'cancelled']);
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Order cancelled', 'data' => $order]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Cancellation failed: ' . $e->getMessage()], 400);
        }
    }

    // Get order statistics (Admin Dashboard)
    public function statistics()
    {
        try {
            $totalRevenue = Order::where('payment_status', 'paid')->sum('total');
            $totalOrders = Order::count();
            $customerCount = User::where('is_admin', false)->count();

            $stats = [
                'total_orders' => $totalOrders,
                'pending_orders' => Order::where('status', 'pending')->count(),
                'processing_orders' => Order::where('status', 'processing')->count(),
                'completed_orders' => Order::where('status', 'delivered')->count(),
                'cancelled_orders' => Order::where('status', 'cancelled')->count(),
                'total_revenue' => $totalRevenue,
                'today_orders' => Order::whereDate('created_at', today())->count(),
                'today_revenue' => Order::whereDate('created_at', today())->where('payment_status', 'paid')->sum('total'),
                'customer_count' => $customerCount,
                'avg_order_value' => $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0,
            ];

            return response()->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error fetching statistics: ' . $e->getMessage()], 500);
        }
    }
}
