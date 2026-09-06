<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import logoUrl from '@/assets/campus-digital-logo.jpg';
import { ArrowRight, LockKeyhole, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';

const page = usePage();
const demoMode = computed(() => Boolean(page.props.demoMode));
function enterDemo() { router.post('/demo/session'); }
</script>

<template>
    <Head title="Acceso" />
    <main class="login-page">
        <section class="login-brand-panel">
            <img :src="logoUrl" alt="Campus Digital · Plataforma Estudiantil">
            <div>
                <span class="login-chip"><ShieldCheck :size="16" /> Equipo 1</span>
                <h1>Una identidad para toda la vida universitaria.</h1>
                <p>Gestiona las cuentas y perfiles que conectan al estudiante con todos los servicios del campus.</p>
            </div>
            <small>Módulo 1.1 · Gestión de cuentas y perfil</small>
        </section>
        <section class="login-action-panel">
            <div class="login-card">
                <span class="login-lock"><LockKeyhole :size="25" /></span>
                <p class="eyebrow">Campus Digital</p>
                <h2>Acceso administrativo</h2>
                <p>La autenticación definitiva se integra en el módulo 1.2 mediante Laravel Fortify.</p>
                <button v-if="demoMode" class="button button-primary button-block" type="button" @click="enterDemo">
                    Entrar a la demostración <ArrowRight :size="18" />
                </button>
                <div v-else class="notice notice-warning">El acceso de demostración solo está disponible en ambiente local.</div>
                <span class="login-help">La sesión demo utiliza el administrador creado por el seeder.</span>
            </div>
        </section>
    </main>
</template>
