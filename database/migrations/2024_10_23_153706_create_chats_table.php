<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->bigIncrements('chat_id'); // Primary key
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade'); // Foreign key to Users table
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade'); // Foreign key to Users table
            $table->text('message'); // Chat message
            $table->timestamps(); // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('chats');
    }
}
