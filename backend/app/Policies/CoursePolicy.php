<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use App\Support\Authorize;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return Authorize::allows($user, 'manage_courses');
    }

    public function view(User $user, Course $course): bool
    {
        return Authorize::isAdmin($user)
            || (int) $course->user_id === (int) $user->id
            || Authorize::allows($user, 'manage_courses');
    }

    public function create(User $user): bool
    {
        return Authorize::allows($user, 'manage_courses');
    }

    public function update(User $user, Course $course): bool
    {
        return Authorize::ownsCourse($user, $course, 'manage_courses');
    }

    public function delete(User $user, Course $course): bool
    {
        return Authorize::ownsCourse($user, $course, 'manage_courses');
    }

    public function publish(User $user, Course $course): bool
    {
        return Authorize::ownsCourse($user, $course, 'manage_courses');
    }
}
