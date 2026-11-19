<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Intake\Actions\SaveLeadIntegration;
use App\Domain\Intake\Enums\LeadEventStatus;
use App\Domain\Intake\Enums\LeadProvider;
use App\Domain\Intake\Models\LeadIntegration;
use App\Domain\Intake\Models\LeadWebhookEvent;
use App\Domain\Intake\Services\LeadFieldMapper;
use App\Domain\Shared\Support\DisplayTime;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\LeadIntegrationRequest;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /settings/lead-integrations (task 2.7): the Facebook Lead Ads / TikTok webhooks of the organization, with the last
 * leads each one received. Secrets are never sent to the browser (only whether they are set); the verify token is shown
 * to people who can manage integrations because it must be pasted into the Facebook app. Authorization: LeadIntegrationPolicy.
 */
class LeadIntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeadIntegration::class);
        $canManage = $request->user()->can('lead_integration.manage');

        return Inertia::render('LeadIntegrations/Index', [
            'integrations' => LeadIntegration::query()->with(['campaign:id,name', 'source:id,name'])->orderBy('name')->get()->map(fn (LeadIntegration $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'provider' => $i->provider->value,
                'provider_label' => $i->provider->label(),
                'is_active' => $i->is_active,
                'webhook_url' => $i->webhookUrl(),
                'campaign_id' => $i->campaign_id,
                'campaign_name' => $i->campaign?->name,
                'source_id' => $i->source_id,
                'source_name' => $i->source?->name,
                'has_secret' => filled($i->secret),
                'has_access_token' => filled($i->access_token),
                'verify_token' => $canManage ? $i->verify_token : null,
                'field_map' => collect($i->field_map ?? [])->map(fn ($key, $name) => ['name' => $name, 'key' => $key])->values()->all(),
                'last_received_at' => $this->time($i->last_received_at),
                'counts' => $this->counts($i),
                'events' => $this->events($i),
            ])->all(),
            'options' => [
                'providers' => array_map(fn (LeadProvider $p) => ['value' => $p->value, 'label' => $p->label()], LeadProvider::cases()),
                'campaigns' => Campaign::query()->orderBy('name')->get(['id', 'name'])->map(fn (Campaign $c) => ['value' => $c->id, 'label' => $c->name])->all(),
                'sources' => LeadSource::query()->orderBy('name')->get(['id', 'name'])->map(fn (LeadSource $s) => ['value' => $s->id, 'label' => $s->name])->all(),
                'lead_keys' => LeadFieldMapper::LABELS,
            ],
            'can' => ['manage' => $canManage],
        ]);
    }

    public function store(LeadIntegrationRequest $request, SaveLeadIntegration $save): RedirectResponse
    {
        $integration = $save->handle(null, $request->validated());

        return $this->toast("Đã thêm tích hợp {$integration->name}.");
    }

    public function update(LeadIntegrationRequest $request, int $id, SaveLeadIntegration $save): RedirectResponse
    {
        $integration = LeadIntegration::query()->findOrFail($id);
        $save->handle($integration, $request->validated());

        return $this->toast("Đã cập nhật tích hợp {$integration->name}.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $integration = LeadIntegration::query()->findOrFail($id);
        $this->authorize('delete', $integration);
        $integration->delete();

        return $this->toast("Đã xoá tích hợp {$integration->name}.");
    }

    /** @return array<string, int> status => number of leads */
    private function counts(LeadIntegration $integration): array
    {
        $counts = LeadWebhookEvent::query()->where('integration_id', $integration->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(LeadEventStatus::cases())->mapWithKeys(fn (LeadEventStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)])->all();
    }

    /** @return list<array<string, mixed>> */
    private function events(LeadIntegration $integration): array
    {
        return LeadWebhookEvent::query()->with('applicant:id,uuid,full_name')->where('integration_id', $integration->id)
            ->latest('id')->limit(10)->get()->map(fn (LeadWebhookEvent $e) => [
                'id' => $e->id,
                'status' => $e->status->value,
                'status_label' => $e->status->label(),
                'external_id' => $e->external_id,
                'error' => $e->error,
                'applicant' => $e->applicant === null ? null : ['uuid' => $e->applicant->uuid, 'name' => $e->applicant->full_name],
                'at' => $this->time($e->created_at),
            ])->all();
    }

    /** "2026-11-17 08:30" in display time, or null. */
    private function time(?CarbonInterface $time): ?string
    {
        return $time === null ? null : str_replace('T', ' ', (string) DisplayTime::toInput($time));
    }

    private function toast(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back(fallback: '/settings/lead-integrations');
    }
}
