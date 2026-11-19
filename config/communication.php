<?php

/**
 * Messaging channels (task 3.1). Each channel uses one driver: "log" only writes to the application log (development),
 * the others talk to the provider. Provider credentials come from .env.
 */
return [
    'drivers' => [
        'zns' => env('MESSAGE_ZNS_DRIVER', 'zalo'),
        'sms' => env('MESSAGE_SMS_DRIVER', 'http'),
        'email' => env('MESSAGE_EMAIL_DRIVER', 'mail'),
    ],

    // Zalo Notification Service: template messages sent by phone number (access token of the Official Account).
    'zns' => [
        'url' => env('ZALO_ZNS_URL', 'https://business.openapi.zalo.me/message/template'),
        'access_token' => env('ZALO_ZNS_ACCESS_TOKEN'),
    ],

    // Generic JSON SMS gateway: POST {to, content, brandname} with a bearer token.
    'sms' => [
        'url' => env('SMS_API_URL'),
        'token' => env('SMS_API_TOKEN'),
        'brandname' => env('SMS_BRANDNAME'),
    ],

    // Most recipients one bulk send may reach.
    'max_bulk' => 2000,
];
