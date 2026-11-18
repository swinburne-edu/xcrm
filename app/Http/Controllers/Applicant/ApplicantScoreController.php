<?php

namespace App\Http\Controllers\Applicant;

use App\Domain\Applicant\Actions\SaveApplicantScores;
use App\Domain\Applicant\Models\Applicant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applicant\ScoresRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Quick-entry score grid of an applicant (PUT of all changed cells).
 */
class ApplicantScoreController extends Controller
{
    public function update(ScoresRequest $request, string $uuid, SaveApplicantScores $save): RedirectResponse
    {
        $applicant = Applicant::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $applicant);

        $result = $save->handle($applicant, $request->validated('scores'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Đã lưu {$result['saved']} điểm, xoá {$result['deleted']}."]);

        return back();
    }
}
