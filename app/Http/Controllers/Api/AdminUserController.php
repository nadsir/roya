<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->withCount('orders')
            ->latest('created_at');

        if ($search = $request->input('search')) {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $users = $query->paginate(20)->through(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'orders_count' => $user->orders_count,
                'created_at' => $user->created_at,
            ];
        });

        return response()->json($users);
    }

    public function show(Request $request, string $user): JsonResponse
    {
        $userModel = User::query()
            ->withCount('orders')
            ->with(['orders' => function ($q) {
                $q->select('id', 'status', 'total', 'created_at')
                    ->latest('created_at')
                    ->limit(10);
            }])
            ->findOrFail($user);

        $data = $userModel->only([
            'id', 'name', 'email', 'mobile', 'role', 'is_active',
            'created_at', 'orders_count',
        ]);

        $data['orders'] = $userModel->orders->map(function ($order) {
            return [
                'id' => $order->id,
                'status' => $order->status,
                'total' => $order->total,
                'created_at' => $order->created_at,
            ];
        })->values();

        return response()->json(['user' => $data]);
    }

    public function updateStatus(Request $request, string $user): JsonResponse
    {
        $userModel = User::findOrFail($user);

        if ($userModel->role === 'admin') {
            throw ValidationException::withMessages([
                'status' => 'امکان تغییر وضعیت مدیر وجود ندارد.',
            ]);
        }

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $userModel->update(['is_active' => $validated['is_active']]);

        return response()->json([
            'message' => 'وضعیت کاربر با موفقیت به‌روزرسانی شد.',
            'user' => [
                'id' => $userModel->id,
                'is_active' => $userModel->is_active,
            ],
        ]);
    }
}