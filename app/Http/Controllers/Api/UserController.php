<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,id',
            'birthday' => 'nullable|date',
            'gender' => 'nullable|string|in:Nam,Nữ,Khác',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'department_id' => 'nullable|exists:departments,id',
            'status' => 'required|string',
            'cccd' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => $request->role_id,
            ]);

            $employee = Employee::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'email' => $request->email,
                'role' => $user->role->name ?? 'Receptionist',
                'birthday' => $request->birthday,
                'gender' => $request->gender,
                'phone' => $request->phone,
                'address' => $request->address,
                'hire_date' => $request->hire_date,
                'department_id' => $request->department_id,
                'status' => $request->status,
                'cccd' => $request->cccd,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Tạo tài khoản và nhân viên thành công.',
                'user' => $user,
                'employee' => $employee,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Lỗi khi tạo dữ liệu.',
                'error' => $e->getMessage()
            ], 500);
        }
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
    $user = User::find($id);

    if (!$user) {
        return response()->json([
            'message' => 'Không tìm thấy người dùng'
        ], 404);
    }

    $request->validate([
        'name' => 'sometimes|string|max:100',
        'email' => ['sometimes','email',
            Rule::unique('users')->ignore($user->id),
        ],
        'password' => 'nullable|min:6',
        'role_id' => 'sometimes|exists:roles,id',
    ]);

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
