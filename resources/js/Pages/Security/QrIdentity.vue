<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { BrowserQRCodeReader } from '@zxing/library';
import QRCode from 'qrcode';
import { onBeforeUnmount, ref } from 'vue';

defineProps({ recentValidations: { type: Array, default: () => [] }, ttlSeconds: { type: Number, default: 60 } });

const canvas = ref(null);
const video = ref(null);
const payload = ref('');
const seconds = ref(0);
const scanResult = ref(null);
const scanning = ref(false);
let timer;
let reader;

async function generate() {
    const { data } = await window.axios.post(route('identity.qr.generate'));
    payload.value = data.payload;
    seconds.value = data.seconds_remaining;
    await QRCode.toCanvas(canvas.value, data.payload, { width: 240, margin: 2, color: { dark: '#00338D' } });
    clearInterval(timer);
    timer = setInterval(() => { seconds.value = Math.max(0, seconds.value - 1); }, 1000);
}

async function startScanner() {
    scanning.value = true;
    reader = new BrowserQRCodeReader();
    try {
        const result = await reader.decodeOnceFromVideoDevice(undefined, video.value);
        const response = await window.axios.post(route('identity.qr.validate'), { code: result.getText(), context: 'escaneo-web' });
        scanResult.value = response.data;
    } catch (error) {
        scanResult.value = error.response?.data ?? { ok: false, result: 'No se pudo leer el código' };
    } finally {
        stopScanner();
    }
}

function stopScanner() {
    reader?.reset();
    scanning.value = false;
}

onBeforeUnmount(() => { clearInterval(timer); stopScanner(); });
</script>

<template>
    <Head title="Códigos QR" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-2xl font-bold text-[#00338D]">Códigos QR</h2></template>
        <div class="min-h-[calc(100vh-9rem)] bg-[#F5F8FC] px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-2">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Código dinámico de acceso</p>
                    <canvas ref="canvas" class="mx-auto my-6 rounded-xl border border-slate-100 p-2"></canvas>
                    <p class="font-mono text-xs text-slate-500">{{ payload || 'Genera un código para comenzar' }}</p>
                    <p class="mt-3 text-sm text-slate-500">Expira en <strong class="text-[#00338D]">{{ seconds }} s</strong></p>
                    <button class="mt-5 rounded-lg bg-[#00338D] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0284C7]" @click="generate">Generar / renovar</button>
                </section>
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Escanear código</p>
                    <video ref="video" class="mt-4 aspect-video w-full rounded-xl bg-slate-900 object-cover" autoplay muted></video>
                    <div class="mt-4 flex gap-3">
                        <button v-if="!scanning" class="rounded-lg bg-[#0284C7] px-4 py-2 text-sm font-semibold text-white" @click="startScanner">Activar cámara</button>
                        <button v-else class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600" @click="stopScanner">Detener</button>
                    </div>
                    <p v-if="scanResult" class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">Resultado: {{ scanResult.result }}</p>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>