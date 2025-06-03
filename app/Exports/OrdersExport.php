<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrdersExport implements FromCollection, WithHeadings, WithMapping
{
    protected $startDate;
    protected $endDate;
    protected $status;

    public function __construct($startDate = null, $endDate = null, $status = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->status = $status;
    }

    public function collection(): Collection
    {
        $query = Order::with(['user.customer', 'orderItems.product']);

        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }
        if ($this->status) {
            $query->where('status', $this->status);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Order ID',
            'Customer Name',
            'Email',
            'Products',
            'Total Amount',
            'Status',
            'Created At'
        ];
    }

    public function map($order): array
    {
        return [
            $order->order_id,
            $order->user->customer->full_name ?? 'N/A',
            $order->user->customer->email ?? 'N/A',
            $this->formatProducts($order->orderItems),
            number_format($order->total_amount, 0, ',', '.'),
            $order->status,
            $order->created_at->format('Y-m-d H:i:s')
        ];
    }

    private function formatProducts($orderItems)
    {
        return $orderItems->map(function($item) {
            return "{$item->product->product_name} (x{$item->quantity})";
        })->implode(', ');
    }
} 