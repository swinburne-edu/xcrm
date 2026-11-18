<?php

// The embedded lead forms post from the university's own website, so the public endpoint is open to any origin.
return [
    'paths' => ['api/public/*'],
    'allowed_methods' => ['POST', 'OPTIONS'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => false,
];
