<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            'canAssignRoles' => $user->hasRole(Role::ADMIN),
        ]);
    }

    /**
     * Asignar un rol a un usuario.
     *
     * P0 corregido: antes este endpoint solo comprobaba "¿esta
     * autenticado?" y asignaba el rol solicitado al propio usuario que
     * hacia la peticion, sin validar el nombre del rol contra ningun
     * catalogo. Eso permitia que cualquier usuario autenticado se
     * autoasignara el rol "admin" (escalacion de privilegios).
     *
     * Ahora:
     *  - Solo un usuario con rol "admin" puede llegar aqui
     *    (RolePolicy::assign, reforzado ademas por el middleware
     *    'role.context:admin' en routes/web.php).
     *  - El rol a asignar debe existir en el catalogo Role::VALID_ROLES.
     *  - El admin puede asignar el rol a si mismo o a otro usuario
     *    (user_id opcional); nadie mas puede asignarse roles.
     */
    public function assign(Request $request)
    {
        $this->authorize('assign', Role::class);

        $validated = $request->validate([
            'role_name' => ['required', 'string', Rule::in(Role::VALID_ROLES)],
            'user_id' => ['nullable', 'string', 'exists:users,_id'],
            'scope_type' => ['nullable', 'string', Rule::in(['business', 'association', 'service'])],
            'scope_id' => ['nullable', 'string', 'max:100'],
        ]);

        $expectedScope = Role::ROLE_SCOPE_TYPES[$validated['role_name']] ?? null;
        if ($expectedScope !== null) {
            if (($validated['scope_type'] ?? null) !== $expectedScope || blank($validated['scope_id'] ?? null)) {
                return back()->withErrors(['scope_type' => "El rol seleccionado requiere el ámbito {$expectedScope} y un identificador de ámbito."]);
            }
        } elseif (! empty($validated['scope_type']) || ! empty($validated['scope_id'])) {
            return back()->withErrors(['scope_type' => 'El rol seleccionado es global y no acepta un ámbito contextual.']);
        }

        $target = ($validated['user_id'] ?? null)
            ? User::findOrFail($validated['user_id'])
            : $request->user();

        $target->assignRole(
            $validated['role_name'],
            $validated['scope_type'] ?? null,
            $validated['scope_id'] ?? null
        );

        return redirect()->back()->with('success', 'Rol asignado correctamente.');
    }
}
