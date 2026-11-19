<?php

return [
    // Webhook actions only call public https hosts. Turn off the DNS check only in tests (no network).
    'resolve_dns' => env('WORKFLOW_RESOLVE_DNS', true),
];
