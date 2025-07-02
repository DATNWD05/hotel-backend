<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

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

    public function show($id)
    {
        $customer = Customer::with([
            'bookings.rooms.roomType.amenities',
            'bookings.services'
        ])->find($id);

        if (!$customer) {
            return response()->json(['message' => 'Không tìm thấy khách hàng'], 404);
        }

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

        // Gộp thông tin khách hàng và lịch sử đặt phòng
        $customerData = $customer->toArray(); // lấy toàn bộ thông tin khách hàng gốc
        $customerData['booking_history'] = $bookingHistory; // thêm trường lịch sử

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

    public function update(Request $request, $id)
    {
        $customer = Customer::find($id);
        if (!$customer) {
            return response()->json(['message' => 'Không tìm thấy khách hàng'], 404);
        }

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
        ]);

        $customer->update($data);
        return response()->json($customer);
    }

    public function checkCccd($cccd)
    {
        $customer = Customer::where('cccd', $cccd)->first();

        if ($customer) {
            return response()->json([
                'status' => 'exists',
                'data' => $customer
            ], 200);
        }

        return response()->json([
            'status' => 'not_found',
            'message' => 'Không tìm thấy khách hàng với CCCD này.'
        ], 404);
    }
}
