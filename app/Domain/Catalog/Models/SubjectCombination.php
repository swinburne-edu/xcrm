<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\SubjectCombinationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Admission subject combination (A00, D01…): exactly 3 subjects with weights (rule R2).
 *
 * @property int $id
 * @property string $code
 * @property string|null $name
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'is_active'])]
#[UseFactory(SubjectCombinationFactory::class)]
class SubjectCombination extends Model
{
    /** @use HasFactory<SubjectCombinationFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsToMany<Subject, $this>
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'combination_subjects', 'combination_id', 'subject_id')
            ->withPivot('weight');
    }
}
