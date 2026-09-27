<?php

return [
    'driver' => env('SMS_DRIVER', 'log'),
    'endpoint' => env('SMS_ENDPOINT'),
    'token' => env('SMS_TOKEN'),
    'template_id' => env('SMS_TEMPLATE_ID'),
];