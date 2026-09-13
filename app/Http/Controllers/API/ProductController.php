<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    // Get all products (with filters)
    public function index(Request $request)
    {
        $query = Product::with('category');

        $isAdmin = auth('sanctum')->user()?->is_admin === true;
        if (!$isAdmin) {
            $query->where('is_active', true);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->is_featured);
        }
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }
        // ⬇️ Gender filter
        if ($request->has('gender')) {
            $query->where('gender', $request->gender);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = strtolower($request->get('sort_order', 'desc'));
        $sortBy = in_array($sortBy, ['name', 'price', 'stock', 'created_at'], true) ? $sortBy : 'created_at';
        $sortOrder = in_array($sortOrder, ['asc', 'desc'], true) ? $sortOrder : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 12);
        $products = $query->paginate($perPage);

        return response()->json(['success' => true, 'data' => $products]);
    }

    // Get single product
    public function show($id)
    {
        $product = Product::with('category')->findOrFail($id);
        $product->incrementViews();
        return response()->json(['success' => true, 'data' => $product]);
    }

    // Create product
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            // ✅ removed lt:price to allow sale_price > price
            'sale_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'sku' => 'nullable|string|unique:products',
            'category_id' => 'required|exists:categories,id',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'colors' => 'nullable|array',
            'colors.*' => 'string|max:50',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            // ⬇️ Gender field validation
            'gender' => 'nullable|in:men,women,unisex'
        ]);

        $data = $request->all();

        if ($request->hasFile('images')) {
            $imagePaths = [];
            foreach ($request->file('images') as $image) {
                $imagePaths[] = $image->store('products', 'public');
            }
            $data['images'] = $imagePaths;
        }

        $product = Product::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product
        ], 201);
    }

    // Update product
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            // ✅ removed lt:price
            'sale_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'sku' => 'nullable|string|unique:products,sku,' . $id,
            'category_id' => 'required|exists:categories,id',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'colors' => 'nullable|array',
            'colors.*' => 'string|max:50',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            // ⬇️ Gender for update
            'gender' => 'nullable|in:men,women,unisex'
        ]);

        $data = $request->except('images');

        if ($request->hasFile('images')) {
            if ($product->images) {
                foreach ($product->images as $oldImage) {
                    Storage::disk('public')->delete($oldImage);
                }
            }
            $imagePaths = [];
            foreach ($request->file('images') as $image) {
                $imagePaths[] = $image->store('products', 'public');
            }
            $data['images'] = $imagePaths;
        }

        $product->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product
        ]);
    }

    public function updateStock(Request $request, $id)
    {
        $data = $request->validate([
            'stock' => 'required|integer|min:0',
        ]);

        $product = Product::findOrFail($id);
        $product->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Stock updated successfully',
            'data' => $product,
        ]);
    }

    // Delete product
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        if ($product->images) {
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image);
            }
        }
        $product->delete();
        return response()->json(['success' => true, 'message' => 'Product deleted']);
    }

    // Featured products (unchanged)
    public function featured()
    {
        $products = Product::with('category')
            ->featured()
            ->active()
            ->inStock()
            ->limit(8)
            ->get();
        return response()->json(['success' => true, 'data' => $products]);
    }

    // Latest products (unchanged)
    public function latest()
    {
        $products = Product::with('category')
            ->active()
            ->inStock()
            ->latest()
            ->limit(12)
            ->get();
        return response()->json(['success' => true, 'data' => $products]);
    }

    // Related products (unchanged)
    public function related($id)
    {
        $product = Product::findOrFail($id);
        $related = Product::with('category')
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $id)
            ->active()
            ->inStock()
            ->limit(4)
            ->get();
        return response()->json(['success' => true, 'data' => $related]);
    }
}
