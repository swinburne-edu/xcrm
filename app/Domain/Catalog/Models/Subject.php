<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * School subject (TOAN, VAN, ANH…). is_core = Toán / Ngữ văn (rule R2).
 *
 * @property int $id
 * @property string $code
 * @property string|null $name
 * @property bool $is_core
 */
#[Fillable(['code', 'name', 'is_core'])]
#[UseFactory(SubjectFactory::class)]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    public const MATH = 'TOAN';

    public const LITERATURE = 'VAN';

    public $timestamps = false;

    protected $attributes = ['is_core' => false];

    protected function casts(): array
    {
        return ['is_core' => 'boolean'];
    }

    /**
     * @return BelongsToMany<SubjectCombination, $this>
     */
    public function combinations(): BelongsToMany
    {
        return $this->belongsToMany(SubjectCombination::class, 'combination_subjects', 'subject_id', 'combination_id')
            ->withPivot('weight');
    }
}
