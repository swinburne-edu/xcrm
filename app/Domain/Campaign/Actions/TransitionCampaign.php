<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Models\Campaign;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Moves a campaign along its approval flow (CampaignStatus). Who may do it is decided by
 * CampaignPolicy::transition. Approving stamps approved_by / approved_at; sending back to draft clears them.
 */
class TransitionCampaign
{
    public function handle(Campaign $campaign, CampaignStatus $to, User $by): Campaign
    {
        if (! $campaign->status->canMoveTo($to)) {
            throw ValidationException::withMessages(['status' => "Không thể chuyển từ \"{$campaign->status->label()}\" sang \"{$to->label()}\"."]);
        }

        $from = $campaign->status;
        $campaign->status = $to;

        if ($from === CampaignStatus::PendingApproval && $to === CampaignStatus::Running) {
            $campaign->approved_by = $by->id;
            $campaign->approved_at = now();
        }

        if ($to === CampaignStatus::Draft) {
            $campaign->approved_by = null;
            $campaign->approved_at = null;
        }

        $campaign->save();

        return $campaign;
    }
}
