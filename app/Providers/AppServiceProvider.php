<?php

namespace App\Providers;

use App\Models\StudentProfile;
use App\Policies\StudentProfilePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Domain services are resolved through constructor injection.
    }

    public function boot(): void
    {
        Gate::policy(StudentProfile::class, StudentProfilePolicy::class);
        Gate::define('import-students', fn ($user): bool => (bool) $user->is_platform_admin);
    }
}

