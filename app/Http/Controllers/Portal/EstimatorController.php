<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Catalog\Models\Subject;
use App\Domain\Cycle\Services\CurrentCycle;
use App\Domain\Intake\Actions\IntakeLead;
use App\Domain\Organization\Models\Organization;
use App\Domain\Portal\Services\ScoreEstimator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public page "Tính điểm và gợi ý ngành" (task 3.4), no login. It doubles as a lead form: a student who leaves a phone
 * number and agrees to be contacted goes through the normal intake (flow F3) with the source Website.
 */
class EstimatorController extends Controller
{
    public function show(Request $request, CurrentCycle $currentCycle): Response
    {
        /** @var Organization $organization */
        $organization = $request->attributes->get('portal_organization');
        $cycle = $currentCycle->get();

        return Inertia::render('Portal/Estimator', [
            'school' => $organization->name,
            'cycle' => $cycle?->name,
            'subjects' => Subject::query()->orderByDesc('is_core')->orderBy('id')->get(['id', 'code', 'name'])->map(fn (Subject $s) => ['code' => $s->code, 'name' => $s->name])->all(),
        ]);
    }

    public function estimate(Request $request, CurrentCycle $currentCycle, ScoreEstimator $estimator): JsonResponse
    {
        $data = $request->validate([
            'scores' => ['required', 'array', 'min:1', 'max:30'],
            'scores.*' => ['nullable', 'numeric', 'between:0,10'],
            'bonus' => ['nullable', 'numeric', 'between:0,3'],
        ]);
        $cycle = $currentCycle->get();

        if ($cycle === null) {
            return response()->json(['suggestions' => [], 'message' => 'Nhà trường chưa mở mùa tuyển sinh.']);
        }

        return response()->json(['suggestions' => $estimator->suggest($cycle, $data['scores'], (float) ($data['bonus'] ?? 0))]);
    }

    public function register(Request $request, IntakeLead $intake): JsonResponse
    {
        if ($request->filled('hp')) {
            return response()->json(['ok' => true]);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Vui lòng đồng ý để nhà trường liên hệ.']);

        /** @var Organization $organization */
        $organization = $request->attributes->get('portal_organization');
        $source = LeadSource::query()->where('code', 'website')->value('id');

        $intake->handle($organization->id, $data + ['consent' => true], ['source_id' => $source, 'label' => 'Đăng ký từ trang tính điểm và gợi ý ngành']);

        return response()->json(['ok' => true, 'message' => 'Cảm ơn bạn! Nhà trường sẽ liên hệ trong thời gian sớm nhất.'], 201);
    }
}
