<?php

namespace App\Domain\Portal\Services;

use App\Domain\Communication\Data\OutboundMessage;
use App\Domain\Communication\Enums\MessageChannelType;
use App\Domain\Communication\Services\ChannelManager;
use App\Domain\Portal\Models\PortalOtp;
use App\Domain\Shared\Services\PhoneNormalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Six-digit codes sent by SMS (the SMS driver of config/communication.php). Limits: one code per resend_seconds, at most
 * max_per_day per phone per day, max_attempts wrong tries per code, ttl_minutes of life. Only a hash is stored and a code
 * works once. issue() answers the same way whether or not the phone belongs to a known applicant.
 */
class OtpService
{
    public function __construct(private readonly PhoneNormalizer $phones, private readonly ChannelManager $channels) {}

    /** @throws ValidationException */
    public function issue(int $organizationId, string $phone, string $schoolName): string
    {
        $normalized = $this->phones->normalize($phone) ?? throw ValidationException::withMessages(['phone' => 'Số điện thoại không hợp lệ.']);
        $resend = (int) config('portal.otp.resend_seconds');
        $last = PortalOtp::query()->where('phone_normalized', $normalized)->latest('id')->first();

        if ($last !== null && $last->created_at->gt(now()->subSeconds($resend))) {
            $wait = max(1, $resend - (int) $last->created_at->diffInSeconds(now()));

            throw ValidationException::withMessages(['phone' => "Vui lòng đợi {$wait} giây rồi yêu cầu mã mới."]);
        }

        if (PortalOtp::query()->where('phone_normalized', $normalized)->where('created_at', '>=', now()->subDay())->count() >= (int) config('portal.otp.max_per_day')) {
            throw ValidationException::withMessages(['phone' => 'Bạn đã yêu cầu quá nhiều mã hôm nay. Hãy thử lại vào ngày mai.']);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttl = (int) config('portal.otp.ttl_minutes');
        $otp = PortalOtp::query()->create(['organization_id' => $organizationId, 'phone_normalized' => $normalized, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes($ttl)]);

        $result = $this->channels->for(MessageChannelType::Sms)->send(new OutboundMessage($normalized, "Ma xac thuc {$schoolName}: {$code}. Hieu luc {$ttl} phut, khong chia se cho ai."));

        if (! $result->ok) {
            $otp->delete();

            throw ValidationException::withMessages(['phone' => 'Không gửi được mã xác thực. Vui lòng thử lại sau.']);
        }

        return $normalized;
    }

    /**
     * @return string the normalized phone when the code is right
     *
     * @throws ValidationException
     */
    public function verify(string $phone, string $code): string
    {
        $normalized = $this->phones->normalize($phone) ?? throw ValidationException::withMessages(['phone' => 'Số điện thoại không hợp lệ.']);
        $otp = PortalOtp::query()->where('phone_normalized', $normalized)->whereNull('consumed_at')->where('expires_at', '>', now())->latest('id')->first();
        $wrong = ValidationException::withMessages(['code' => 'Mã không đúng hoặc đã hết hạn.']);

        if ($otp === null || $otp->attempts >= (int) config('portal.otp.max_attempts')) {
            throw $wrong;
        }

        $otp->increment('attempts');

        if (! Hash::check(trim($code), $otp->code_hash)) {
            throw $wrong;
        }

        $otp->forceFill(['consumed_at' => now()])->save();

        return $normalized;
    }
}
