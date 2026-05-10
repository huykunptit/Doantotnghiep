<?php

namespace App\Concerns;

use App\Models\User;
use App\Models\UserAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait ScopesByUnit
{
    protected function activeUnitIdsFor(User $user): Collection
    {
        return UserAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('unit_id')
            ->values();
    }

    protected function isAdminUser(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Limit a query to rows whose `$unitColumn` matches one of the user's active units.
     * Admin bypasses; non-admin without assignments returns empty set.
     */
    protected function scopeByUnitColumn(Builder $query, User $user, string $unitColumn = 'unit_id'): Builder
    {
        if ($this->isAdminUser($user)) {
            return $query;
        }

        $activeUnitIds = $this->activeUnitIdsFor($user);
        if ($activeUnitIds->isEmpty()) {
            return $query->whereRaw('1=0');
        }

        return $query->whereIn($unitColumn, $activeUnitIds);
    }

    /**
     * Limit courses to those linked (via program OR major) to the user's active units.
     */
    protected function scopeCoursesByUnits(Builder $query, User $user): Builder
    {
        if ($this->isAdminUser($user)) {
            return $query;
        }

        $activeUnitIds = $this->activeUnitIdsFor($user);
        if ($activeUnitIds->isEmpty()) {
            return $query->whereRaw('1=0');
        }

        return $query->where(function (Builder $q) use ($activeUnitIds) {
            $q->whereHas('program', fn (Builder $p) => $p->whereIn('unit_id', $activeUnitIds))
              ->orWhereHas('major', fn (Builder $m) => $m->whereIn('unit_id', $activeUnitIds));
        });
    }

    /**
     * Limit enrollments to those whose course is linked (via program OR major) to the user's active units.
     */
    protected function scopeEnrollmentsByUnits(Builder $query, User $user): Builder
    {
        if ($this->isAdminUser($user)) {
            return $query;
        }

        $activeUnitIds = $this->activeUnitIdsFor($user);
        if ($activeUnitIds->isEmpty()) {
            return $query->whereRaw('1=0');
        }

        return $query->whereHas('course', function (Builder $c) use ($activeUnitIds) {
            $c->where(function (Builder $q) use ($activeUnitIds) {
                $q->whereHas('program', fn (Builder $p) => $p->whereIn('unit_id', $activeUnitIds))
                  ->orWhereHas('major', fn (Builder $m) => $m->whereIn('unit_id', $activeUnitIds));
            });
        });
    }
}
