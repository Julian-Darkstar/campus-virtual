<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('campus:about', function (): void {
    $this->info('Campus Digital — Equipo 1 — Módulo 1.1');
})->purpose('Muestra la identidad del módulo instalado');

