<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
#use Illuminate\Database\Schema\Blueprint\unsignedbigIncrements;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('user_id');  
            $table->string('username')->unique();  // Username field
            $table->string('password');  // Password field
            $table->enum('role', ['customer', 'seller']);  // Role as ENUM ('customer' or 'seller' or 'admin')
            $table->enum('status', ['active', 'suspended'])->default('active'); // Status as ENUM ('active', 'suspended')
            $table->timestamps();  // Laravel's created_at and updated_at fields
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');  // Drop users table if it exists
    }
}
