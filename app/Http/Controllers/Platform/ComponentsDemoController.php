<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Local-only showcase of the shared CRM components (/dev/components),
 * wired to a real list (users of the organization) through ListQuery.
 */
class ComponentsDemoController extends Controller
{
    public function __invoke(Request $request, CurrentOrganization $organization): Response
    {
        $users = ListQuery::for(User::query()->where('organization_id', $organization->id()), $request)
            ->filters(
                Filter::search('q', ['name', 'email', 'phone']),
                Filter::exact('data_scope'),
                Filter::boolean('is_active'),
                Filter::dateRange('created_at'),
            )
            ->sorts(['name', 'email', 'created_at'])
            ->defaultSort('name')
            ->toProps(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
                'data_scope' => $user->data_scope->value,
                'data_scope_label' => $user->data_scope->label(),
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('dev/Components', [
            'users' => $users,
            'dataScopeOptions' => array_map(
                fn (DataScopeLevel $level) => ['value' => $level->value, 'label' => $level->label()],
                DataScopeLevel::cases(),
            ),
        ]);
    }
}
