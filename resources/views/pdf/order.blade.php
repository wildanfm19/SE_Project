<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order #{{ $order->order_id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 10px;
        }
        .order-info {
            margin-bottom: 20px;
        }
        .customer-info, .shipping-info {
            float: left;
            width: 50%;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
        }
        .total-section {
            float: right;
            width: 300px;
        }
        .total-row {
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 50px;
            font-size: 12px;
            color: #666;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ public_path('images/logo.png') }}" class="logo" alt="Logo">
        <h1>Order Invoice</h1>
    </div>

    <div class="order-info">
        <p><strong>Order ID:</strong> #{{ $order->order_id }}</p>
        <p><strong>Order Date:</strong> {{ $order->created_at->format('d M Y H:i') }}</p>
        <p><strong>Status:</strong> {{ ucfirst($order->status) }}</p>
        <p><strong>Payment Status:</strong> {{ ucfirst($order->payment_status) }}</p>
    </div>

    <div class="clearfix">
        <div class="customer-info">
            <h3>Customer Information</h3>
            <p><strong>Name:</strong> {{ $order->user->customer->full_name ?? 'N/A' }}</p>
            <p><strong>Email:</strong> {{ $order->user->customer->email ?? 'N/A' }}</p>
            <p><strong>Phone:</strong> {{ $order->user->customer->phone_number ?? 'N/A' }}</p>
        </div>

        <div class="shipping-info">
            <h3>Shipping Address</h3>
            <p>{{ $order->address->name ?? 'N/A' }}</p>
            <p>{{ $order->address->address ?? 'N/A' }}</p>
            <p>Phone: {{ $order->address->phone_number ?? 'N/A' }}</p>
        </div>
    </div>

    <h3>Order Items</h3>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Product</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->orderItems as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->product->product_name }}</td>
                <td>Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                <td>{{ $item->quantity }}</td>
                <td>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-section">
        <table>
            <tr>
                <td><strong>Subtotal:</strong></td>
                <td>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td><strong>Shipping:</strong></td>
                <td>Rp 0</td>
            </tr>
            <tr class="total-row">
                <td><strong>Total:</strong></td>
                <td>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Thank you for your order!</p>
        <p>If you have any questions, please contact our customer service.</p>
        <p>Generated on {{ now()->format('d M Y H:i:s') }}</p>
    </div>
</body>
</html> 