<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Seller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class SellerSeeder extends Seeder
{
    public function run()
    {
        $seller = [
            'username' => 'nabeal',
            'password' => '172425',
           
        ];

        DB::beginTransaction();
        
        try {
            // Cek username unik
            if (User::where('username', $seller['username'])->exists()) {
                $this->command->error('Username ' . $seller['username'] . ' sudah digunakan!');
                return;
            }

            // Buat user
            $user = User::create([
                'username' => $seller['username'],
                'password' => Hash::make($seller['password']),
                'role' => 'seller',
                'status' => 'active',
            ]);

            // Buat seller profile
            Seller::create([
                'user_id' => $user->user_id,
                
            ]);

            DB::commit();
            $this->command->info('Seller nabeal berhasil ditambahkan');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error: ' . $e->getMessage());
        }
    }
}
