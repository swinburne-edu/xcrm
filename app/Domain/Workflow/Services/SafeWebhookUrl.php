<?php

namespace App\Domain\Workflow\Services;

/**
 * Guards the webhook action against calling internal services (SSRF): https only, no credentials in the URL,
 * and the host must not be localhost or resolve to a private, loopback or reserved address.
 */
class SafeWebhookUrl
{
    public function check(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return 'Webhook phải là địa chỉ https hợp lệ.';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'Địa chỉ webhook không được chứa tài khoản.';
        }

        $host = strtolower(trim($parts['host'], '[]'));

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return 'Webhook không được trỏ vào máy nội bộ.';
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (config('workflows.resolve_dns') ? (gethostbynamel($host) ?: []) : null);

        if ($ips === []) {
            return 'Không phân giải được tên miền webhook.';
        }

        foreach ($ips ?? [] as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return 'Webhook không được trỏ vào địa chỉ nội bộ.';
            }
        }

        return null;
    }
}
