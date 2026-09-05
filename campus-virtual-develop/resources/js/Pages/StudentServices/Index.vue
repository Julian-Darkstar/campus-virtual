<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    student: { type: Object, required: true },
    consents: { type: Array, required: true },
    preferences: { type: Object, required: true },
});

const preferences = ref({ ...props.preferences });
const saved = ref(false);

function savePreferences() {
    saved.value = true;
    window.setTimeout(() => {
        saved.value = false;
    }, 2500);
}

function refreshData() {
    router.reload({ only: ['student', 'consents', 'preferences'] });
}
</script>

<template>
    <Head title="Mi condición y privacidad" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-[#0284C7]">Identidad estudiantil</p>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight text-[#00338D]">Mi condición y privacidad</h2>
                </div>
                <button class="text-sm font-semibold text-[#00338D] transition hover:text-[#0284C7]" type="button" @click="refreshData">
                    Actualizar datos
                </button>
            </div>
        </template>

        <div class="min-h-[calc(100vh-9rem)] bg-[#F5F8FC] px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-6xl space-y-6">
                <section class="overflow-hidden rounded-2xl bg-[#00338D] px-6 py-7 text-white shadow-xl shadow-[#00338D]/10 sm:px-8">
                    <div class="flex flex-col justify-between gap-6 md:flex-row md:items-center">
                        <div>
                            <p class="text-sm text-blue-100">Estado académico actual</p>
                            <div class="mt-3 flex items-center gap-3">
                                <span class="h-3 w-3 rounded-full bg-[#10B981] ring-4 ring-[#10B981]/20"></span>
                                <h3 class="text-3xl font-bold">{{ student.status_label }}</h3>
                            </div>
                            <p class="mt-3 max-w-xl text-sm leading-6 text-blue-100">Tu condición estudiantil se encuentra vigente y no presenta restricciones registradas.</p>
                        </div>
                        <div class="rounded-xl border border-white/20 bg-white/10 px-5 py-4 backdrop-blur-sm">
                            <p class="text-xs uppercase tracking-[0.18em] text-blue-100">Matrícula</p>
                            <p class="mt-1 text-lg font-semibold">{{ student.enrollment }}</p>
                            <p class="mt-2 text-xs text-blue-100">Vigente desde 15 ene 2026</p>
                        </div>
                    </div>
                </section>

                <div class="grid gap-6 lg:grid-cols-[1.05fr_1fr]">
                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#64748B]">Módulo 1.8</p>
                                <h3 class="mt-2 text-xl font-bold text-[#00338D]">Perfil académico</h3>
                            </div>
                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Verificado</span>
                        </div>
                        <dl class="mt-7 grid gap-5 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Estudiante</dt>
                                <dd class="mt-1 font-semibold text-slate-800">{{ student.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Identificador</dt>
                                <dd class="mt-1 font-semibold text-slate-800">{{ student.student_id }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Programa</dt>
                                <dd class="mt-1 font-semibold text-slate-800">{{ student.program }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Semestre</dt>
                                <dd class="mt-1 font-semibold text-slate-800">{{ student.semester }}º semestre</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Campus</dt>
                                <dd class="mt-1 font-semibold text-slate-800">{{ student.campus }}</dd>
                            </div>
                        </dl>
                        <div class="mt-7 border-t border-slate-100 pt-5 text-sm text-slate-500">
                            La condición puede cambiar cuando la institución actualice tu información académica.
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#64748B]">Módulo 1.9</p>
                            <h3 class="mt-2 text-xl font-bold text-[#00338D]">Consentimientos</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500">Controla qué usos de información has aceptado.</p>
                        </div>
                        <div class="mt-6 space-y-3">
                            <article v-for="consent in consents" :key="consent.id" class="flex items-start justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                                <div>
                                    <h4 class="font-semibold text-slate-800">{{ consent.name }}</h4>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ consent.description }}</p>
                                    <p class="mt-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Versión {{ consent.version }}</p>
                                </div>
                                <span :class="consent.status === 'accepted' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'" class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold">
                                    {{ consent.status === 'accepted' ? 'Aceptado' : 'Pendiente' }}
                                </span>
                            </article>
                        </div>
                    </section>
                </div>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#64748B]">Preferencias de comunicación</p>
                            <h3 class="mt-2 text-xl font-bold text-[#00338D]">Cómo quieres recibir novedades</h3>
                        </div>
                        <p v-if="saved" class="text-sm font-semibold text-[#10B981]">Preferencias actualizadas</p>
                    </div>
                    <div class="mt-6 grid gap-3 sm:grid-cols-3">
                        <label v-for="(enabled, channel) in preferences" :key="channel" class="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 px-4 py-4 transition hover:border-[#0284C7]">
                            <span class="font-semibold capitalize text-slate-700">{{ channel }}</span>
                            <input v-model="preferences[channel]" class="h-5 w-5 rounded border-slate-300 text-[#0284C7] focus:ring-[#0284C7]" type="checkbox" @change="savePreferences" />
                        </label>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>