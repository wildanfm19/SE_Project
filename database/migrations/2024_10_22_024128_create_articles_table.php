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
        Schema::create('articles', function (Blueprint $table) {
            $table->bigIncrements('article_id');
            $table->string('title');
            $table->text('content');
            $table->unsignedBigInteger('seller_id');
            $table->timestamps();

            $table->foreign('seller_id')->references('seller_id')->on('sellers')->onDelete('cascade');
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article');
    }
};
