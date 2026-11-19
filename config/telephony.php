<?php

/**
 * Click-to-call (task 3.7). Driver log only records the call (development); http asks the gateway of the telephone
 * provider to ring the agent and then the customer: POST {call_id, agent: {id, name, email}, to} with a bearer token.
 */
return [
    'driver' => env('TELEPHONY_DRIVER', 'log'),
    'http' => [
        'url' => env('TELEPHONY_URL'),
        'token' => env('TELEPHONY_TOKEN'),
    ],
    // Seconds an inbound call keeps showing the popup.
    'popup_seconds' => 90,
];
