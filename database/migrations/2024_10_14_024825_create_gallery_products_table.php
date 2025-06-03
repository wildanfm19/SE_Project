<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGalleryProductsTable extends Migration
{
    /**
     * Jalankan migrasi untuk membuat tabel gallery_products.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gallery_products', function (Blueprint $table) {
            $table->bigIncrements('gallery_id');  // Primary key auto-increment
            $table->unsignedBigInteger('product_id');  // Foreign key ke tabel products
            $table->unsignedBigInteger('seller_id');   // Foreign key ke tabel sellers
            $table->string('image_url');  // Menyimpan URL gambar
            $table->timestamp('uploaded_at')->nullable();  // Tanggal upload
            $table->timestamps();  // Otomatis menambah created_at dan updated_at

            // Menambahkan foreign key constraints
            $table->foreign('product_id')->references('product_id')->on('products')->onDelete('cascade');
            $table->foreign('seller_id')->references('seller_id')->on('sellers')->onDelete('cascade');
        });
    }

    /**
     * Rollback migrasi untuk menghapus tabel gallery_products.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gallery_products');
    }
}
