<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ScoreSource;
use Database\Factories\AdmissionMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admission method with the Ministry code (100, 200, 301, 401…).
 * Per-cycle configuration lives in cycle_methods (task 1.5).
 *
 * @property int $id
 * @property string $code
 * @property string|null $name
 * @property ScoreSource|null $score_source
 */
#[Fillable(['code', 'name', 'score_source'])]
#[UseFactory(AdmissionMethodFactory::class)]
class AdmissionMethod extends Model
{
    /** @use HasFactory<AdmissionMethodFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['score_source' => ScoreSource::class];
    }
}
