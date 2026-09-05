<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $devices = Device::where('user_id', (string) $request->user()->_id)
            ->latest('last_seen_at')->get();

        return Inertia::render('Security/Devices', ['devices' => $devices]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'device_name' => ['required', 'string', 'max:100'],
            'device_type' => ['nullable', 'string', 'max:40'],
            'uuid' => ['nullable', 'string', 'max:100'],
            'mac_address' => ['nullable', 'string', 'max:45'],
        ]);

        Device::create(array_merge($data, [
            'user_id' => (string) $request->user()->_id,
            'status' => 'active',
            'is_trusted' => false,
            'ip_address' => $request->ip(),
            'last_seen_at' => now(),
        ]));

        return back()->with('success', 'Dispositivo vinculado correctamente.');
    }

    public function destroy(Request $request, string $device)
    {
        Device::where('_id', $device)
            ->where('user_id', (string) $request->user()->_id)
            ->update(['status' => 'revoked', 'revoked_at' => now()]);

        return back()->with('success', 'Dispositivo desvinculado.');
    }
}