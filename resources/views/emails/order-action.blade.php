<!DOCTYPE html>
<html>
<head>
    <title>Order Notification</title>
</head>
<body>
    <h2>Order {{ ucfirst($action) }}</h2>

    <p>Hello {{ $order->user->name }},</p>

    <p>Your order has been <strong>{{ $action }}</strong>. Here are the details:</p>

    <table border="1" cellpadding="8" cellspacing="0">
        <tr>
            <td><strong>Order Number</strong></td>
            <td>{{ $order->order_number }}</td>
        </tr>
        <tr>
            <td><strong>Product Name</strong></td>
            <td>{{ $order->product_name }}</td>
        </tr>
        <tr>
            <td><strong>Amount</strong></td>
            <td>${{ number_format($order->amount, 2) }}</td>
        </tr>
    </table>

    <p>Thank you!</p>
</body>
</html>