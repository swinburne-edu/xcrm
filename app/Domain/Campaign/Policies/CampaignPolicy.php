<?php

namespace App\Domain\Campaign\Policies;

use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Shared\Policies\ResourcePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * campaign.* permissions plus the campaign rules:
 *  - a campaign of a closed cycle is read-only (R8);
 *  - it cannot be edited while waiting for approval, nor once ended;
 *  - only drafts are deleted (later it is ended instead);
 *  - approving or rejecting needs campaign.approve; submit / pause / resume / end need campaign.update.
 */
class CampaignPolicy extends ResourcePolicy
{
    protected function object(): string
    {
        return 'campaign';
    }

    /** @param  Campaign  $model */
    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model)
            && ! in_array($model->status, [CampaignStatus::PendingApproval, CampaignStatus::Ended], true)
            && ! $this->cycleClosed($model);
    }

    /** @param  Campaign  $model */
    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && $model->status === CampaignStatus::Draft && ! $this->cycleClosed($model);
    }

    /** Record or fix daily costs: allowed at any status (even after the campaign ended), but not in a closed cycle. */
    public function manageCosts(User $user, Campaign $campaign): bool
    {
        return $this->allows($user, 'update') && $this->visible($user, $campaign) && ! $this->cycleClosed($campaign);
    }

    /** Whether $user may move the campaign to $to right now. */
    public function transition(User $user, Campaign $campaign, CampaignStatus $to): bool
    {
        if ($this->cycleClosed($campaign) || ! $campaign->status->canMoveTo($to)) {
            return false;
        }

        $decision = $campaign->status === CampaignStatus::PendingApproval;

        return $this->allows($user, $decision ? 'approve' : 'update');
    }

    private function cycleClosed(Campaign $campaign): bool
    {
        return (bool) $campaign->cycle?->isClosed();
    }
}
