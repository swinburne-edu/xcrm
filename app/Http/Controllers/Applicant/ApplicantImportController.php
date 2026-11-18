<?php

namespace App\Http\Controllers\Applicant;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Intake\Actions\ApplicantImport;
use App\Domain\Intake\Jobs\ProcessApplicantImport;
use App\Domain\Intake\Models\ImportJob;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Excel import of applicants, in the three steps of <ImportWizard>:
 * POST /applicants/import/preview (headings, suggested mapping, dry run) →
 * POST /applicants/import (stores the file, queues the job) → GET /imports/{id} (progress and result).
 */
class ApplicantImportController extends Controller
{
    public function preview(Request $request, ApplicantImport $import): JsonResponse
    {
        $this->authorize('import', Applicant::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
            'mapping' => ['nullable', 'string'],
        ]);

        $file = $request->file('file');
        ['headings' => $headings, 'rows' => $rows] = $import->read($file);

        if ($headings === [] || $rows === []) {
            return response()->json(['message' => 'File không có dữ liệu. Dòng đầu tiên phải là tiêu đề cột.', 'errors' => ['file' => ['File không có dữ liệu.']]], 422);
        }

        $mapping = $this->mapping($request->input('mapping')) ?? $import->suggest($headings);

        return response()->json([
            'headings' => $headings,
            'mapping' => $mapping,
            'fields' => collect(ApplicantImport::FIELDS)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'preview' => $import->preview($request->user()->organization_id, $file, $mapping),
            'max_rows' => ApplicantImport::MAX_ROWS,
            'campaigns' => Campaign::query()->orderByDesc('id')->limit(100)->get(['id', 'name'])->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('import', Applicant::class);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
            'mapping' => ['required', 'string'],
            'campaign_id' => ['nullable', 'integer'],
        ]);

        $mapping = $this->mapping($data['mapping']);

        if ($mapping === null || ! isset($mapping['full_name']) || (! isset($mapping['phone']) && ! isset($mapping['email']))) {
            return response()->json(['message' => 'Cần ghép cột Họ và tên và Số điện thoại (hoặc Email).', 'errors' => ['mapping' => ['Cần ghép cột Họ và tên và Số điện thoại (hoặc Email).']]], 422);
        }

        $campaignId = isset($data['campaign_id']) ? Campaign::query()->whereKey($data['campaign_id'])->value('id') : null;
        $file = $request->file('file');

        $job = ImportJob::query()->create([
            'organization_id' => $request->user()->organization_id,
            'object' => 'applicants',
            'file_path' => Storage::disk('local')->putFile('imports', $file),
            'original_name' => $file->getClientOriginalName(),
            'mapping' => $mapping,
            'campaign_id' => $campaignId,
            'user_id' => $request->user()->id,
        ]);

        ProcessApplicantImport::dispatch($job->id);

        return response()->json(['import' => $this->payload($job->refresh())], 202);
    }

    public function show(int $id): JsonResponse
    {
        $this->authorize('import', Applicant::class);

        return response()->json(['import' => $this->payload(ImportJob::query()->where('object', 'applicants')->findOrFail($id))]);
    }

    /** @return array<string, string>|null */
    private function mapping(mixed $json): ?array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($decoded)) {
            return null;
        }

        return array_filter($decoded, fn ($v, $k) => is_string($v) && $v !== '' && array_key_exists($k, ApplicantImport::FIELDS), ARRAY_FILTER_USE_BOTH);
    }

    /** @return array<string, mixed> */
    private function payload(ImportJob $job): array
    {
        return [
            'id' => $job->id,
            'status' => $job->status,
            'file' => $job->original_name,
            'total' => $job->total_rows,
            'success' => $job->success_rows,
            'duplicate' => $job->duplicate_rows,
            'error' => $job->error_rows,
            'errors' => $job->errors ?? [],
        ];
    }
}
