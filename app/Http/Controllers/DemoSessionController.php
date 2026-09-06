<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoSessionController extends Controller
{
    public function store(): RedirectResponse
    {
        abort_unless(app()->environment('local') && config('app.demo_mode'), 404);
        $admin = User::query()->where('is_platform_admin', true)->firstOrFail();
        Auth::login($admin);

        return to_route('students.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }
}

