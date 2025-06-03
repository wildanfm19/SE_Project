<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
            
    Schema::create('sellers', function (Blueprint $table) {
        $table->unsignedBigInteger('seller_id');
        $table->unsignedBigInteger('user_id');
        $table->string('store_name');
        $table->text('store_address');
        $table->string('store_logo')->nullable();
        $table->text('store_description')->nullable();
        $table->float('store_rating')->default(0);
        $table->integer('total_sales')->default(0);
        $table->timestamps();

        $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
     });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller');
    }
};
