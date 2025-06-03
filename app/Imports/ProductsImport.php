<?php

namespace App\Imports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ProductsImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        return new Product([
            'product_name' => $row['product_name'],
            'description' => $row['description'],
            'price' => $row['price'],
            'stock_quantity' => $row['stock_quantity'],
            'seller_id' => auth()->user()->seller->seller_id,
            'category_id' => $row['category_id'],
            'is_active' => true
        ]);
    }

    public function rules(): array
    {
        return [
            'product_name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,category_id'
        ];
    }
} 