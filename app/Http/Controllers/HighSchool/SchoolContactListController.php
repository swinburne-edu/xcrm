<?php

namespace App\Http\Controllers\HighSchool;

use App\Domain\HighSchool\Enums\ContactRole;
use App\Domain\HighSchool\Models\SchoolContact;
use App\Domain\Shared\Services\SensitiveData;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /school-contacts: every contact of every school the user can see, in one searchable list. Adding and editing stay on
 * the school page (tab Người liên hệ); a contact whose school is outside the data scope is not listed.
 */
class SchoolContactListController extends Controller
{
    public function __construct(private readonly SensitiveData $sensitive) {}

    public function __invoke(Request $request): Response
    {
        $this->authorize('viewAny', SchoolContact::class);
        $user = $request->user();

        $contacts = ListQuery::for(SchoolContact::query()->whereHas('highSchool')->with('highSchool:id,name'), $request)
            ->filters(
                Filter::search('q', ['full_name', 'phone', 'email']),
                Filter::exact('role'),
                Filter::exact('high_school_id'),
                Filter::exact('is_primary'),
            )
            ->sorts(['full_name', 'created_at'])
            ->defaultSort('full_name')
            ->toProps(fn (SchoolContact $c) => [
                'id' => $c->id,
                'full_name' => $c->full_name,
                'role_label' => $c->role?->label(),
                'homeroom_class' => $c->homeroom_class,
                'high_school_id' => $c->high_school_id,
                'high_school_name' => $c->highSchool?->name,
                'phone' => $this->sensitive->phone($c->phone, $user),
                'email' => $c->email,
                'zalo' => $c->zalo,
                'relationship_level' => $c->relationship_level,
                'is_primary' => (bool) $c->is_primary,
            ]);

        return Inertia::render('SchoolContacts/Index', [
            'contacts' => $contacts,
            'options' => ['roles' => array_map(fn (ContactRole $r) => ['value' => $r->value, 'label' => $r->label()], ContactRole::cases())],
        ]);
    }
}
