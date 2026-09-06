<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        return Inertia::render('Roles/Index', [
            'availableRoles' => Role::all(),
            'userRoles' => $user->roles ?? [],
            'twoFactorEnabled' => ! empty($user->two_factor_secret),
        ]);
    }
    public function assign(Request $request)
    {
        $request->validate([
            'role_name' => 'required|string',
            'scope_type' => 'nullable|string',
            'scope_id' => 'nullable|string',
        ]);

        $user = $request->user();
        $user->assignRole(
            $request->input('role_name'),
            $request->input('scope_type'),
            $request->input('scope_id')
        );

        return redirect()->back()->with('success', 'Rol asignado correctamente.');
    }
}