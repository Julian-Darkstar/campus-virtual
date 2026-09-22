<?php

namespace App\Policies;

use App\Models\QrValidationContext;
use App\Models\Role;
use App\Models\User;

class QrValidationContextPolicy
{
    /**
     * Crear un contexto de validación (y listar los existentes para
     * usarlos al validar). Cualquier rol validador puede crear el
     * suyo — no solo administración.
     */
    public function create(User $user): bool
    {
        return $user->isQrValidator();
    }

    /**
     * Cancelarlo antes de tiempo. A propósito más restrictivo que
     * create(): solo quien lo creó (o un admin, por si la persona ya
     * no tiene acceso) puede cancelarlo — un colega validador puede
     * *usar* el contexto de otro mientras esté vigente, pero no
     * cancelárselo.
     */
    public function cancel(User $user, QrValidationContext $context): bool
    {
        return $user->hasRole(Role::ADMIN)
            || (string) $context->created_by === (string) $user->getKey();
    }
}
