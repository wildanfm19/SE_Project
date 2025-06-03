<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSellerRole
{
    public function handle(Request $request, Closure $next)
    {
        // Cek apakah user memiliki role seller
        if (auth()->user()->role !== 'seller') {
            // Jika bukan seller, kembalikan response unauthorized
            return response()->json(['message' => 'Access Denied! Only sellers are allowed.'], 403);
        }

        return $next($request);  // Lanjutkan jika role adalah seller
    }
}
