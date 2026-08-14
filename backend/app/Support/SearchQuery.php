<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Accent-insensitive LIKE (MySQL 8 utf8mb4_0900_ai_ci).
 * "toan" khớp "Toán", không phân biệt hoa thường.
 */
class SearchQuery
{
    public const COLLATION = 'utf8mb4_0900_ai_ci';

    public static function like(Builder $query, array $columns, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '' || $columns === []) {
            return $query;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $query->where(function (Builder $inner) use ($columns, $like): void {
            foreach (array_values($columns) as $index => $column) {
                $sql = $column.' COLLATE '.self::COLLATION.' LIKE ?';
                if ($index === 0) {
                    $inner->whereRaw($sql, [$like]);
                } else {
                    $inner->orWhereRaw($sql, [$like]);
                }
            }
        });
    }
}
