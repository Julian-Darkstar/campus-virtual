<script setup lang="ts">
import FlashToast from '@/Components/FlashToast.vue';
import logoUrl from '@/assets/campus-digital-logo.jpg';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bell, BookOpen, ChevronDown, CreditCard, GraduationCap, LayoutDashboard,
    LogOut, Menu, QrCode, Settings, ShieldCheck, Users, X,
} from '@lucide/vue';
import { computed, ref } from 'vue';

defineProps<{ title: string; eyebrow?: string }>();

const page = usePage();
const mobileOpen = ref(false);
const flashMessage = computed(() => (page.props.flash as { success?: string | null })?.success);
const user = computed(() => (page.props.auth as { user?: { name: string; email: string } | null })?.user);

const nav = [
    { label: 'Resumen', icon: LayoutDashboard, href: '#', disabled: true },
    { label: 'Cuentas y perfiles', icon: Users, href: '/students', active: true },
    { label: 'Roles y permisos', icon: ShieldCheck, href: '#', disabled: true },
    { label: 'Credenciales NFC', icon: CreditCard, href: '#', disabled: true },
    { label: 'Identidad QR', icon: QrCode, href: '#', disabled: true },
    { label: 'Catálogos académicos', icon: BookOpen, href: '#', disabled: true },
];

function logout() {
    router.delete('/demo/session');
}
</script>

<template>
    <div class="app-shell">
        <button class="mobile-menu" type="button" aria-label="Abrir navegación" @click="mobileOpen = true">
            <Menu :size="22" />
        </button>
        <div v-if="mobileOpen" class="sidebar-backdrop" @click="mobileOpen = false" />

        <aside class="sidebar" :class="{ 'sidebar-open': mobileOpen }">
            <div class="brand-block">
                <img :src="logoUrl" alt="Campus Digital · Plataforma Estudiantil" class="brand-logo">
                <button class="sidebar-close" type="button" aria-label="Cerrar navegación" @click="mobileOpen = false">
                    <X :size="20" />
                </button>
            </div>

            <div class="team-label">
                <span class="team-icon"><GraduationCap :size="18" /></span>
                <span><small>Equipo 1</small>Identidad y acceso</span>
            </div>

            <nav class="sidebar-nav" aria-label="Navegación principal">
                <p class="nav-caption">Módulos</p>
                <component
                    :is="item.disabled ? 'span' : Link"
                    v-for="item in nav"
                    :key="item.label"
                    :href="item.href"
                    class="nav-item"
                    :class="{ active: item.active, disabled: item.disabled }"
                    @click="mobileOpen = false"
                >
                    <component :is="item.icon" :size="19" />
                    <span>{{ item.label }}</span>
                    <small v-if="item.disabled">Próximo</small>
                </component>
            </nav>

            <div class="sidebar-footer">
                <span class="nav-item disabled"><Settings :size="19" /><span>Configuración</span></span>
                <div class="sidebar-user">
                    <span class="avatar avatar-inverse">{{ user?.name?.charAt(0) ?? 'A' }}</span>
                    <div><strong>{{ user?.name ?? 'Usuario' }}</strong><small>{{ user?.email }}</small></div>
                    <button type="button" title="Cerrar sesión" aria-label="Cerrar sesión" @click="logout"><LogOut :size="18" /></button>
                </div>
            </div>
        </aside>

        <main class="main-area">
            <header class="topbar">
                <div class="topbar-title">
                    <span>{{ eyebrow ?? 'Administración académica' }}</span>
                    <h1>{{ title }}</h1>
                </div>
                <div class="topbar-actions">
                    <button class="icon-button" type="button" aria-label="Notificaciones"><Bell :size="20" /><span class="notification-dot" /></button>
                    <button class="user-menu" type="button">
                        <span class="avatar">{{ user?.name?.charAt(0) ?? 'A' }}</span>
                        <span class="user-menu-copy"><strong>{{ user?.name }}</strong><small>Administrador</small></span>
                        <ChevronDown :size="16" />
                    </button>
                </div>
            </header>

            <section class="page-content">
                <slot />
            </section>
        </main>

        <FlashToast :message="flashMessage" />
    </div>
</template>
