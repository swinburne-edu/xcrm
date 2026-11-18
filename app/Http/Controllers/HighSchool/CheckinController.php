<?php

namespace App\Http\Controllers\HighSchool;

use App\Domain\HighSchool\Actions\CheckInToActivity;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\HighSchool\Models\SchoolActivity;
use App\Domain\Major\Models\Major;
use App\Domain\Organization\Models\Organization;
use App\Domain\Shared\Support\DisplayTime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public check-in page behind the QR code (task 2.1, flow F2): anonymous, rate limited.
 *  GET  /checkin/{token}   the short form (or why it is closed: not started, ended, done, cancelled)
 *  POST /checkin/{token}   full name, phone, class, interested major, consent → CheckInToActivity
 * An unknown token is a plain 404. "hp" is a honeypot: a filled one is answered like a success and dropped.
 */
class CheckinController extends Controller
{
    public function show(string $token, CheckInToActivity $checkin): Response
    {
        $activity = $this->activity($token);
        $school = $activity->high_school_id === null ? null : HighSchool::query()->withoutGlobalScopes()->find($activity->high_school_id);

        return Inertia::render('Checkin/Show', [
            'activity' => [
                'title' => $activity->title,
                'type_label' => $activity->type->label(),
                'school' => $school?->name,
                'location' => $activity->location,
                'start_at' => $activity->start_at?->toIso8601String(),
                'organization' => Organization::query()->whereKey($activity->organization_id)->value('name'),
            ],
            'closed' => $checkin->closedReason($activity),
            'opens_at' => $activity->start_at === null ? null : DisplayTime::toInput($activity->start_at->copy()->subHours(CheckInToActivity::OPENS_BEFORE_HOURS)),
            'majors' => Major::query()->withoutGlobalScopes()->where('organization_id', $activity->organization_id)->where('is_active', true)->orderBy('name')->limit(100)->get(['id', 'name'])
                ->map(fn (Major $m) => ['value' => $m->id, 'label' => $m->name])->all(),
        ]);
    }

    public function store(Request $request, string $token, CheckInToActivity $checkin): JsonResponse
    {
        $activity = $this->activity($token);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'class_name' => ['nullable', 'string', 'max:50'],
            'major' => ['nullable', 'integer'],
            'consent' => ['accepted'],
            'hp' => ['nullable', 'string', 'max:255'],
        ], ['consent.accepted' => 'Cần đồng ý để nhà trường liên hệ tư vấn.'], [
            'full_name' => 'họ tên', 'phone' => 'số điện thoại', 'class_name' => 'lớp', 'major' => 'ngành quan tâm', 'consent' => 'đồng ý nhận thông tin',
        ]);

        if (filled($data['hp'] ?? null)) {
            return response()->json(['ok' => true, 'message' => 'Cảm ơn bạn! Đã ghi nhận điểm danh.', 'repeat' => false]);
        }

        $result = $checkin->handle($activity, $data);

        return response()->json([
            'ok' => true,
            'repeat' => $result['repeat'],
            'message' => $result['repeat']
                ? 'Bạn đã điểm danh rồi, chúng tôi đã cập nhật lại thông tin của bạn. Cảm ơn!'
                : 'Cảm ơn '.$result['applicant']->full_name.'! Đã ghi nhận điểm danh, cán bộ tuyển sinh sẽ liên hệ với bạn sớm.',
        ]);
    }

    private function activity(string $token): SchoolActivity
    {
        return SchoolActivity::query()->withoutGlobalScopes()->whereNull('deleted_at')->where('checkin_token', $token)->firstOrFail();
    }
}
