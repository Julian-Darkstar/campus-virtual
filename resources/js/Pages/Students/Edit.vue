<script setup lang="ts">
import StudentForm from '@/Components/StudentForm.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { Campus, Option, StudentFormData } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Clock3, KeyRound } from '@lucide/vue';

defineProps<{ student: StudentFormData; campuses: Campus[]; statuses: Option[]; contactChannels: Option[] }>();
</script>

<template>
    <Head :title="`Editar · ${student.name}`" />
    <AppLayout title="Editar perfil" eyebrow="Cuentas y perfiles">
        <div class="page-heading compact profile-heading">
            <div><h2>{{ student.name }}</h2><p>{{ student.enrollment_number }} · Revisa y actualiza su información académica.</p></div>
            <div class="profile-meta"><StatusBadge :status="student.academic_status" :label="statuses.find((item) => item.value === student.academic_status)?.label ?? student.academic_status" /><span v-if="student.activation_pending"><KeyRound :size="16" /> Activación pendiente</span></div>
        </div>
        <StudentForm :student="student" :campuses="campuses" :statuses="statuses" :contact-channels="contactChannels" />

        <section v-if="student.status_history?.length" class="history-card">
            <header><span><Clock3 :size="19" /></span><div><h2>Historial de condición académica</h2><p>Registro inmutable de altas y cambios de estatus.</p></div></header>
            <ol class="timeline">
                <li v-for="item in student.status_history" :key="item.id"><span class="timeline-dot" /><div><strong>{{ item.from ? `${item.from} → ${item.to}` : item.to }}</strong><p>{{ item.reason || 'Sin motivo capturado' }}</p><small>{{ item.changed_at }} · {{ item.changed_by }}</small></div></li>
            </ol>
        </section>
    </AppLayout>
</template>
