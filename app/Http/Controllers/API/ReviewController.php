<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    // Get reviews for a specific product (public)
    public function getForProduct($productId)
    {
        $reviews = Review::where('product_id', $productId)
                         ->with('user')
                         ->latest()
                         ->get();
        return response()->json($reviews);
    }

    // Store a new review (authenticated)
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating'     => 'required|integer|min:1|max:5',
            'comment'    => 'required|string|max:1000',
        ]);

        // Check if user already reviewed this product
        $existing = Review::where('user_id', Auth::id())
                          ->where('product_id', $request->product_id)
                          ->first();
        if ($existing) {
            return response()->json(['message' => 'You have already reviewed this product.'], 422);
        }

        $review = Review::create([
            'user_id'     => Auth::id(),
            'product_id'  => $request->product_id,
            'rating'      => $request->rating,
            'comment'     => $request->comment,
            'is_approved' => false, // pending approval
        ]);

        return response()->json($review, 201);
    }

    // Admin: get all reviews (with optional filters)
    public function index(Request $request)
    {
        $query = Review::with(['user', 'product']);

        // Filter by status (pending, approved, rejected)
        if ($request->has('status')) {
            if ($request->status === 'pending') {
                $query->where('is_approved', false);
            } elseif ($request->status === 'approved') {
                $query->where('is_approved', true);
            } elseif ($request->status === 'rejected') {
                // If you have a separate column for rejected, add it.
                // For now, reject = not approved and we ignore? Better add status column.
                // We'll assume a "status" column exists? Simpler: treat rejected as is_approved = false and a separate 'rejected_at'? For demo, let's return empty.
                $query->whereRaw('1 = 0'); // no results
            }
        }

        $reviews = $query->latest()->paginate(15);

        // Transform each review to include a 'status' field
        $reviews->getCollection()->transform(function ($review) {
            if ($review->is_approved) {
                $review->status = 'approved';
            } else {
                // If you want to support rejected, you need a separate column
                $review->status = 'pending';
            }
            return $review;
        });

        return response()->json($reviews);
    }

    // Admin: approve a review (set is_approved = true)
    public function approve($id)
    {
        $review = Review::findOrFail($id);
        $review->update(['is_approved' => true]);
        $review->status = 'approved';
        return response()->json($review);
    }

    // Admin: reject a review (you can either delete or set a rejected flag)
    public function reject($id)
    {
        $review = Review::findOrFail($id);
        // Option 1: delete it
        $review->delete();
        // Option 2: set a rejected column (if added)
        return response()->json(['message' => 'Review rejected']);
    }

    // Admin: delete review
    public function destroy($id)
    {
        $review = Review::findOrFail($id);
        $review->delete();
        return response()->json(['message' => 'Review deleted']);
    }
}