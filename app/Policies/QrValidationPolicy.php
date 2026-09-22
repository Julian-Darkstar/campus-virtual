<?php

namespace App\Policies;

use App\Models\User;

/**
 * No corresponde a un modelo Eloquent puntual (una validación no
 * "pertenece" a nadie en el sentido de view/update): son reglas de
 * autorización sobre la ACCIÓN de validar, así que se autorizan con
 * $this->authorize('validate', \App\Models\QrValidation::class) del
 * lado del controlador (mismo patrón que RolePolicy::assign).
 */
class QrValidationPolicy
{
    /**
     * Validar el QR/código de OTRA persona (no el propio). Antes de
     * este cambio, cualquier usuario autenticado podía hacerlo.
     */
    public function validate(User $user): bool
    {
        return $user->isQrValidator();
    }

    /**
     * Pedir el nivel "full" (nombre sin enmascarar) al validar.
     * Subconjunto más restrictivo que validate(): no todo el que
     * puede validar necesita ver el nombre completo.
     */
    public function viewFull(User $user): bool
    {
        return $user->canRequestFullQrIdentity();
    }
}
