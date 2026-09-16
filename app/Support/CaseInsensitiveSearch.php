<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Portable case-insensitive LIKE across drivers. Plain `where('col', 'like', ...)`
 * is case-insensitive on SQLite (default ASCII collation) and typically on
 * MySQL (default ci collations), but case-SENSITIVE on Postgres — switching
 * DB_CONNECTION from sqlite to pgsql silently changed search behavior
 * (confirmed live: searching "corte" no longer matched "Corte Clásico").
 * Wrapping both sides in LOWER() gives identical behavior on all three.
 *
 * $column must be a trusted, hardcoded column name from application code —
 * never pass user input here, it's interpolated directly into the query.
 */
class CaseInsensitiveSearch
{
    public static function apply(Builder $query, string $column, string $term, string $boolean = 'and'): Builder
    {
        return $query->whereRaw("LOWER({$column}) LIKE ?", ['%'.mb_strtolower($term).'%'], $boolean);
    }

    public static function orWhere(Builder $query, string $column, string $term): Builder
    {
        return self::apply($query, $column, $term, 'or');
    }
}
