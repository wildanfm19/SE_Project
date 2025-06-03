<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class AddressController extends Controller
{
    /**
     * Get all addresses
     * GET /addresses
     */
    public function index()
    {
        if (!Auth::check()) {
            return response()->json([
                'code' => '401',
                'status' => 'error',
                'message' => 'Unauthorized access, please login first'
            ], 401);
        }

        try {
            $addresses = Address::where('user_id', Auth::id())->get();

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'data' => $addresses
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching addresses: ' . $e->getMessage());
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to fetch addresses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new address
     * POST /addresses
     */
    public function store(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'code' => '401',
                'status' => 'error',
                'message' => 'Unauthorized access, please login first'
            ], 401);
        }

        try {
            // Validasi request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'address' => 'required|string',
                'district_id' => 'required|exists:districts,district_id',
                'poscode_id' => 'required|exists:pos_codes,poscode_id',
                'phone_number' => 'required|string|max:15',
                'is_main' => 'boolean',
                'biteship_id' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => '422',
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Jika alamat baru adalah alamat utama, set semua alamat lain menjadi tidak utama
            if ($request->is_main) {
                Address::where('user_id', Auth::id())
                    ->update(['is_main' => false]);
            }

            // Simpan alamat ke database
            $address = Address::create(array_merge($request->all(), ['user_id' => Auth::id()]));

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'message' => 'Address created successfully',
                'data' => $address
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error storing address: ' . $e->getMessage());
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to create address',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get address by ID
     * GET /addresses/{id}
     */
    public function show($address_id)
    {
        if (!Auth::check()) {
            return response()->json([
                'code' => '401',
                'status' => 'error',
                'message' => 'Unauthorized access, please login first'
            ], 401);
        }

        try {
            $address = Address::where('address_id', $address_id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'data' => $address
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'code' => '404',
                'status' => 'error',
                'message' => 'Address not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching address: ' . $e->getMessage());
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to fetch address',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update address
     * PUT /addresses/{id}
     */
    public function update(Request $request, $address_id)
    {
        if (!Auth::check()) {
            return response()->json([
                'code' => '401',
                'status' => 'error',
                'message' => 'Unauthorized access, please login first'
            ], 401);
        }

        try {
            $address = Address::where('address_id', $address_id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            // Validasi request
            $validator = Validator::make($request->all(), [
                'name' => 'string|max:255',
                'address' => 'string',
                'district_id' => 'exists:districts,district_id',
                'poscode_id' => 'exists:pos_codes,poscode_id',
                'phone_number' => 'string|max:15',
                'is_main' => 'boolean',
                'biteship_id' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => '422',
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Jika alamat ini diatur sebagai utama, set semua alamat lain menjadi tidak utama
            if ($request->is_main) {
                Address::where('user_id', Auth::id())
                    ->update(['is_main' => false]);
            }

            // Update alamat
            $address->update($request->all());

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'message' => 'Address updated successfully',
                'data' => $address
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'code' => '404',
                'status' => 'error',
                'message' => 'Address not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error updating address: ' . $e->getMessage());
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to update address',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete address
     * DELETE /addresses/{id}
     */
    public function destroy($address_id)
    {
        if (!Auth::check()) {
            return response()->json([
                'code' => '401',
                'status' => 'error',
                'message' => 'Unauthorized access, please login first'
            ], 401);
        }

        try {
            $address = Address::where('address_id', $address_id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $address->delete();

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'message' => 'Address deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'code' => '404',
                'status' => 'error',
                'message' => 'Address not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting address: ' . $e->getMessage());
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to delete address',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set address as main
     * PUT /addresses/{id}/main
     */
    public function setAsMain($address_id)
    {
        if (!Auth::check()) {
            return response()->json([
                'code' => '401',
                'status' => 'error',
                'message' => 'Unauthorized access, please login first'
            ], 401);
        }

        try {
            $address = Address::where('address_id', $address_id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            // Set semua alamat lain menjadi tidak utama
            Address::where('user_id', Auth::id())
                ->update(['is_main' => false]);

            // Set alamat ini sebagai utama
            $address->update(['is_main' => true]);

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'message' => 'Address set as main successfully',
                'data' => $address
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'code' => '404',
                'status' => 'error',
                'message' => 'Address not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error setting address as main: ' . $e->getMessage());
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to set address as main',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all districts
     * GET /addresses/districts
     */
    public function getDistricts()
    {
        try {
            $districts = \App\Models\District::select('district_id', 'district_name')->get();
            
            return response()->json([
                'code' => '000',
                'status' => 'success',
                'data' => $districts
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching districts: ' . $e->getMessage());
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to fetch districts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pos codes by district
     * GET /addresses/districts/{district_id}/poscodes
     */
    public function getPosCodesByDistrict($districtId)
    {
        try {
            $posCodes = \App\Models\PosCode::where('district_id', $districtId)
                ->select('poscode_id', 'code')
                ->get();
            
            if ($posCodes->isEmpty()) {
                return response()->json([
                    'code' => '404',
                    'status' => 'error',
                    'message' => 'No pos codes found for this district'
                ], 404);
            }

            return response()->json([
                'code' => '000',
                'status' => 'success',
                'data' => $posCodes
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching pos codes: ' . $e->getMessage());
            return response()->json([
                'code' => '500',
                'status' => 'error',
                'message' => 'Failed to fetch pos codes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
