<?php

namespace App\Http\Controllers\Applicant;

use App\Domain\Applicant\Actions\SaveGuardian;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Models\Guardian;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applicant\GuardianRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Guardians of an applicant. The applicant is loaded through the data scope (outside it = 404) and
 * every change needs applicant.update.
 */
class ApplicantGuardianController extends Controller
{
    public function store(GuardianRequest $request, string $uuid, SaveGuardian $save): RedirectResponse
    {
        $applicant = $this->applicant($uuid);
        $guardian = $save->handle($applicant, null, $request->validated());

        return $this->back("Đã thêm phụ huynh {$guardian->full_name}.");
    }

    public function update(GuardianRequest $request, string $uuid, int $guardianId, SaveGuardian $save): RedirectResponse
    {
        $applicant = $this->applicant($uuid);
        $guardian = $applicant->guardians()->findOrFail($guardianId);
        $save->handle($applicant, $guardian, $request->validated());

        return $this->back("Đã cập nhật phụ huynh {$guardian->full_name}.");
    }

    /** Unlinks the guardian from this applicant (the guardian record stays for siblings). */
    public function destroy(string $uuid, int $guardianId): RedirectResponse
    {
        $applicant = $this->applicant($uuid);
        $guardian = $applicant->guardians()->findOrFail($guardianId);
        $applicant->guardians()->detach($guardian->id);

        if (! $guardian->applicants()->exists()) {
            $guardian->delete();
        }

        return $this->back("Đã gỡ phụ huynh {$guardian->full_name}.");
    }

    private function applicant(string $uuid): Applicant
    {
        $applicant = Applicant::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $applicant);

        return $applicant;
    }

    private function back(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
