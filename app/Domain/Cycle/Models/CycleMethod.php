<?php

namespace App\Domain\Cycle\Models;

use App\Domain\Catalog\Models\AdmissionMethod;
use Database\Factories\CycleMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admission method used in a cycle, with its score formula (docs/04-database.md §6),
 * conditions and required documents. At most 5 per cycle that are not direct admission (R1).
 *
 * @property int $id
 * @property int $cycle_id
 * @property int $method_id
 * @property string|null $display_name
 * @property bool $is_direct
 * @property array<string, mixed>|null $score_formula
 * @property array<string, mixed>|null $conditions
 * @property list<string>|null $required_documents
 */
#[Fillable(['cycle_id', 'method_id', 'display_name', 'is_direct', 'score_formula', 'conditions', 'required_documents'])]
#[UseFactory(CycleMethodFactory::class)]
class CycleMethod extends Model
{
    /** @use HasFactory<CycleMethodFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $attributes = ['is_direct' => false];

    protected function casts(): array
    {
        return [
            'is_direct' => 'boolean',
            'score_formula' => 'array',
            'conditions' => 'array',
            'required_documents' => 'array',
        ];
    }

    /**
     * @return BelongsTo<AdmissionCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class, 'cycle_id');
    }

    /**
     * @return BelongsTo<AdmissionMethod, $this>
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(AdmissionMethod::class, 'method_id');
    }
}
