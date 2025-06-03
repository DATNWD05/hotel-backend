<?php

namespace App\Http\Requests\Employees;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
{
    return [
        'name' => 'required|string|max:100',
        'email' => 'required|email|unique:employees,email',
        'phone' => 'nullable|string|max:20',
        'birthday' => 'nullable|date',
        'gender' => 'nullable|in:Nam,Nữ,Khác',
        'address' => 'nullable|string|max:255',
        'cccd' => 'nullable|regex:/^[0-9]+$/|min:10|max:12',
        'department_id' => 'required|exists:departments,id',
        'hire_date' => 'required|date',
        'status' => 'required|in:active,inactive',
        'role' => 'required|in:staff,admin,user',
    ];
}

public function messages(): array
{
    return [
        'name.required' => 'Tên nhân viên không được để trống.',
        'name.string' => 'Tên phải là chuỗi ký tự.',
        'name.max' => 'Tên không được vượt quá 100 ký tự.',

        'email.required' => 'Email không được để trống.',
        'email.email' => 'Email không đúng định dạng.',
        'email.unique' => 'Email đã tồn tại trong hệ thống.',

        'phone.string' => 'Số điện thoại phải là chuỗi ký tự.',
        'phone.max' => 'Số điện thoại không được vượt quá 20 ký tự.',

        'birthday.date' => 'Ngày sinh không đúng định dạng.',

        'gender.in' => 'Giới tính phải là Nam, Nữ hoặc Khác.',

        'address.string' => 'Địa chỉ phải là chuỗi ký tự.',
        'address.max' => 'Địa chỉ không được vượt quá 255 ký tự.',

        'cccd.regex' => 'CCCD chỉ được chứa các chữ số.',
        'cccd.min' => 'CCCD phải có ít nhất 10 chữ số.',
        'cccd.max' => 'CCCD không được vượt quá 12 chữ số.',

        'department_id.required' => 'Phòng ban không được để trống.',
        'department_id.exists' => 'Phòng ban không tồn tại.',

        'hire_date.required' => 'Ngày tuyển dụng không được để trống.',
        'hire_date.date' => 'Ngày tuyển dụng không đúng định dạng.',

        'status.required' => 'Trạng thái không được để trống.',
        'status.in' => 'Trạng thái phải là active hoặc inactive.',

        'role.required' => 'Vai trò không được để trống.',
        'role.in' => 'Vai trò phải là staff, admin hoặc user.',
    ];
}

}
