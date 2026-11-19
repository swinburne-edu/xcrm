<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Api\Enums\WebhookEvent;
use App\Domain\Api\Jobs\DeliverWebhook;
use App\Domain\Api\Models\Webhook;
use App\Domain\Api\Models\WebhookDelivery;
use App\Domain\Shared\Support\DisplayTime;
use App\Domain\Workflow\Services\SafeWebhookUrl;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /settings/api (task 3.5): API tokens of the user and the outgoing webhooks of the organization with their latest
 * deliveries. A webhook secret is shown once (flash), at creation or renewal. Authorization: WebhookPolicy, api_token.manage.
 */
class WebhookController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('webhook.view') || $user->can('api_token.manage'), 403);

        $time = fn ($t) => $t === null ? null : str_replace('T', ' ', (string) DisplayTime::toInput($t));

        return Inertia::render('ApiAccess/Index', [
            'tokens' => $user->can('api_token.manage') ? $user->tokens()->latest('id')->get()->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->name, 'abilities' => $t->abilities, 'last_used_at' => $time($t->last_used_at), 'expires_at' => $time($t->expires_at), 'created_at' => $time($t->created_at),
            ])->all() : [],
            'webhooks' => $user->can('webhook.view') ? Webhook::query()->orderBy('name')->get()->map(fn (Webhook $w) => [
                'id' => $w->id, 'name' => $w->name, 'url' => $w->url, 'events' => $w->events, 'is_active' => $w->is_active,
                'deliveries' => $w->deliveries()->limit(5)->get()->map(fn (WebhookDelivery $d) => [
                    'id' => $d->id, 'event' => $d->event, 'status' => $d->status, 'attempts' => $d->attempts, 'response_code' => $d->response_code, 'error' => $d->error, 'created_at' => $time($d->created_at),
                ])->all(),
            ])->all() : [],
            'events' => array_map(fn (WebhookEvent $e) => ['value' => $e->value, 'label' => $e->label()], WebhookEvent::cases()),
            'new_secret' => $request->session()->get('new_secret'),
            'new_token' => $request->session()->get('new_token'),
            'api_url' => url('/api/v1'),
            'can' => ['tokens' => $user->can('api_token.manage'), 'webhooks' => $user->can('create', Webhook::class)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Webhook::class);
        $data = $this->validated($request);
        $secret = Str::random(40);
        Webhook::query()->create($data + ['secret' => $secret, 'organization_id' => $request->user()->organization_id]);

        return $this->done('Đã tạo webhook.')->with('new_secret', $secret);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $webhook = Webhook::query()->findOrFail($id);
        $this->authorize('update', $webhook);
        $secret = null;

        if ($request->boolean('renew_secret')) {
            $secret = Str::random(40);
            $webhook->secret = $secret;
        }

        $webhook->fill($request->has('url') ? $this->validated($request) : $request->validate(['is_active' => ['sometimes', 'boolean']]))->save();
        $response = $this->done('Đã cập nhật webhook.');

        return $secret === null ? $response : $response->with('new_secret', $secret);
    }

    public function destroy(int $id): RedirectResponse
    {
        $webhook = Webhook::query()->findOrFail($id);
        $this->authorize('delete', $webhook);
        $webhook->delete();

        return $this->done('Đã xoá webhook.');
    }

    /** Send a delivery again (after a failure, or to test the receiver). */
    public function redeliver(int $id, int $deliveryId): RedirectResponse
    {
        $webhook = Webhook::query()->findOrFail($id);
        $this->authorize('update', $webhook);
        $delivery = WebhookDelivery::query()->where('webhook_id', $webhook->id)->findOrFail($deliveryId);
        $delivery->forceFill(['status' => 'pending', 'attempts' => 0, 'error' => null])->save();
        DeliverWebhook::dispatch($delivery->id);

        return $this->done('Đã xếp hàng gửi lại.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::enum(WebhookEvent::class)],
            'is_active' => ['boolean'],
        ]);

        if (($problem = app(SafeWebhookUrl::class)->check($data['url'])) !== null) {
            throw ValidationException::withMessages(['url' => $problem]);
        }

        return $data;
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
