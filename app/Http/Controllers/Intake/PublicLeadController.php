<?php

namespace App\Http\Controllers\Intake;

use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Intake\Actions\IntakeLead;
use App\Domain\Intake\Models\Form;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/public/leads: the endpoint of the embedded web forms (anonymous, CORS-open, rate limited).
 *  - form_uuid picks the organization, source and campaign; unknown / inactive forms are 404.
 *  - "hp" is a honeypot: a filled one is answered like a success and dropped.
 *  - The rest goes through IntakeLead (flow F3): duplicate check, case, owner, first-call task.
 */
class PublicLeadController extends Controller
{
    public function __invoke(Request $request, IntakeLead $intake): JsonResponse
    {
        $request->validate(['form_uuid' => ['required', 'uuid']]);

        $form = Form::query()->withoutGlobalScopes()->where('uuid', $request->string('form_uuid')->toString())->where('is_active', true)->first();

        if ($form === null) {
            return response()->json(['message' => 'Biểu mẫu không tồn tại hoặc đã ngừng nhận đăng ký.'], 404);
        }

        $message = $form->success_message ?: 'Cảm ơn bạn! Nhà trường sẽ liên hệ trong thời gian sớm nhất.';

        if ($request->filled('hp')) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        $shown = collect($form->fields)->pluck('key')->all();
        $required = collect($form->fields)->where('required', true)->pluck('key')->all();
        $input = collect($request->only(array_keys(Form::AVAILABLE_FIELDS)))->only($shown)->all();

        foreach ($required as $key) {
            if (blank($input[$key] ?? null)) {
                throw ValidationException::withMessages([$key => 'Vui lòng nhập '.mb_strtolower(Form::AVAILABLE_FIELDS[$key]).'.']);
            }
        }

        $sourceId = $form->source_id ?? LeadSource::query()->withoutGlobalScopes()->where('organization_id', $form->organization_id)->where('code', 'website')->value('id');

        $intake->handle($form->organization_id, $this->strings($input) + ['consent' => true], [
            'source_id' => $sourceId,
            'campaign_id' => $form->campaign_id,
            'form_id' => $form->id,
            'label' => "Đăng ký qua biểu mẫu “{$form->name}”",
            'utm' => [
                'source' => $request->input('utm_source'),
                'medium' => $request->input('utm_medium'),
                'campaign' => $request->input('utm_campaign'),
                'content' => $request->input('utm_content'),
            ],
            'payload' => ['form' => $form->name, 'referer' => $request->headers->get('referer')],
        ]);

        return response()->json(['ok' => true, 'message' => $message], 201);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function strings(array $input): array
    {
        return array_map(fn ($v) => is_scalar($v) ? mb_substr(trim((string) $v), 0, 1000) : '', $input);
    }
}
