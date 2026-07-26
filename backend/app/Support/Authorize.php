<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Authorize
{
    /**
     * True if admin (superuser) or user has any of the given permissions.
     */
    public static function allows(User $user, string|array $permissions): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $list = is_array($permissions) ? $permissions : [$permissions];

        return $user->canAny($list);
    }

    /**
     * Return 403 JSON when denied, otherwise null (controller early-return style).
     */
    public static function forbidden(User $user, string|array $permissions, string $message = 'Forbidden'): ?JsonResponse
    {
        if (self::allows($user, $permissions)) {
            return null;
        }

        return response()->json(['message' => $message], 403);
    }

    /**
     * Throw 403 when denied (for use inside policies / nested calls).
     */
    public static function assert(User $user, string|array $permissions, string $message = 'Forbidden'): void
    {
        if (! self::allows($user, $permissions)) {
            throw new HttpException(403, $message);
        }
    }

    public static function isAdmin(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Admin or course owner (after permission already granted elsewhere, or with perm).
     */
    public static function ownsCourse(User $user, $course, string|array $permissions = 'manage_courses'): bool
    {
        if (! self::allows($user, $permissions)) {
            return false;
        }

        if (self::isAdmin($user)) {
            return true;
        }

        return (int) $course->user_id === (int) $user->id;
    }
}
