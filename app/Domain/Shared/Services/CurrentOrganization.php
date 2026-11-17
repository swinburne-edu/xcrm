<?php

namespace App\Domain\Shared\Services;

use Illuminate\Support\Facades\Auth;

/**
 * Resolves the organization the current code path works for.
 *
 * - HTTP requests: the authenticated user's organization.
 * - Console, queue jobs, seeders: nothing by default (no tenant filter),
 *   unless an organization is set explicitly with set() / run().
 *
 * Registered as a scoped singleton (reset per request / job).
 */
class CurrentOrganization
{
    private ?int $override = null;

    public function id(): ?int
    {
        if ($this->override !== null) {
            return $this->override;
        }

        $organizationId = Auth::user()?->organization_id;

        return $organizationId === null ? null : (int) $organizationId;
    }

    /**
     * True when there is a tenant context: an explicit organization or a logged-in user.
     */
    public function hasContext(): bool
    {
        return $this->override !== null || Auth::check();
    }

    public function set(?int $organizationId): void
    {
        $this->override = $organizationId;
    }

    /**
     * Run a callback as the given organization, then restore the previous context.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(int $organizationId, callable $callback): mixed
    {
        $previous = $this->override;
        $this->override = $organizationId;

        try {
            return $callback();
        } finally {
            $this->override = $previous;
        }
    }
}
