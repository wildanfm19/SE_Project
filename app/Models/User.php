<?php
// app/Models/User.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $primaryKey = 'user_id';

    protected $fillable = ['username', 'password', 'role', 'status'];

    protected $hidden = ['password', 'remember_token'];

    // Relationship to Seller model
    public function seller()
    {
        return $this->hasOne(Seller::class, 'user_id');
    }

    // Relationship to Customer model
    public function customer()
    {
        return $this->hasOne(Customer::class, 'user_id', 'user_id');
    }

    // Method to check if user is a seller
    public function isSeller()
    {
        return $this->role === 'seller';
    }

    // Method to check if user is a customer
    public function isCustomer()
    {
        return $this->role === 'customer';
    }

    // Method to check if user is an admin
    public function isAdmin()
    {
        return $this->role === 'admin';
    }
}
