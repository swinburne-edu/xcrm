<?php

namespace App\Domain\Shared\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Times are stored in UTC and shown in the display timezone (Asia/Ho_Chi_Minh).
 * Forms send "YYYY-MM-DDTHH:mm" as typed by the user (display timezone).
 */
final class DisplayTime
{
    /** "2026-11-17T08:30" typed in Vietnam time → UTC Carbon (null for blank / invalid input). */
    public static function toUtc(?string $input): ?CarbonImmutable
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($input, config('app.display_timezone'))->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /** UTC instant → "2026-11-17T08:30" in display time, for <input type="datetime-local">. */
    public static function toInput(?CarbonInterface $time): ?string
    {
        return $time?->copy()->setTimezone(config('app.display_timezone'))->format('Y-m-d\TH:i');
    }
}
