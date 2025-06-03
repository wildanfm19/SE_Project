<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Customer;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{

    // Register Customer
    public function registerCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string',
            'username' => 'required|string|unique:users',
            'birth_date' => 'required|date',
            'phone_number' => 'required|string|unique:customers',
            'email' => 'required|string|email|unique:customers',
            'password' => 'required|string|min:6',
        ]);

        // Handle validation errors
        if ($validator->fails()) {
            return response()->json([
                'code' => '101',
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Create user for customer
            $user = User::create([
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'role' => 'customer',
                'status' => 'active',
            ]);

            // Create customer profile
            $customer = Customer::create([
                'user_id' => $user->user_id,
                'full_name' => $request->full_name,
                'birth_date' => $request->birth_date,
                'phone_number' => $request->phone_number,
                'email' => $request->email,
            ]);

            // Generate token for the new customer
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'code' => '000',
                'message' => 'Customer registered successfully!',
                'token' => $token,
                'user' => $user,
                'customer_profile' => $customer,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'code' => '500',
                'message' => 'Failed to register customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Login
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Ambil user berdasarkan username
        $user = User::where('username', $request->username)->first();
        
        if ($user) {
            // Cek apakah password yang di-input cocok dengan password yang di-hash di database
            if (Hash::check($request->password, $user->password)) {
                $token = $user->createToken('auth_token')->plainTextToken;
                return response()->json([
            'code' => '000',
                    'message' => 'Login successful!',
                    'token' => $token,
                    'user' => $user,
                ], 200);
            } else {
                return response()->json([
            'code' => '101',
                    'message' => 'Invalid password!',
                ], 401);
            }
        }

        return response()->json([
            'code' => '102',
            'message' => 'User not found!',
        ], 401);
    }

    public function logout(Request $request)
    {
        // Menghapus token autentikasi yang sedang digunakan
        $request->user()->currentAccessToken()->delete();

        return response()->json([
        'code' => '000',
            'message' => 'Logout successful!'
        ], 200);
    }

    public function getSellerProfile(Request $request)
    {
        // Mengambil user yang sedang login berdasarkan token
        $user = $request->user();

        // Mengambil profil berdasarkan peran user
        if ($user->role === 'seller') {
            $profile = Seller::where('user_id', $user->user_id)->first();
            if (!$profile) {
                return response()->json([
                    'code' => '404',
                    'message' => 'Seller profile not found!',
                ], 404);
            }
        } elseif ($user->role === 'customer') {
            $profile = Customer::where('user_id', $user->user_id)->first();
            if (!$profile) {
                return response()->json([
                    'code' => '404',
                    'message' => 'Customer profile not found!',
                ], 404);
            }
        } else {
            return response()->json([
                'code' => '403',
                'message' => 'Access denied! Only sellers and customers can access this resource.',
            ], 403);
        }

        return response()->json([
            'code' => '000',
            'role' => $user->role,
            'profile' => $profile,
        ], 200);
    }
}


