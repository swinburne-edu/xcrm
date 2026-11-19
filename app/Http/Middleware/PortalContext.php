<?php

namespace App\Http\Middleware;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Portal\Services\PortalOrganization;
use App\Domain\Shared\Services\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public pages and the applicant portal (task 3.4) have no staff user, so the organization comes from config/portal.php.
 * With the "auth" parameter the request must also carry a portal session (a verified OTP) of an applicant of that
 * organization, else the visitor is sent to the portal login. The applicant is put on the request as "portal_applicant".
 */
class PortalContext
{
    public const SESSION_KEY = 'portal_applicant_id';

    public function __construct(private readonly PortalOrganization $organizations, private readonly CurrentOrganization $current) {}

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $organization = $this->organizations->get();

        abort_if($organization === null, 404);
        $this->current->set($organization->id);
        $request->attributes->set('portal_organization', $organization);

        if ($mode === 'auth') {
            $applicant = $request->session()->has(self::SESSION_KEY)
                ? Applicant::query()->whereNull('merged_into_id')->find($request->session()->get(self::SESSION_KEY))
                : null;

            if ($applicant === null) {
                $request->session()->forget(self::SESSION_KEY);

                return $request->expectsJson() ? response()->json(['message' => 'Phiên đăng nhập đã hết hạn.'], 401) : redirect('/cong-thi-sinh/dang-nhap');
            }

            $request->attributes->set('portal_applicant', $applicant);
        }

        return $next($request);
    }
}
