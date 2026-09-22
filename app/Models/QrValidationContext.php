<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Coleccion Mongo: qr_validation_contexts (Modulo 1.6)
 *
 * "Contexto de validación" = la sesión bajo la que un validador
 * (bibliotecario, cajero de negocio, agente de recarga/retiro,
 * agente de soporte o admin) valida QRs de estudiantes: por ejemplo
 * "Evento de Bienvenida - Auditorio", vigente hasta una fecha/hora
 * que el propio validador define, con opción de cancelarlo antes de
 * tiempo (error de dedo al crearlo, evento cancelado, etc.).
 *
 * Reemplaza el select de texto libre que existía antes (una lista
 * fija hardcodeada sin relación con quién validaba ni por cuánto
 * tiempo). Ver QrController::contexts()/storeContext()/cancelContext()
 * y App\Policies\QrValidationContextPolicy.
 */
class QrValidationContext extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'qr_validation_contexts';

    protected $fillable = [
        'name',
        'created_by',
        'ends_at',
        'cancelled_at',
    ];

    protected $casts = [
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Vigente = no cancelado y (sin fecha de cierre, o esa fecha
     * todavía no llega). Es la única fuente de verdad de si este
     * contexto puede usarse para validar; QrController la consulta
     * antes de aceptar cualquier validación bajo este contexto.
     */
    public function isActive(): bool
    {
        if ($this->cancelled_at !== null) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }
}
