<?php
// app/Http/Requests/UpdateBookingRequest.php

namespace App\Http\Requests\Bookings;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        // Kiểm quyền update nếu cần:
        // return auth()->user()->can('update', $this->route('booking'));
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'    => 'sometimes|required|exists:customers,id',
            'check_in_date'  => 'sometimes|required|date|after_or_equal:today',
            'check_out_date' => 'sometimes|required|date|after:check_in_date',
            'status'         => 'sometimes|required|in:Pending,Confirmed,Checked-in,Checked-out,Canceled',
            'deposit_amount' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.exists'      => 'Khách hàng không hợp lệ.',
            'check_out_date.after'    => 'Ngày trả phải sau ngày nhận.',
            'status.in'               => 'Trạng thái không hợp lệ.',
            'deposit_amount.min'      => 'Số tiền đặt cọc phải >= 0.',
        ];
    }
}
