<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Shared\Services\PhoneNormalizer;
use App\Domain\Shared\Support\Listing\Like;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /search?q=term → { applicants: [...], high_schools: [...] } for the Ctrl+K dialog.
 * Each group is returned only when the user holds {object}.view; data scope applies through the models.
 * Phone numbers are matched (typed as 09xx… or 84…) but never returned.
 */
class GlobalSearchController extends Controller
{
    private const LIMIT = 6;

    public function __invoke(Request $request, PhoneNormalizer $phones): JsonResponse
    {
        $term = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $user = $request->user();

        if (mb_strlen($term) < 2) {
            return response()->json(['applicants' => [], 'high_schools' => []]);
        }

        $applicants = $user->can('applicant.view') ? $this->applicants($term, $phones) : [];
        $schools = $user->can('high_school.view') ? $this->highSchools($term) : [];

        return response()->json(['applicants' => $applicants, 'high_schools' => $schools]);
    }

    /** @return list<array<string, mixed>> */
    private function applicants(string $term, PhoneNormalizer $phones): array
    {
        $normalized = preg_match('/^[\d\s.+()-]{8,}$/', $term) === 1 ? $phones->normalize($term) : null;

        return Applicant::query()
            ->whereNull('merged_into_id')
            ->with('highSchool:id,name')
            ->where(function (Builder $q) use ($term, $normalized): void {
                Like::anyColumn($q, ['full_name', 'code', 'email', 'phone_normalized'], $term);

                if ($normalized !== null) {
                    $q->orWhere('phone_normalized', 'like', $normalized.'%');
                }
            })
            ->orderBy('full_name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Applicant $a): array => [
                'id' => $a->uuid,
                'label' => $a->full_name,
                'description' => trim($a->code.($a->highSchool ? ' · '.$a->highSchool->name : '')),
                'url' => "/applicants/{$a->uuid}",
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function highSchools(string $term): array
    {
        return HighSchool::query()
            ->with('province:id,name')
            ->where(fn (Builder $q) => Like::anyColumn($q, ['name', 'code'], $term))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (HighSchool $s): array => [
                'id' => $s->id,
                'label' => $s->name,
                'description' => trim(($s->code ? $s->code.' · ' : '').($s->province?->name ?? '')),
                'url' => "/high-schools/{$s->id}",
            ])
            ->all();
    }
}
