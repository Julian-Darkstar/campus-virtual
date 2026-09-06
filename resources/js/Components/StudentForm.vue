<script setup lang="ts">
import type { Campus, Option, StudentFormData } from '@/types';
import { Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft, Building2, Camera, Check, Contact, GraduationCap, ImagePlus,
    LoaderCircle, Mail, Save, ShieldCheck, UserRound, X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    student?: StudentFormData;
    campuses: Campus[];
    statuses: Option[];
    contactChannels: Option[];
}>();

const isEditing = computed(() => Boolean(props.student?.id));
const form = useForm({
    name: props.student?.name ?? '',
    email: props.student?.email ?? '',
    enrollment_number: props.student?.enrollment_number ?? '',
    campus_id: props.student?.campus_id ?? '',
    academic_program_id: props.student?.academic_program_id ?? '',
    current_semester: props.student?.current_semester ?? '',
    group_name: props.student?.group_name ?? '',
    academic_status: props.student?.academic_status ?? 'active',
    status_reason: '',
    personal_email: props.student?.personal_email ?? '',
    phone: props.student?.phone ?? '',
    preferred_contact_channel: props.student?.preferred_contact_channel ?? 'institutional_email',
    locale: props.student?.locale ?? 'es-MX',
    photo: null as File | null,
});

const originalStatus = props.student?.academic_status;
const photoPreview = ref<string | null>(props.student?.photo_url ?? null);
let objectUrl: string | null = null;

const availablePrograms = computed(() => props.campuses.find((item) => item.id === Number(form.campus_id))?.programs ?? []);
const selectedCampus = computed(() => props.campuses.find((item) => item.id === Number(form.campus_id)));
const statusChanged = computed(() => isEditing.value && originalStatus !== form.academic_status);

watch(() => form.campus_id, () => {
    if (!availablePrograms.value.some((program) => program.id === Number(form.academic_program_id))) {
        form.academic_program_id = '';
    }
});

function choosePhoto(event: Event) {
    const input = event.target as HTMLInputElement;
    form.photo = input.files?.[0] ?? null;
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = form.photo ? URL.createObjectURL(form.photo) : null;
    photoPreview.value = objectUrl ?? props.student?.photo_url ?? null;
}

function submit() {
    if (isEditing.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/students/${props.student!.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { form.photo = null; form.status_reason = ''; },
        });
    } else {
        form.post('/students', { forceFormData: true, preserveScroll: true });
    }
}

onBeforeUnmount(() => { if (objectUrl) URL.revokeObjectURL(objectUrl); });
</script>

