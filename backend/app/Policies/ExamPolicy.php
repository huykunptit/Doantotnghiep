<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;
use App\Support\Authorize;

class ExamPolicy
{
    public function viewAny(User $user): bool
    {
        return Authorize::allows($user, 'manage_exams');
    }

    public function view(User $user, Exam $exam): bool
    {
        return Authorize::allows($user, ['manage_exams', 'take_exams']);
    }

    public function create(User $user): bool
    {
        return Authorize::allows($user, 'manage_exams');
    }

    public function update(User $user, Exam $exam): bool
    {
        return Authorize::allows($user, 'manage_exams');
    }

    public function delete(User $user, Exam $exam): bool
    {
        return Authorize::allows($user, 'manage_exams');
    }

    public function proctor(User $user, Exam $exam): bool
    {
        return Authorize::allows($user, 'manage_exams');
    }
}
