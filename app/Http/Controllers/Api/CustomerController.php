<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;

class CustomerController extends Controller
{
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
        $customer = Customer::find($id);
        if (!$customer) {
            return response()->json(['message' => 'Không tìm thấy khách hàng'], 404);
        }
        return response()->json($customer);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cccd'          => 'required|string|size:12|unique:customers,cccd',
            'name'    => 'required|string|max:100',
            'gender'        => 'nullable|in:male,female,other',
            'email'         => 'nullable|email|max:255',
            'phone'         => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'nationality'   => 'nullable|string|max:50',
            'address'       => 'nullable|string',
            'note'          => 'nullable|string',
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
            'cccd'          => [
                'required',
                'string',
                'size:12',
                Rule::unique('customers', 'cccd')->ignore($customer->id), // bỏ qua chính nó
            ],
            'name'    => 'sometimes|required|string|max:100',
            'gender'        => 'nullable|in:male,female,other',
            'email'         => 'nullable|email|max:255',
            'phone'         => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'nationality'   => 'nullable|string|max:50',
            'address'       => 'nullable|string',
            'note'          => 'nullable|string',
        ]);

        $customer->update($data);
        return response()->json($customer);
    }
}
