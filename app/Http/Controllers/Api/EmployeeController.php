<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreEmployeeRequest;
use App\Http\Requests\Employees\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeFace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    use AuthorizesRequests;

    // public function __construct()
    // {
    //     $this->authorizeResource(Employee::class, parameter: 'employees');
    // }
    public function index()
    {
        $data = Employee::with(['department', 'role', 'user'])->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'total_pages' => $data->lastPage(),
                'total_items' => $data->total(),
                'per_page' => $data->perPage(),
            ],
        ]);
    }


    public function store(StoreEmployeeRequest $request)
    {
        try {
            $employee = Employee::create($request->validated());

            return response()->json([
                'status' => 'success',
                'data' => [
                    'employee' => $employee->load('department'),
                    'employee_id' => $employee->id, // THÊM DÒNG NÀY
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Lỗi hệ thống khi thêm nhân viên: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi hệ thống.',
            ], 500);
        }
    }

    public function show(Employee $employee)
    {
        return response()->json([
            'status' => 'success',
            'data' => $employee->load('department'),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        try {
            $data = $request->validated();

            // Nếu có ảnh mới
            if ($request->hasFile('face_image')) {
                // Xoá ảnh cũ nếu tồn tại
                if ($employee->face_image && file_exists(public_path($employee->face_image))) {
                    unlink(public_path($employee->face_image));
                }

                $image = $request->file('face_image');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();

                $destination = public_path('uploads/employees');

                if (!file_exists($destination)) {
                    mkdir($destination, 0755, true);
                }

                $image->move($destination, $imageName);

                // Lưu đường dẫn tương đối để dùng asset()
                $data['face_image'] = 'uploads/employees/' . $imageName;
            }

            // Cập nhật thông tin
            $employee->update($data);

            return response()->json([
                'status' => 'success',
                'data' => $employee->load('department'),
            ]);
        } catch (\Exception $e) {
            Log::error('Lỗi hệ thống khi cập nhật nhân viên: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi hệ thống.',
            ], 500);
        }
    }



    public function destroy(Employee $employee)
    {
        try {
            if (Department::where('manager_id', $employee->id)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Không thể xóa nhân viên đang là quản lý phòng ban.',
                ], 400);
            }
            $employee->delete();
            return response()->json([], 204);
        } catch (\Exception $e) {
            Log::error('Lỗi khi xóa nhân viên: ' . $e->getMessage() . ' - ID: ' . $employee->id);
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi hệ thống.',
            ], 500);
        }
    }

    // Phương thức để upload ảnh khuôn mặt
    public function uploadFaces(Request $request, Employee $employee)
    {
        $request->validate([
            'images' => 'required|array',
            'images.*' => 'required|string' // base64 encoded strings
        ]);

        $saved = [];

        foreach ($request->input('images') as $base64Image) {
            // Làm sạch chuỗi base64 (xóa tiền tố và chuẩn hóa)
            $base64Image = preg_replace('#^data:image/\w+;base64,#i', '', $base64Image);
            $base64Image = str_replace(' ', '+', $base64Image);

            // Tạo tên file ngẫu nhiên
            $fileName = 'faces/' . Str::random(40) . '.jpg';

            // Lưu file ảnh vào storage/public/faces
            Storage::disk('public')->put($fileName, base64_decode($base64Image));

            // Lưu đường dẫn ảnh vào DB
            EmployeeFace::create([
                'employee_id' => $employee->id,
                'image_path' => $fileName,
            ]);

            $saved[] = $fileName;
        }

        return response()->json([
            'success' => true,
            'message' => 'Thu thập khuôn mặt từ camera thành công.',
            'files' => $saved,
        ]);
    }
}
