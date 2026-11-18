<?php

namespace App\Domain\Catalog\Catalogs;

use App\Domain\Catalog\Models\Subject;
use App\Domain\Catalog\Models\SubjectCombination;
use App\Domain\Shared\Support\Listing\Filter;
use App\Http\Requests\Catalog\SubjectCombinationRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Combinations with their 3 weighted subjects (rule R2, validated in SubjectCombinationRequest).
 */
class SubjectCombinationCatalog extends CatalogDefinition
{
    public function key(): string
    {
        return 'combinations';
    }

    public function title(): string
    {
        return 'Tổ hợp xét tuyển';
    }

    public function description(): ?string
    {
        return 'Đúng 3 môn, bắt buộc có Toán hoặc Ngữ văn với trọng số ≥ 1/3 tổng trọng số.';
    }

    public function model(): string
    {
        return SubjectCombination::class;
    }

    public function request(): string
    {
        return SubjectCombinationRequest::class;
    }

    public function query(): Builder
    {
        return SubjectCombination::query()->with('subjects:id,code,name');
    }

    public function filters(): array
    {
        return [
            Filter::search('q', ['code', 'name']),
            Filter::callback(
                'subject_id',
                fn (Builder $query, array $ids) => $query->whereHas('subjects', fn (Builder $q) => $q->whereIn('subjects.id', $ids)),
                fn (mixed $value) => array_values(array_filter(array_map('intval', is_array($value) ? $value : explode(',', (string) $value)))) ?: null,
            ),
            Filter::boolean('is_active'),
        ];
    }

    public function sorts(): array
    {
        return ['code', 'name'];
    }

    /** @param  SubjectCombination  $model */
    public function row(Model $model): array
    {
        // Core subject (Toán / Ngữ văn) first, then a stable order.
        $subjects = $model->subjects->sortBy([['is_core', 'desc'], ['id', 'asc']])->values();

        return [
            'id' => $model->id,
            'code' => $model->code,
            'name' => $model->name,
            'is_active' => $model->is_active,
            'subjects_label' => $subjects->map(function (Subject $s): string {
                $weight = (float) $s->pivot->weight;

                return $weight === 1.0 ? $s->name : $s->name.' ×'.rtrim(rtrim(number_format($weight, 2, ',', ''), '0'), ',');
            })->implode(', '),
            'subjects' => $subjects->map(fn (Subject $s) => [
                'subject_id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'weight' => (float) $s->pivot->weight,
            ])->all(),
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Mã', 'type' => 'code', 'sortable' => true],
            ['key' => 'subjects_label', 'label' => 'Môn (trọng số)'],
            ['key' => 'name', 'label' => 'Tên', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Trạng thái', 'type' => 'boolean'],
        ];
    }

    public function filterDefinitions(): array
    {
        return [
            self::searchFilter('Mã, tên tổ hợp…'),
            ['key' => 'subject_id', 'label' => 'Có môn', 'type' => 'entity', 'entity' => 'subjects'],
            self::activeFilter(),
        ];
    }

    public function fields(): array
    {
        return [
            ['key' => 'code', 'label' => 'Mã', 'type' => 'text', 'required' => true, 'maxlength' => 10, 'placeholder' => 'VD: D01'],
            ['key' => 'subjects', 'label' => 'Môn và trọng số', 'type' => 'combination_subjects', 'required' => true],
            ['key' => 'name', 'label' => 'Tên', 'type' => 'text', 'help' => 'Để trống sẽ tự đặt theo tên các môn.'],
            ['key' => 'is_active', 'label' => 'Đang sử dụng', 'type' => 'boolean', 'default' => true],
        ];
    }

    /**
     * @param  SubjectCombination  $model
     * @param  array{code: string, name?: string|null, is_active?: bool, subjects: list<array{subject_id: int, weight: float|string}>}  $data
     */
    public function save(Model $model, array $data): Model
    {
        return DB::transaction(function () use ($model, $data): Model {
            $subjects = collect($data['subjects']);

            if (blank($data['name'] ?? null)) {
                $names = Subject::query()->whereIn('id', $subjects->pluck('subject_id'))->pluck('name', 'id');
                $data['name'] = $subjects->map(fn (array $s) => $names[$s['subject_id']] ?? '')->implode(', ');
            }

            $model->fill([
                'code' => $data['code'],
                'name' => $data['name'],
                'is_active' => $data['is_active'] ?? true,
            ])->save();

            $model->subjects()->sync(
                $subjects->mapWithKeys(fn (array $s) => [(int) $s['subject_id'] => ['weight' => $s['weight']]])->all(),
            );

            return $model;
        });
    }
}
