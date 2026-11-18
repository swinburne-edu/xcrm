<?php

namespace App\Http\Controllers\HighSchool;

use App\Domain\HighSchool\Actions\SaveSchoolContact;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\HighSchool\Models\SchoolContact;
use App\Http\Controllers\Controller;
use App\Http\Requests\HighSchool\SchoolContactRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Contacts of a school ("Người liên hệ" tab). The school is loaded through HighSchool's data scope,
 * so a school outside the user's scope is a 404; school_contact.* permissions decide the rest.
 */
class SchoolContactController extends Controller
{
    public function store(SchoolContactRequest $request, int $schoolId, SaveSchoolContact $save): RedirectResponse
    {
        $school = HighSchool::query()->findOrFail($schoolId);
        $this->authorize('create', SchoolContact::class);

        $contact = $save->handle($school, null, $request->validated());

        return $this->back("Đã thêm {$contact->full_name}.");
    }

    public function update(SchoolContactRequest $request, int $schoolId, int $contactId, SaveSchoolContact $save): RedirectResponse
    {
        $school = HighSchool::query()->findOrFail($schoolId);
        $contact = SchoolContact::query()->where('high_school_id', $school->id)->findOrFail($contactId);
        $this->authorize('update', $contact);

        $save->handle($school, $contact, $request->validated());

        return $this->back("Đã cập nhật {$contact->full_name}.");
    }

    public function destroy(int $schoolId, int $contactId): RedirectResponse
    {
        $school = HighSchool::query()->findOrFail($schoolId);
        $contact = SchoolContact::query()->where('high_school_id', $school->id)->findOrFail($contactId);
        $this->authorize('delete', $contact);

        $contact->delete();

        return $this->back("Đã xoá {$contact->full_name}.");
    }

    private function back(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