<template>
    <form class="student-form" @submit.prevent="submit">
        <div class="form-main">
            <section class="form-card">
                <header class="form-section-header"><span><UserRound :size="20" /></span><div><h2>Datos de la cuenta</h2><p>Información con la que se identifica al estudiante en la plataforma.</p></div></header>
                <div class="form-grid two-columns">
                    <label class="field field-wide"><span>Nombre completo <b>*</b></span><div class="input-with-icon"><UserRound :size="17" /><input v-model="form.name" type="text" autocomplete="name" maxlength="120" placeholder="Ej. Andrea Mendoza López"></div><small v-if="form.errors.name" class="field-error">{{ form.errors.name }}</small></label>
                    <label class="field"><span>Correo institucional <b>*</b></span><div class="input-with-icon"><Mail :size="17" /><input v-model="form.email" type="email" autocomplete="email" placeholder="nombre@campusdigital.edu.mx"></div><small v-if="form.errors.email" class="field-error">{{ form.errors.email }}</small></label>
                    <label class="field"><span>Matrícula <b>*</b></span><input v-model="form.enrollment_number" type="text" maxlength="30" placeholder="20260001" @input="form.enrollment_number = form.enrollment_number.toUpperCase()"><small v-if="form.errors.enrollment_number" class="field-error">{{ form.errors.enrollment_number }}</small></label>
                </div>
            </section>

            <section class="form-card">
                <header class="form-section-header"><span><GraduationCap :size="21" /></span><div><h2>Información académica</h2><p>Ubicación y condición vigente dentro de la institución.</p></div></header>
                <div class="form-grid two-columns">
                    <label class="field"><span>Campus <b>*</b></span><div class="input-with-icon"><Building2 :size="17" /><select v-model="form.campus_id"><option value="" disabled>Selecciona un campus</option><option v-for="item in campuses" :key="item.id" :value="item.id">{{ item.name }}</option></select></div><small v-if="form.errors.campus_id" class="field-error">{{ form.errors.campus_id }}</small></label>
                    <label class="field"><span>Carrera <b>*</b></span><select v-model="form.academic_program_id" :disabled="!form.campus_id"><option value="" disabled>{{ form.campus_id ? 'Selecciona una carrera' : 'Selecciona primero un campus' }}</option><option v-for="item in availablePrograms" :key="item.id" :value="item.id">{{ item.name }}</option></select><small v-if="form.errors.academic_program_id" class="field-error">{{ form.errors.academic_program_id }}</small></label>
                    <label class="field"><span>Semestre <b>*</b></span><select v-model="form.current_semester"><option value="" disabled>Selecciona</option><option v-for="number in 20" :key="number" :value="number">{{ number }}.° semestre</option></select><small v-if="form.errors.current_semester" class="field-error">{{ form.errors.current_semester }}</small></label>
                    <label class="field"><span>Grupo</span><input v-model="form.group_name" type="text" maxlength="30" placeholder="Ej. A o ISC-4B"><small v-if="form.errors.group_name" class="field-error">{{ form.errors.group_name }}</small></label>
                    <label class="field"><span>Estatus académico <b>*</b></span><select v-model="form.academic_status"><option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select><small v-if="form.errors.academic_status" class="field-error">{{ form.errors.academic_status }}</small></label>
                    <label v-if="statusChanged" class="field"><span>Motivo del cambio <b>*</b></span><input v-model="form.status_reason" type="text" maxlength="300" placeholder="Describe el motivo"><small v-if="form.errors.status_reason" class="field-error">{{ form.errors.status_reason }}</small></label>
                </div>
                <div v-if="selectedCampus" class="inline-summary"><Check :size="17" /><span>El perfil quedará vinculado a <strong>{{ selectedCampus.name }}</strong> y será visible para los servicios autorizados.</span></div>
            </section>

            <section class="form-card">
                <header class="form-section-header"><span><Contact :size="20" /></span><div><h2>Contacto y preferencias</h2><p>Canales alternos para comunicaciones relacionadas con la cuenta.</p></div></header>
                <div class="form-grid two-columns">
                    <label class="field"><span>Correo personal</span><input v-model="form.personal_email" type="email" autocomplete="email" placeholder="correo@ejemplo.com"><small v-if="form.errors.personal_email" class="field-error">{{ form.errors.personal_email }}</small></label>
                    <label class="field"><span>Teléfono</span><input v-model="form.phone" type="tel" autocomplete="tel" maxlength="25" placeholder="55 1234 5678"><small v-if="form.errors.phone" class="field-error">{{ form.errors.phone }}</small></label>
                    <label class="field"><span>Canal preferido <b>*</b></span><select v-model="form.preferred_contact_channel"><option v-for="item in contactChannels" :key="item.value" :value="item.value">{{ item.label }}</option></select><small v-if="form.errors.preferred_contact_channel" class="field-error">{{ form.errors.preferred_contact_channel }}</small></label>
                    <label class="field"><span>Idioma de comunicación <b>*</b></span><select v-model="form.locale"><option value="es-MX">Español (México)</option><option value="en-US">English (US)</option></select><small v-if="form.errors.locale" class="field-error">{{ form.errors.locale }}</small></label>
                </div>
            </section>
        </div>

        <aside class="form-aside">
            <section class="form-card photo-card">
                <header><span><Camera :size="19" /></span><div><h2>Fotografía</h2><p>JPG, PNG o WebP · máx. 2 MB</p></div></header>
                <label class="photo-picker">
                    <input type="file" accept="image/jpeg,image/png,image/webp" @change="choosePhoto">
                    <img v-if="photoPreview" :src="photoPreview" alt="Vista previa de la fotografía">
                    <span v-else><ImagePlus :size="30" /><strong>Agregar foto</strong><small>Formato vertical recomendado</small></span>
                    <em>{{ photoPreview ? 'Cambiar fotografía' : 'Seleccionar archivo' }}</em>
                </label>
                <small v-if="form.errors.photo" class="field-error">{{ form.errors.photo }}</small>
            </section>

            <section class="form-card security-card">
                <span class="security-icon"><ShieldCheck :size="22" /></span>
                <div><h3>Activación segura</h3><p>La cuenta se crea sin contraseña. El módulo 1.2 enviará el flujo de activación y verificación.</p></div>
            </section>
        </aside>

        <footer class="form-actions">
            <Link href="/students" class="button button-ghost"><ArrowLeft :size="17" /> Volver al listado</Link>
            <button class="button button-primary" type="submit" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="spin" :size="18" />
                <Save v-else :size="18" />
                {{ form.processing ? 'Guardando…' : (isEditing ? 'Guardar cambios' : 'Crear estudiante') }}
            </button>
        </footer>
    </form>
</template>

