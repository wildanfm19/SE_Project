<?php
// app/Models/Customer.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Customer extends Model
{
    use HasFactory;
    protected $primaryKey = 'customer_id';

    protected $fillable = [
        'user_id',
        'full_name',
        'birth_date',
        'phone_number',
        'email',
        'address',
        'profile_image',
        'gender',
        // ... tambahkan field lain yang perlu diupdate
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function chatRooms()
    {
        return $this->hasMany(ChatRoom::class, 'customer_id', 'customer_id');
    }

    public function sentMessages(): MorphMany
    {
        return $this->morphMany(Chat::class, 'sender');
    }

    public function receivedMessages(): MorphMany
    {
        return $this->morphMany(Chat::class, 'receiver');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }
}
