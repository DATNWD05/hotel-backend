<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserController extends Controller
{
    use AuthorizesRequests;

    // public function __construct()
    // {
    //     $this->authorizeResource(User::class, 'user');
    // }
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10); // mặc định 10 dòng mỗi trang

        $users = User::with('role:id,name')
            ->select('id', 'name', 'email', 'role_id', 'status', 'created_at')
            ->paginate($perPage);

        // Sử dụng map để thêm tên vai trò
        $users->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'role' => $user->role->name ?? null,
                'status' => $user->status,
                'created_at' => $user->created_at,
            ];
        });

        return response()->json([
            'data' => $users->items(),
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(),
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
            'face_image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048', // validate ảnh
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Lưu user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => $request->role_id,
            ]);

            // Xử lý upload ảnh nếu có
            $imagePath = null;
            if ($request->hasFile('face_image')) {
                $image = $request->file('face_image');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/employees'), $imageName);
                $imagePath = 'uploads/employees/' . $imageName;
            }

            // Lưu employee
            $employee = Employee::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'email' => $request->email,
                'role_id' => $request->role_id,
                'birthday' => $request->birthday,
                'gender' => $request->gender,
                'phone' => $request->phone,
                'address' => $request->address,
                'hire_date' => $request->hire_date,
                'department_id' => $request->department_id,
                'status' => $request->status,
                'cccd' => $request->cccd,
                'face_image' => $imagePath, // lưu đường dẫn ảnh
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



    public function show(User $user)
    {
        return response()->json([
            'data' => $user
        ], 200);
    }

    public function profile()
    {
        $user = Auth::user();
        $employee = $user->employee;

        return response()->json([
            'user'     => $user
        ]);
    }


    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => 'nullable|min:6',
            'role_id' => 'sometimes|exists:roles,id',
            'status' => 'sometimes|in:active,not_active',
        ]);

        $data = $request->only(['name', 'email', 'role_id', 'status']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Cập nhật người dùng thành công.',
            'user' => $user
        ], 200);
    }

    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'message' => 'Xóa người dùng thành công'
        ], 200);
    }
}
