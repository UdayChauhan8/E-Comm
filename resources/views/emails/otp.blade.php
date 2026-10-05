<!DOCTYPE html>
<html>
<head>
    <title>Your OTP Code</title>
</head>
<body>
    <h2>Email Verification</h2>

    <p>Hello {{ $userName }},</p>

    <p>Your One-Time Password (OTP) is:</p>

    <h1 style="letter-spacing: 10px; font-size: 36px; color: #2d3748; 
               background: #f7fafc; padding: 15px 25px; display: inline-block;
               border-radius: 8px;">
        {{ $otp }}
    </h1>

    <p>This code expires in <strong>10 minutes</strong>.</p>
    <p>If you didn't request this, please ignore this email.</p>

    <p>Thank you!</p>
</body>
</html>