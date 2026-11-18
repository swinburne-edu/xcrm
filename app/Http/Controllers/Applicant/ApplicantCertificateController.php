<?php

namespace App\Http\Controllers\Applicant;

use App\Domain\Applicant\Models\Applicant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applicant\CertificateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Certificates of an applicant. The applicant is loaded through the data scope; changes need applicant.update.
 * Changing a certificate clears its verified flag.
 */
class ApplicantCertificateController extends Controller
{
    public function store(CertificateRequest $request, string $uuid): RedirectResponse
    {
        $this->applicant($uuid)->certificates()->create($request->validated());

        return $this->back('Đã thêm chứng chỉ.');
    }

    public function update(CertificateRequest $request, string $uuid, int $certificateId): RedirectResponse
    {
        $certificate = $this->applicant($uuid)->certificates()->findOrFail($certificateId);
        $certificate->fill($request->validated() + ['verified' => false])->save();

        return $this->back('Đã cập nhật chứng chỉ.');
    }

    public function destroy(string $uuid, int $certificateId): RedirectResponse
    {
        $this->applicant($uuid)->certificates()->findOrFail($certificateId)->delete();

        return $this->back('Đã xoá chứng chỉ.');
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
