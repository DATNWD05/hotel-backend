<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;

class CustomerController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Customer::class, 'customer');
    }
    public function index()
    {
        $data = Customer::paginate(10);

        return response()->json([
            'status' => 'success',
            'data'   => $data->items(),
            'meta'   => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ],
        ]);
    }

    public function show(Customer $customer)
    {
        $customer->load([
            'bookings.rooms.roomType.amenities',
            'bookings.services'
        ]);

        $bookingHistory = $customer->bookings->map(function ($booking) {
            return [
                'booking_id' => $booking->id,
                'check_in'   => $booking->check_in_date,
                'check_out'  => $booking->check_out_date,
                'rooms' => $booking->rooms->map(function ($room) {
                    return [
                        'room_number' => $room->room_number,
                        'room_type'   => $room->roomType->name ?? null,
                        'base_rate'   => $room->roomType->base_rate ?? null,
                        'amenities'   => $room->roomType->amenities->pluck('name') ?? [],
                    ];
                }),
                'services' => $booking->services->map(function ($service) {
                    return [
                        'name'     => $service->name,
                        'quantity' => $service->pivot->quantity,
                        'price'    => $service->price,
                        'total'    => $service->price * $service->pivot->quantity,
                    ];
                })
            ];
        });

        $customerData = $customer->toArray();
        $customerData['booking_history'] = $bookingHistory;

        return response()->json($customerData);
    }
    public function store(Request $request)
    {
        $data = $request->validate([
            'cccd' => [
                'required',
                'string',
                'regex:/^0\d{11}$/',
                'unique:customers,cccd',
            ],
            'name' => 'required|string|max:100',
            'gender' => 'nullable|in:male,female,other',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) {
                    if (Carbon::parse($value)->age < 18) {
                        $fail('Khách hàng phải từ 18 tuổi trở lên.');
                    }
                }
            ],
            'nationality' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $customer = Customer::create($data);
        return response()->json($customer, 201);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'cccd' => [
                'required',
                'string',
                'regex:/^0\d{11}$/',
                Rule::unique('customers', 'cccd')->ignore($customer->id),
            ],
            'name' => 'sometimes|required|string|max:100',
            'gender' => 'nullable|in:male,female,other',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) {
                    if (Carbon::parse($value)->age < 18) {
                        $fail('Khách hàng phải từ 18 tuổi trở lên.');
                    }
                }
            ],
            'nationality' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'note' => 'nullable|string',
            'cccd_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // ảnh tối đa 2MB
        ]);

        // Nếu có upload ảnh mới
        if ($request->hasFile('cccd_image')) {
            // Xóa ảnh cũ nếu có
            if (!empty($customer->cccd_image_path) && Storage::disk('public')->exists($customer->cccd_image_path)) {
                Storage::disk('public')->delete($customer->cccd_image_path);
            }

            // Lưu ảnh mới và gán đường dẫn vào data
            $data['cccd_image_path'] = $request->file('cccd_image')->store('cccd_images', 'public');
        }

        $customer->update($data);

        return response()->json([
            'message' => 'Cập nhật khách hàng thành công',
            'data' => $customer
        ]);
    }

    // Kiểm tra tồn tại theo CCCD
    public function checkCccd($cccd)
    {
        $customer = Customer::where('cccd', $cccd)->first();

        if ($customer) {
            return response()->json([
                'status' => 'exists',
                'data' => $customer
            ]);
        }

        return response()->json([
            'status' => 'not_found',
            'message' => 'Không tìm thấy khách hàng với CCCD này.'
        ], 404);
    }
}
