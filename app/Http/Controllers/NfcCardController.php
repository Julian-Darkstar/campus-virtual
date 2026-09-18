<?php

namespace App\Http\Controllers;

use App\Events\CredentialChanged;
use App\Models\NfcCard;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NfcCardController extends Controller
{
    /**
     * Mostrar las tarjetas NFC registradas.
     *
     * Antes cualquier usuario autenticado veia TODAS las tarjetas de
     * TODOS los estudiantes. Ahora: un admin ve el listado completo;
     * cualquier otro usuario solo ve su(s) propia(s) tarjeta(s).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = NfcCard::with('user', 'registeredBy')->latest();

        if (! $user->hasRole(Role::ADMIN)) {
            $query->where('user_id', (string) $user->getKey());
        }

        return Inertia::render('NFC/Index', [
            'cards' => $query->get(),
        ]);
    }

    /**
     * Mostrar formulario para registrar una tarjeta NFC.
     * Operacion sensible de identidad: solo administracion.
     */
    public function create()
    {
        $this->authorize('create', NfcCard::class);

        $users = User::orderBy('name')->get([
            'id',
            'name',
            'email',
        ]);

        return Inertia::render('NFC/Create', [
            'users' => $users,
        ]);
    }

    /**
     * Registrar una nueva tarjeta NFC.
     * Operacion sensible de identidad: solo administracion.
     */
    public function store(Request $request)
    {
        $this->authorize('create', NfcCard::class);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'uid' => ['required', 'string', 'max:255', 'unique:nfc_cards,uid'],
        ], [
            'user_id.required' => 'Debes seleccionar un estudiante.',
            'user_id.exists' => 'El estudiante seleccionado no existe.',
            'uid.required' => 'Debes ingresar el UID de la tarjeta.',
            'uid.unique' => 'Esta tarjeta NFC ya esta registrada.',
        ]);

        $card = NfcCard::create([
            'user_id' => $validated['user_id'],
            'uid' => $validated['uid'],
            'registered_by' => auth()->id(),
            'status' => 'active',
            'registered_at' => now(),
        ]);

        // Registrar el evento de creacion en el historial.
        $card->credentialEvents()->create([
            'performed_by' => auth()->id(),
            'event_type' => 'registered',
            'reason' => 'Registro inicial de tarjeta NFC',
            'previous_status' => null,
            'new_status' => 'active',
        ]);

        CredentialChanged::dispatch((string) $card->getKey(), 'nfc', 'registered', 'active', (string) auth()->id());

        return redirect()
            ->route('nfc-cards.index')
            ->with('success', 'Tarjeta NFC registrada correctamente.');
    }

    /**
     * Cambiar el estado de una tarjeta NFC.
     * Ciclo de vida de credenciales: solo administracion.
     *
     * Estados disponibles:
     * - active     = Activa
     * - blocked    = Bloqueada
     * - suspended  = Suspendida
     * - replaced   = Reemplazada
     */
    public function updateStatus(Request $request, NfcCard $nfcCard)
    {
        $this->authorize('updateStatus', $nfcCard);

        $validated = $request->validate([
            'status' => [
                'required',
                'in:active,blocked,suspended,replaced',
            ],
            'reason' => [
                'required',
                'string',
                'max:500',
            ],
        ], [
            'status.required' => 'Debes seleccionar un estado.',
            'status.in' => 'El estado seleccionado no es valido.',
            'reason.required' => 'Debes indicar el motivo del cambio.',
            'reason.max' => 'El motivo no puede superar los 500 caracteres.',
        ]);

        $previousStatus = $nfcCard->status;
        $newStatus = $validated['status'];

        // Evitar registrar un cambio si el estado ya es el mismo.
        if ($previousStatus === $newStatus) {
            return back()->withErrors([
                'status' => 'La tarjeta ya tiene ese estado.',
            ]);
        }

        // Actualizar el estado de la tarjeta.
        $nfcCard->update([
            'status' => $newStatus,

            // Guardar cuando fue bloqueada.
            'blocked_at' => $newStatus === 'blocked'
                ? now()
                : $nfcCard->blocked_at,

            // Guardar cuando fue reemplazada.
            'replaced_at' => $newStatus === 'replaced'
                ? now()
                : $nfcCard->replaced_at,
        ]);

        // Registrar el cambio en el historial.
        $nfcCard->credentialEvents()->create([
            'performed_by' => auth()->id(),
            'event_type' => $newStatus,
            'reason' => $validated['reason'],
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
        ]);

        CredentialChanged::dispatch(
            (string) $nfcCard->getKey(),
            'nfc',
            'status_changed',
            $newStatus,
            (string) auth()->id(),
        );

        return redirect()
            ->route('nfc-cards.index')
            ->with(
                'success',
                'El estado de la tarjeta se actualizo correctamente.'
            );
    }

    /**
     * Mostrar el historial de una tarjeta NFC.
     * El dueno de la tarjeta puede ver su propio historial; un admin
     * puede ver el de cualquiera.
     */
    public function history(NfcCard $nfcCard)
    {
        $this->authorize('view', $nfcCard);

        $events = $nfcCard->credentialEvents()
            ->with('performedBy')
            ->latest()
            ->get();

        return Inertia::render('NFC/History', [
            'card' => $nfcCard->load('user'),
            'events' => $events,
        ]);
    }
}
