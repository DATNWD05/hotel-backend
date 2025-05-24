<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
{
    $users = User::with('role:id,name') // lấy kèm vai trò
        ->select('id', 'name', 'email', 'role_id', 'created_at')
        ->get()
        ->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->name ?? null,
                'created_at' => $user->created_at,
            ];
        });

    return response()->json([
        'data' => $users
    ]);
}

public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:100',
        'email' => 'required|email|unique:users,email',
        'role_id' => 'required|exists:roles,id',
    ]);

    $defaultPassword = 'password123'; 

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($defaultPassword),
        'role_id' => $request->role_id,
    ]);

    return response()->json([
        'message' => 'Tạo người dùng thành công.',
        'user' => $user
    ], 201);
}


    public function show(string $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Không tìm thấy người dùng'
            ], 404);
        }

        return response()->json([
            'data' => $user
        ], 200);
    }

  public function update(Request $request, string $id)
{
    $request->validate([
        'name' => 'sometimes|string|max:100',
        'email' => 'sometimes|email|unique:users,email,' . $id,
        'password' => 'nullable|min:6',
        'role_id' => 'sometimes|exists:roles,id'
    ]);

    $user = User::find($id);

    if (!$user) {
        return response()->json([
            'message' => 'Không tìm thấy người dùng'
        ], 404);
    }

    $user->name = $request->name ?? $user->name;
    $user->email = $request->email ?? $user->email;
    $user->role_id = $request->role_id ?? $user->role_id;

    if ($request->filled('password')) {
        $user->password = Hash::make($request->password);
    }

    $user->save();

    return response()->json([
        'message' => 'Cập nhật người dùng thành công.',
        'user' => $user
    ], 200);
}



    public function destroy(string $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Không tìm thấy người dùng'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'message' => 'Xóa người dùng thành công'
        ], 200);
    }
}
