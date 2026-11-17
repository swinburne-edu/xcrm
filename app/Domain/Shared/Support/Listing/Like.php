<?php

namespace App\Domain\Shared\Support\Listing;

use Illuminate\Database\Eloquent\Builder;

/**
 * Portable "column LIKE %term%" with % and _ in the term matched literally.
 * Uses ESCAPE '!' because MySQL and SQLite disagree on backslash escaping.
 */
final class Like
{
    public static function pattern(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
    }

    /**
     * Adds "(col1 LIKE ? OR col2 LIKE ? …)" to the query.
     *
     * @param  list<string>  $columns
     */
    public static function anyColumn(Builder $query, array $columns, string $term): void
    {
        $pattern = self::pattern($term);
        $grammar = $query->getQuery()->getGrammar();

        $query->where(function (Builder $q) use ($columns, $pattern, $grammar): void {
            foreach ($columns as $column) {
                $q->orWhereRaw($grammar->wrap($q->qualifyColumn($column))." like ? escape '!'", [$pattern]);
            }
        });
    }
}
