<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
    
class CreateCustomersTable extends Migration
{
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->unsignedbigIncrements('customer_id');
            $table->unsignedBigInteger('user_id');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('phone_number')->nullable();
            $table->string('profile_image')->nullable();
            $table->text('address')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->timestamps();

            // Foreign key linking to the Users table
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('customers');
    }
}
