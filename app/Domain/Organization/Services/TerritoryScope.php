<?php

namespace App\Domain\Organization\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Territory rule of the data scope (docs/02-phan-quyen.md): rows in the provinces / high schools
 * of the territories the user (or one of the user's teams) looks after.
 *
 * A model plugs it into HasDataScope::applyDataScopeTerritory():
 *
 *   public function applyDataScopeTerritory(Builder $query, User $user): void
 *   {
 *       TerritoryScope::apply($query, $this, $user, provinceColumn: 'province_id', highSchoolColumn: 'high_school_id');
 *   }
 *
 *   high_schools:  provinceColumn 'province_id', highSchoolColumn 'id'
 *   applicants:    provinceColumn 'province_id', highSchoolColumn 'high_school_id'
 *
 * A school assigned to its own territory leaves its province's territory (as in TerritoryResolver).
 */
final class TerritoryScope
{
    public static function apply(Builder $query, Model $model, User $user, ?string $provinceColumn = null, ?string $highSchoolColumn = null): void
    {
        if ($user->organization_id === null) {
            return;
        }

        $myTeams = DB::table('team_user')->select('team_id')->where('user_id', $user->id);
        $organization = fn () => DB::table('territories')->select('id')
            ->where('organization_id', $user->organization_id)->whereNull('deleted_at');

        $mine = $organization()->where(fn ($q) => $q->where('user_id', $user->id)->orWhereIn('team_id', $myTeams));
        $assigned = fn (string $type, $territories) => DB::table('territory_assignments')
            ->select('assignable_id')->where('assignable_type', $type)->whereIn('territory_id', $territories);

        if ($highSchoolColumn !== null) {
            $query->orWhereIn($model->qualifyColumn($highSchoolColumn), $assigned('high_school', $mine));
        }

        if ($provinceColumn !== null) {
            $query->orWhere(function (Builder $province) use ($model, $provinceColumn, $highSchoolColumn, $assigned, $mine, $organization): void {
                $province->whereIn($model->qualifyColumn($provinceColumn), $assigned('province', $mine));

                if ($highSchoolColumn !== null) {
                    // Schools with their own territory are not covered by the province's one.
                    $school = $model->qualifyColumn($highSchoolColumn);
                    $province->where(fn (Builder $q) => $q->whereNull($school)->orWhereNotIn($school, $assigned('high_school', $organization())));
                }
            });
        }
    }
}
