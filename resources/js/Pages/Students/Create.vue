<script setup lang="ts">
import StudentForm from '@/Components/StudentForm.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Campus, Option } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{ campuses: Campus[]; statuses: Option[]; contactChannels: Option[] }>();
</script>

<template>
    <Head title="Nuevo estudiante" />
    <AppLayout title="Nuevo estudiante" eyebrow="Cuentas y perfiles">
        <div class="page-heading compact"><div><h2>Registrar cuenta estudiantil</h2><p>Completa los datos obligatorios. La activación de acceso se realizará desde el módulo 1.2.</p></div></div>
        <StudentForm :campuses="campuses" :statuses="statuses" :contact-channels="contactChannels" />
    </AppLayout>
</template>

