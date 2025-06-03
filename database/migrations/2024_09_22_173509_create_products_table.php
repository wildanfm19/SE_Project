<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('product_id');
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('category_id');
            $table->string('product_name');
            $table->text('description')->nullable();
            $table->float('price');
            $table->integer('stock_quantity');
            $table->timestamps();

            // Foreign keys
            $table->foreign('seller_id')->references('seller_id')->on('sellers')->onDelete('cascade');
            $table->foreign('category_id')->references('category_id')->on('categories')->onDelete('cascade');
        });
    }
    public function down()
    {
        Schema::dropIfExists('products');
    }
}
