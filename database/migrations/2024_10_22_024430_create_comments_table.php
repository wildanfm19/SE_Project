<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->bigIncrements('comment_id'); // Primary key
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->text('comment_text');
            $table->timestamps();
    
            $table->foreign('parent_id')
                  ->references('comment_id')
                  ->on('comments')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('comments');
    }
};