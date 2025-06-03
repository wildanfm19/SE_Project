<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    // Update Customer Biodata 
    public function updateCustomer(Request $request)
    {
        Log::info('Request method: ' . $request->method());
        Log::info('Request data: ' . json_encode($request->all()));


        $validator = Validator::make($request->all(), [
            'full_name' => 'sometimes|required|string|max:255',
            'birth_date' => 'sometimes|required|date',
            'phone_number' => 'sometimes|required|string|max:20',
            'email' => 'sometimes|required|string|email|max:255',
            'address' => 'sometimes|required|string|max:500',
            'profile_image' => 'sometimes|required|image|mimes:jpeg,png,jpg|max:2048',
            'gender' => 'sometimes|required|in:male,female,other',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => '101',
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!Auth::user()) {
            return response()->json([
                'code' => '102',
                'message' => 'Unauthorized access',
            ], 401);
        }

        $customer = Customer::where('user_id', Auth::id())->first();

        if (!$customer) {
            return response()->json([
                'code' => '102',
                'message' => 'Customer not found',
            ], 404);
        }

        $updatedFields = [];
        foreach ($request->all() as $key => $value) {
            if ($customer->isFillable($key)) {
                $customer->{$key} = $value;
                $updatedFields[] = $key;
            }
        }

        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            $path = $image->store('profiles/' . Auth::id(), 'public');
            $customer->profile_image = Storage::url($path);
            $updatedFields[] = 'profile_image';
        }

        if (!empty($updatedFields)) {
            $customer->save();
            return response()->json([
                'code' => '000',
                'message' => 'Customer biodata updated successfully!',
                'updated_fields' => $updatedFields
            ]);
        } else {
            return response()->json([
                'code' => '000',
                'message' => 'No changes were made to the customer biodata.'
            ]);
        }
    }

    // Get All Customers
    public function indexCustomer()
    {
        $customers = Customer::all();

        return response()->json([
            'code' => '000',
            'customers' => $customers
        ], 200);
    }

    // Get Single Customer by User ID
    public function showCustomer($userId)
    {
        // Mengambil pelanggan berdasarkan user_id
        $customer = Customer::where('user_id', $userId)->first();

        if (!$customer) {
            return response()->json([
                'code' => '102',
                'message' => 'Customer not found!'
            ], 404);
        }

        return response()->json([
            'code' => '000',
            'customer' => $customer
        ], 200);
    }

    // Update Seller Biodata
    public function updateSeller(Request $request)
    {
        Log::info('Request method: ' . $request->method());
        Log::info('Request data: ' . json_encode($request->all()));

        // Validasi input - buat validasi lebih fleksibel
        $validator = Validator::make($request->all(), [
            'store_name' => 'sometimes|string|max:255',
            'store_address' => 'sometimes|string|max:500',
            'store_logo' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'store_description' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => '101',
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek autentikasi
        if (!Auth::user()) {
            return response()->json([
                'code' => '102',
                'message' => 'Unauthorized access',
            ], 401);
        }

        // Cek seller
        $seller = Seller::where('user_id', Auth::id())->first();

        if (!$seller) {
            return response()->json([
                'code' => '102',
                'message' => 'Seller not found',
            ], 404);
        }

        try {
            $updatedFields = [];

            // Handle store logo update jika ada
            if ($request->hasFile('store_logo')) {
                // Delete old logo if exists
                if ($seller->store_logo && Storage::exists('public/' . $seller->store_logo)) {
                    Storage::delete('public/' . $seller->store_logo);
                }

                // Upload new logo
                $path = $request->file('store_logo')->store('sellers/' . Auth::id(), 'public');
                $seller->store_logo = $path;
                $updatedFields[] = 'store_logo';
            }

            // Handle text fields update jika ada
            $textFields = ['store_name', 'store_address', 'store_description'];
            foreach ($textFields as $field) {
                if ($request->has($field)) {
                    $seller->{$field} = $request->{$field};
                    $updatedFields[] = $field;
                }
            }

            // Jika ada field yang diupdate
            if (!empty($updatedFields)) {
                $seller->save();

                // Log successful update
                Log::info('Seller Updated:', [
                    'seller_id' => $seller->seller_id,
                    'updated_fields' => $updatedFields
                ]);

                // Prepare response data
                $responseData = [
                    'code' => '000',
                    'message' => 'Seller biodata updated successfully!',
                    'updated_fields' => $updatedFields,
                    'data' => [
                        'seller_id' => $seller->seller_id,
                        'store_name' => $seller->store_name,
                        'store_address' => $seller->store_address,
                        'store_description' => $seller->store_description,
                        'store_logo' => $seller->store_logo ? Storage::url($seller->store_logo) : null,
                        'store_rating' => $seller->store_rating,
                        'total_sales' => $seller->total_sales,
                        'updated_at' => $seller->updated_at
                    ]
                ];

                return response()->json($responseData);
            } else {
                return response()->json([
                    'code' => '000',
                    'message' => 'No changes were made to the seller biodata.'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Seller Update Error:', [
                'seller_id' => $seller->seller_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => '500',
                'message' => 'Failed to update seller biodata: ' . $e->getMessage()
            ], 500);
        }
    }

    // Get All Sellers
    public function indexSeller()
    {
        $sellers = Seller::all();

        return response()->json([
            'code' => '000',
            'sellers' => $sellers
        ], 200);
    }

    // Get Single Seller by ID
    public function showSeller($userId)
    {
        $seller = Seller::where('user_id', $userId)->first();

        if (!$seller) {
            return response()->json([
                'code' => '102',
                'message' => 'Seller not found!'
            ], 404);
        }

        return response()->json([
            'code' => '000',
            'seller' => $seller
        ], 200);
    }
}
