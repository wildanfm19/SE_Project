<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    protected $primaryKey = 'chat_id';

    protected $fillable = [
        'room_id',
        'sender_type',
        'sender_id',
        'receiver_type',
        'receiver_id',
        'message',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function room()
    {
        return $this->belongsTo(ChatRoom::class, 'room_id', 'room_id');
    }

    public function sender()
    {
        return $this->morphTo('sender', 'sender_type', 'sender_id')->withDefault();
    }

    public function receiver()
    {
        return $this->morphTo('receiver', 'receiver_type', 'receiver_id')->withDefault();
    }
}
