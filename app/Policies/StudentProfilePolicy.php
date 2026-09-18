<?php

namespace App\Policies;

use App\Models\StudentProfile;
use App\Models\User;

class StudentProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('student_manager');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, StudentProfile $profile): bool
    {
        return $this->viewAny($user);
    }

    public function viewServices(User $user, StudentProfile $profile): bool
    {
        return (string) $user->getKey() === (string) $profile->user_id || $this->viewAny($user);
    }

    public function updateAcademicStatus(User $user, StudentProfile $profile): bool
    {
        return $this->viewAny($user);
    }
}
