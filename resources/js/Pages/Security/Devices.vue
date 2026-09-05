<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ devices: { type: Array, default: () => [] } });
const form = ref({ device_name: '', device_type: 'browser', uuid: '', mac_address: '' });
const saving = ref(false);

function linkDevice() {
    saving.value = true;
    router.post(route('security.devices.store'), form.value, { onFinish: () => { saving.value = false; form.value.device_name = ''; form.value.uuid = ''; form.value.mac_address = ''; } });
}
</script>

<template>
    <Head title="Dispositivos vinculados" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-2xl font-bold text-[#00338D]">Dispositivos vinculados</h2></template>
        <div class="min-h-[calc(100vh-9rem)] bg-[#F5F8FC] px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-5xl space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="font-bold text-slate-800">Vincular un dispositivo</h3>
                    <form class="mt-4 grid gap-3 md:grid-cols-4" @submit.prevent="linkDevice">
                        <input v-model="form.device_name" required placeholder="Nombre del dispositivo" class="rounded-lg border-slate-300 text-sm" />
                        <input v-model="form.uuid" placeholder="UUID (opcional)" class="rounded-lg border-slate-300 text-sm" />
                        <input v-model="form.mac_address" placeholder="MAC (opcional)" class="rounded-lg border-slate-300 text-sm" />
                        <button :disabled="saving" class="rounded-lg bg-[#00338D] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0284C7]">Vincular</button>
                    </form>
                </section>
                <section class="grid gap-4 md:grid-cols-2">
                    <article v-for="device in props.devices" :key="device._id" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4"><div><h3 class="font-bold text-slate-800">{{ device.device_name }}</h3><p class="mt-1 text-xs text-slate-500">{{ device.device_type }} · {{ device.ip_address || 'IP no disponible' }}</p></div><span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">{{ device.status }}</span></div>
                        <p class="mt-4 text-xs text-slate-500">Último acceso: {{ device.last_seen_at || 'Pendiente' }}</p>
                        <button class="mt-4 text-sm font-semibold text-rose-600 hover:underline" @click="router.delete(route('security.devices.destroy', device._id))">Desvincular</button>
                    </article>
                    <p v-if="!props.devices.length" class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 md:col-span-2">No hay dispositivos vinculados.</p>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>