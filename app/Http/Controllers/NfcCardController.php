<?php

namespace App\Http\Controllers;

use App\Events\CredentialChanged;
use App\Models\NfcCard;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NfcCardController extends Controller
{
    /**
     * Mostrar las tarjetas NFC registradas.
     */
    public function index()
    {
        $cards = NfcCard::with('user', 'registeredBy')
            ->latest()
            ->get();

        return Inertia::render('NFC/Index', [
            'cards' => $cards,
        ]);
    }

    /**
     * Mostrar formulario para registrar una tarjeta NFC.
     */
    public function create()
    {
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
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'uid' => ['required', 'string', 'max:255', 'unique:nfc_cards,uid'],
        ], [
            'user_id.required' => 'Debes seleccionar un estudiante.',
            'user_id.exists' => 'El estudiante seleccionado no existe.',
            'uid.required' => 'Debes ingresar el UID de la tarjeta.',
            'uid.unique' => 'Esta tarjeta NFC ya está registrada.',
        ]);

        $card = NfcCard::create([
            'user_id' => $validated['user_id'],
            'uid' => $validated['uid'],
            'registered_by' => auth()->id(),
            'status' => 'active',
            'registered_at' => now(),
        ]);

        // Registrar el evento de creación en el historial.
        $card->credentialEvents()->create([
            'performed_by' => auth()->id(),
            'event_type' => 'registered',
            'reason' => 'Registro inicial de tarjeta NFC',
            'previous_status' => null,
            'new_status' => 'active',
        ]);

        CredentialChanged::dispatch(
            (string) $card->getKey(),
            'nfc',
            'registered',
            'active',
            (string) auth()->id()
        );

        return redirect()
            ->route('nfc-cards.index')
            ->with('success', 'Tarjeta NFC registrada correctamente.');
    }

    /**
     * Cambiar el estado de una tarjeta NFC.
     *
     * Estados disponibles:
     * - active     = Activa
     * - blocked    = Bloqueada
     * - suspended  = Suspendida
     * - replaced   = Reemplazada
     */
    public function updateStatus(Request $request, NfcCard $nfcCard)
    {
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
            'status.in' => 'El estado seleccionado no es válido.',
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
            'blocked_at' => $newStatus === 'blocked'
                ? now()
                : $nfcCard->blocked_at,
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
            (string) auth()->id()
        );

        return redirect()
            ->route('nfc-cards.index')
            ->with(
                'success',
                'El estado de la tarjeta se actualizó correctamente.'
            );
    }

    /**
     * Reemplazar una tarjeta NFC por una nueva.
     *
     * La tarjeta anterior queda como "replaced"
     * y la nueva tarjeta queda como "active".
     */
    public function replace(Request $request, NfcCard $nfcCard)
    {
        $validated = $request->validate([
            'uid' => [
                'required',
                'string',
                'max:255',
                'unique:nfc_cards,uid',
            ],
            'reason' => [
                'required',
                'string',
                'max:500',
            ],
        ], [
            'uid.required' => 'Debes ingresar el UID de la nueva tarjeta.',
            'uid.unique' => 'Esta tarjeta NFC ya está registrada.',
            'reason.required' => 'Debes indicar el motivo del reemplazo.',
            'reason.max' => 'El motivo no puede superar los 500 caracteres.',
        ]);

        // Evitar reemplazar una tarjeta que ya fue reemplazada.
        if ($nfcCard->status === 'replaced') {
            return back()->withErrors([
                'status' => 'Esta tarjeta ya fue reemplazada.',
            ]);
        }

        // Crear la nueva tarjeta para el mismo estudiante.
        $newCard = NfcCard::create([
            'user_id' => $nfcCard->user_id,
            'uid' => $validated['uid'],
            'registered_by' => auth()->id(),
            'status' => 'active',
            'registered_at' => now(),
            'replacement_of_card_id' => $nfcCard->getKey(),
        ]);

        // Marcar la tarjeta anterior como reemplazada.
        $nfcCard->update([
            'status' => 'replaced',
            'replaced_at' => now(),
            'replaced_by_card_id' => $newCard->getKey(),
        ]);

        // Registrar el reemplazo en el historial de la tarjeta anterior.
        $nfcCard->credentialEvents()->create([
            'performed_by' => auth()->id(),
            'event_type' => 'replaced',
            'reason' => $validated['reason'],
            'previous_status' => 'active',
            'new_status' => 'replaced',
        ]);

        // Registrar la creación de la nueva tarjeta en su historial.
        $newCard->credentialEvents()->create([
            'performed_by' => auth()->id(),
            'event_type' => 'registered',
            'reason' => 'Nueva tarjeta generada por reemplazo',
            'previous_status' => null,
            'new_status' => 'active',
        ]);

        // Notificar que cambió la credencial.
        CredentialChanged::dispatch(
            (string) $newCard->getKey(),
            'nfc',
            'replaced',
            'active',
            (string) auth()->id()
        );

        return redirect()
            ->route('nfc-cards.index')
            ->with(
                'success',
                'La tarjeta NFC fue reemplazada correctamente.'
            );
    }

    /**
     * Mostrar el historial de una tarjeta NFC.
     */
    public function history(NfcCard $nfcCard)
    {
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