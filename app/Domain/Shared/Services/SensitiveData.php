<?php

namespace App\Domain\Shared\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Hides phone numbers and citizen ids from users without `applicant.view_sensitive`
 * (docs/02-phan-quyen.md): "0912345678" → "09******78", citizen id → "*********123".
 * Viewing a citizen id is written to the audit log.
 */
class SensitiveData
{
    public const PERMISSION = 'applicant.view_sensitive';

    public function canView(?User $user): bool
    {
        return $user !== null && $user->can(self::PERMISSION);
    }

    /** Phone as shown to $user: full with permission, masked otherwise. */
    public function phone(?string $phone, ?User $user): ?string
    {
        if ($phone === null || $phone === '' || $this->canView($user)) {
            return $phone;
        }

        return $this->maskPhone($phone);
    }

    /** Citizen id as shown to $user. Pass $subject to audit-log a full view. */
    public function citizenId(?string $citizenId, ?User $user, ?Model $subject = null): ?string
    {
        if ($citizenId === null || $citizenId === '') {
            return $citizenId;
        }

        if (! $this->canView($user)) {
            return $this->maskCitizenId($citizenId);
        }

        if ($subject !== null) {
            activity()->causedBy($user)->performedOn($subject)->log('Xem số CCCD');
        }

        return $citizenId;
    }

    /** Keeps the first 2 and last 2 characters (spaces and dots ignored). */
    public function maskPhone(string $phone): string
    {
        $compact = preg_replace('/[\s.\-()]/', '', $phone);
        $length = mb_strlen($compact);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return mb_substr($compact, 0, 2).str_repeat('*', $length - 4).mb_substr($compact, -2);
    }

    /** Keeps only the last 3 characters. */
    public function maskCitizenId(string $citizenId): string
    {
        $length = mb_strlen($citizenId);

        if ($length <= 3) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 3).mb_substr($citizenId, -3);
    }
}
