<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Intake\Actions\IntakeLead;
use App\Domain\Organization\Models\Organization;
use App\Domain\Portal\Services\OtpService;
use App\Http\Controllers\Controller;
use App\Http\Middleware\PortalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Applicant portal login by SMS code (task 3.4). A phone number that has no applicant yet is asked for a name after the
 * code is checked and then registered through the normal intake. The code is the only credential; the session holds the
 * applicant id.
 */
class AuthController extends Controller
{
    private const VERIFIED_KEY = 'portal_verified_phone';

    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->session()->has(PortalContext::SESSION_KEY)) {
            return redirect('/cong-thi-sinh');
        }

        /** @var Organization $organization */
        $organization = $request->attributes->get('portal_organization');

        return Inertia::render('Portal/Login', ['school' => $organization->name]);
    }

    public function requestCode(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        /** @var Organization $organization */
        $organization = $request->attributes->get('portal_organization');

        $otp->issue($organization->id, $data['phone'], $organization->name);

        return response()->json(['ok' => true]);
    }

    public function verify(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20'], 'code' => ['required', 'string', 'max:10']]);
        $phone = $otp->verify($data['phone'], $data['code']);
        $applicant = Applicant::query()->whereNull('merged_into_id')->where('phone_normalized', $phone)->first();

        if ($applicant === null) {
            $request->session()->put(self::VERIFIED_KEY, ['phone' => $phone, 'at' => now()->timestamp]);

            return response()->json(['ok' => true, 'needs_name' => true]);
        }

        $this->login($request, $applicant);

        return response()->json(['ok' => true, 'needs_name' => false]);
    }

    public function register(Request $request, IntakeLead $intake): JsonResponse
    {
        $verified = $request->session()->get(self::VERIFIED_KEY);

        if (! is_array($verified) || now()->timestamp - ($verified['at'] ?? 0) > 600) {
            return response()->json(['message' => 'Phiên xác thực đã hết hạn, hãy nhập lại số điện thoại.'], 422);
        }

        $data = $request->validate(['full_name' => ['required', 'string', 'max:255']]);
        /** @var Organization $organization */
        $organization = $request->attributes->get('portal_organization');
        $source = LeadSource::query()->where('code', 'website')->value('id');

        $result = $intake->handle($organization->id, ['full_name' => $data['full_name'], 'phone' => $verified['phone'], 'consent' => true], ['source_id' => $source, 'label' => 'Đăng ký qua cổng thí sinh']);
        $request->session()->forget(self::VERIFIED_KEY);
        $this->login($request, $result['applicant']);

        return response()->json(['ok' => true]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(PortalContext::SESSION_KEY);
        $request->session()->regenerate();

        return redirect('/cong-thi-sinh/dang-nhap');
    }

    private function login(Request $request, Applicant $applicant): void
    {
        $request->session()->regenerate();
        $request->session()->put(PortalContext::SESSION_KEY, $applicant->id);
    }
}
