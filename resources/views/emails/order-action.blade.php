<!DOCTYPE html>
<html>

<head>
    <title>Order Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f8f9fa;
        }

        .total-row {
            font-weight: bold;
            background-color: #f1f1f1;
        }
    </style>
</head>

<body>
    <h2>Order {{ ucfirst($action) }}</h2>

    <p>Hello {{ $order->user->name }},</p>

    <p>Your order <strong>{{ $order->reference }}</strong> has been <strong>{{ $action }}</strong>. Here are your order
        details:</p>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Color</th>
                <th>Size</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product ? $item->product->title : 'Product' }}</td>
                    <td>{{ $item->color }}</td>
                    <td>{{ $item->size }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>₹{{ number_format($item->unit_price, 2) }}</td>
                    <td>₹{{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" style="text-align: right;">Total Amount Paid:</td>
                <td>₹{{ number_format($order->total_amount, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <p style="margin-top: 20px;">Thank you for shopping with us!</p>
</body>

</html>