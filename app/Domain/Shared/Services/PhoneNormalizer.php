<?php

namespace App\Domain\Shared\Services;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Normalizes phone numbers to digits-only E.164 without "+",
 * e.g. "0912 345 678" / "+84 912-345-678" / "84912345678" => "84912345678".
 * Result is stored in *_phone_normalized columns and used for de-duplication.
 */
class PhoneNormalizer
{
    public const DEFAULT_REGION = 'VN';

    public function __construct(private readonly PhoneNumberUtil $util) {}

    /**
     * Normalized number, or null when the input is empty or not a valid phone number.
     */
    public function normalize(?string $phone, string $region = self::DEFAULT_REGION): ?string
    {
        $number = $this->parse($phone, $region);

        if ($number === null) {
            return null;
        }

        return ltrim($this->util->format($number, PhoneNumberFormat::E164), '+');
    }

    public function isValid(?string $phone, string $region = self::DEFAULT_REGION): bool
    {
        return $this->parse($phone, $region) !== null;
    }

    /**
     * National display format, e.g. "0912 345 678". Returns the input unchanged if it cannot be parsed.
     */
    public function formatNational(?string $phone, string $region = self::DEFAULT_REGION): ?string
    {
        $number = $this->parse($phone, $region);

        return $number === null ? $phone : $this->util->format($number, PhoneNumberFormat::NATIONAL);
    }

    private function parse(?string $phone, string $region): ?PhoneNumber
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        // "0084..." (international dialing prefix) and "84xxxxxxxxx" typed without "+".
        if (str_starts_with($digits, '00')) {
            $phone = '+'.substr($digits, 2);
        } elseif ($region === 'VN' && ! str_starts_with($phone, '+') && str_starts_with($digits, '84') && strlen($digits) >= 11) {
            $phone = '+'.$digits;
        }

        try {
            $number = $this->util->parse($phone, $region);
        } catch (NumberParseException) {
            return null;
        }

        return $this->util->isValidNumber($number) ? $number : null;
    }
}
