<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\ChatRoom;
use App\Models\Customer;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function getRooms(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if ($user->seller) {
            $rooms = ChatRoom::where('seller_id', $user->seller->seller_id)
                ->with(['customer.user', 'lastMessage'])
                ->orderBy('last_message_time', 'desc')
                ->paginate(20);
        } else {
            $rooms = ChatRoom::where('customer_id', $user->customer->customer_id)
                ->with(['seller.user', 'lastMessage'])
                ->orderBy('last_message_time', 'desc')
                ->paginate(20);
        }

        return response()->json([
            'status' => 'success',
            'data' => $rooms
        ]);
    }

    public function createRoom(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'seller_id' => 'required|exists:sellers,seller_id'
            ], [
                'seller_id.required' => 'Seller ID harus diisi',
                'seller_id.exists' => 'Seller tidak ditemukan'
            ]);

            // Cek apakah user adalah customer
            if (!$request->user()->customer) {
                return response()->json([
                    'message' => 'Only customers can create chat rooms',
                    'status' => 'error'
                ], 403);
            }

            $room = ChatRoom::firstOrCreate([
                'customer_id' => $request->user()->customer->customer_id,
                'seller_id' => $request->seller_id,
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $room->load(['seller.user', 'customer.user'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create chat room',
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    public function getMessages(Request $request, $room_id): JsonResponse
    {
        try {
            // Cek apakah room exists
            $room = ChatRoom::findOrFail($room_id);
            
            // Cek apakah user punya akses ke room ini
            $user = $request->user();
            if ($user->seller) {
                if ($room->seller_id !== $user->seller->seller_id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'You do not have access to this chat room'
                    ], 403);
                }
            } else {
                if ($room->customer_id !== $user->customer->customer_id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'You do not have access to this chat room'
                    ], 403);
                }
            }

            $messages = Chat::where('room_id', $room_id)
                ->with(['sender', 'receiver'])
                ->orderBy('created_at', 'desc')
                ->paginate(50);

            return response()->json([
                'status' => 'success',
                'data' => $messages
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chat room not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get messages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function sendMessage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'room_id' => 'required|exists:chat_rooms,room_id',
                'message' => 'required|string'
            ]);

            $room = ChatRoom::findOrFail($request->room_id);
            $user = $request->user();
            
            // Tentukan tipe user dan ID
            if ($user->seller) {
                $senderType = 'App\\Models\\Seller';
                $senderId = $user->seller->seller_id;
                $receiverType = 'App\\Models\\Customer';
                $receiverId = $room->customer_id;
            } else {
                $senderType = 'App\\Models\\Customer';
                $senderId = $user->customer->customer_id;
                $receiverType = 'App\\Models\\Seller';
                $receiverId = $room->seller_id;
            }

            // Validasi akses
            if ($user->seller && $room->seller_id !== $senderId) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            if (!$user->seller && $room->customer_id !== $senderId) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $message = Chat::create([
                'room_id' => $request->room_id,
                'sender_type' => $senderType,
                'sender_id' => $senderId,
                'receiver_type' => $receiverType,
                'receiver_id' => $receiverId,
                'message' => $request->message,
                'status' => 'unread'
            ]);

            $room->update([
                'last_message' => $request->message,
                'last_message_time' => now(),
            ]);

            // Load relationships
            $message->load(['sender', 'receiver']);

            return response()->json([
                'status' => 'success',
                'data' => $message
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function markAsRead(Request $request): JsonResponse
    {
        $request->validate([
            'room_id' => 'required|exists:chat_rooms,room_id'
        ]);

        $user = $request->user();
        $userType = $user->seller ? 'seller' : 'customer';
        $userId = $userType === 'seller' ? $user->seller->seller_id : $user->customer->customer_id;

        Chat::where('room_id', $request->room_id)
            ->where('receiver_type', $userType === 'seller' ? Seller::class : Customer::class)
            ->where('receiver_id', $userId)
            ->update(['status' => 'read']);

        return response()->json([
            'status' => 'success',
            'message' => 'Messages marked as read'
        ]);
    }
}