<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $users = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 10));

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string|max:50|unique:users',
            'name' => 'required|string|max:100',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,operator',
            'status' => 'in:active,inactive',
        ]);

        $user = User::create([
            'username' => $request->username,
            'name' => $request->name,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'status' => $request->input('status', 'active'),
        ]);

        return response()->json($user, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $request->validate([
            'username' => 'sometimes|string|max:50|unique:users,username,' . $id,
            'name' => 'sometimes|string|max:100',
            'password' => 'sometimes|string|min:6',
            'role' => 'sometimes|in:admin,operator',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $data = $request->only(['username', 'name', 'role', 'status']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json($user);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($request->user()->id === $id) {
            return response()->json(['message' => 'Cannot delete yourself'], 422);
        }

        $user = User::findOrFail($id);

        if ($user->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return response()->json(['message' => 'Cannot delete the last admin'], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'User deleted']);
    }
}
