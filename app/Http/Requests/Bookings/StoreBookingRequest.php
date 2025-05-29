<?php
// app/Http/Requests/StoreBookingRequest.php

namespace App\Http\Requests\Bookings;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        // Bạn có thể kiểm tra quyền ở đây, ví dụ:
        // return auth()->user()->can('create', Booking::class);
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'    => 'required|exists:customers,id',
            'created_by'     => 'required|exists:users,id',
            'check_in_date'  => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'status'         => 'required|in:Pending,Confirmed,Checked-in,Checked-out,Canceled',
            'deposit_amount' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required'    => 'Bạn phải chọn khách hàng.',
            'customer_id.exists'      => 'Khách hàng không hợp lệ.',
            'created_by.required'     => 'Bạn phải chỉ định người tạo booking.',
            'created_by.exists'       => 'Nhân viên không hợp lệ.',
            'check_in_date.required'  => 'Ngày nhận phòng là bắt buộc.',
            'check_out_date.required' => 'Ngày trả phòng là bắt buộc.',
            'check_out_date.after'    => 'Ngày trả phải sau ngày nhận.',
            'status.in'               => 'Trạng thái không hợp lệ.',
            'deposit_amount.min'      => 'Số tiền đặt cọc phải >= 0.',
        ];
    }
}
