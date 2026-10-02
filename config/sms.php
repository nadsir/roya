<?php

return [
    // log: development only, writes the OTP to the log. http: SMS.ir REST API v1.
    'driver' => env('SMS_DRIVER', 'log'),
    'endpoint' => env('SMS_ENDPOINT', 'https://api.sms.ir/v1/send/verify'),
    'token' => env('SMS_TOKEN'),
    'template_id' => env('SMS_TEMPLATE_ID', '325946'),
    // Must match the placeholder name configured in the SMS.ir template.
    'parameter_name' => env('SMS_PARAMETER_NAME', 'CODE'),
];