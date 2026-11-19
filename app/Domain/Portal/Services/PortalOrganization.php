<?php

namespace App\Domain\Portal\Services;

use App\Domain\Organization\Models\Organization;

/** The organization the public pages and the portal belong to (config/portal.php). */
class PortalOrganization
{
    public function get(): ?Organization
    {
        $id = config('portal.organization_id');

        return $id ? Organization::query()->find($id) : Organization::query()->orderBy('id')->first();
    }
}
