<?php

namespace App\Policies;

use App\Models\QuestionBank;
use App\Models\User;
use App\Support\Authorize;

class QuestionBankPolicy
{
    public function viewAny(User $user): bool
    {
        return Authorize::allows($user, 'manage_exams');
    }

    public function view(User $user, QuestionBank $bank): bool
    {
        return Authorize::allows($user, 'manage_exams');
    }

    public function create(User $user): bool
    {
        return Authorize::allows($user, 'manage_exams');
    }

    public function update(User $user, QuestionBank $bank): bool
    {
        return Authorize::allows($user, 'manage_exams');
    }

    public function delete(User $user, QuestionBank $bank): bool
    {
        return Authorize::allows($user, 'manage_exams');
    }
}
