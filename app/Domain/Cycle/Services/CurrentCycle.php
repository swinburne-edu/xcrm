<?php

namespace App\Domain\Cycle\Services;

use App\Domain\Cycle\Enums\CycleLevel;
use App\Domain\Cycle\Models\AdmissionCycle;
use Illuminate\Contracts\Session\Session;

/**
 * The admission cycle every screen filters by (header selector, docs/03-tinh-nang.md M5).
 * The user's choice lives in the session; without one (or when it is no longer valid)
 * the organization's current cycle is used: university level first, else any current cycle,
 * else the most recent cycle. Cycles are scoped to the logged-in user's organization.
 *
 * Registered as a scoped singleton: resolved once per request.
 */
class CurrentCycle
{
    public const SESSION_KEY = 'cycle_id';

    private bool $resolved = false;

    private ?AdmissionCycle $cycle = null;

    public function __construct(private readonly Session $session) {}

    public function get(): ?AdmissionCycle
    {
        if (! $this->resolved) {
            $this->cycle = $this->fromSession() ?? $this->default();
            $this->resolved = true;
        }

        return $this->cycle;
    }

    public function id(): ?int
    {
        return $this->get()?->id;
    }

    /** Remember the user's choice for the rest of the session. */
    public function select(AdmissionCycle $cycle): void
    {
        $this->session->put(self::SESSION_KEY, $cycle->id);
        $this->cycle = $cycle;
        $this->resolved = true;
    }

    private function fromSession(): ?AdmissionCycle
    {
        $id = $this->session->get(self::SESSION_KEY);

        return $id === null ? null : AdmissionCycle::query()->find($id);
    }

    private function default(): ?AdmissionCycle
    {
        $current = AdmissionCycle::query()->where('is_current', true)->orderByDesc('year')->get();

        return $current->firstWhere('level', CycleLevel::University)
            ?? $current->first()
            ?? AdmissionCycle::query()->orderByDesc('year')->first();
    }
}
