<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all(); // or paginate
        return response()->json(['data' => $users]);
    }
    public function customers()
    {
        $customers = User::where('is_admin', false)
            ->withCount('orders')
            ->latest()
            ->get();

        return response()->json(['data' => $customers]);
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json(['data' => $user]);
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->is_admin, 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'is_active' => 'required|boolean',
        ]);
        $user->update($data);

        if (!$user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'User updated', 'data' => $user]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }
}
