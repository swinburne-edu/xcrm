<?php

namespace App\Http\Middleware;

use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Services\CurrentCycle;
use App\Domain\Platform\Services\NavigationMenu;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'timezone' => config('app.display_timezone'),
            'auth' => [
                'user' => $request->user(),
            ],
            'navigation' => fn (): array => app(NavigationMenu::class)->forUser($request->user()),
            'cycles' => fn (): array => $request->user()
                ? AdmissionCycle::query()->orderByDesc('year')->get(['id', 'name', 'year', 'status', 'is_current'])
                    ->map(fn (AdmissionCycle $c) => ['id' => $c->id, 'name' => $c->name, 'year' => $c->year, 'status' => $c->status->value, 'is_current' => $c->is_current])->all()
                : [],
            'currentCycle' => fn (): ?array => ($cycle = $request->user() ? app(CurrentCycle::class)->get() : null)
                ? ['id' => $cycle->id, 'name' => $cycle->name, 'year' => $cycle->year, 'status' => $cycle->status->value, 'is_current' => $cycle->is_current]
                : null,
            'telephony' => fn (): bool => (bool) $request->user()?->can('call.view'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
