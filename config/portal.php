<?php

/**
 * Applicant portal and public score page (task 3.4).
 * organization_id: the organization the public pages belong to; empty = the first organization (single-school installs).
 */
return [
    'organization_id' => env('PORTAL_ORGANIZATION_ID'),
    'otp' => [
        'ttl_minutes' => 5,
        'max_attempts' => 5,
        'resend_seconds' => 60,
        'max_per_day' => 5,
    ],
];
