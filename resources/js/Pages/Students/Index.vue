<script setup lang="ts">
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { PageLink } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowRight, CalendarClock, CheckCircle2, ChevronLeft, ChevronRight,
    CircleAlert, Download, FileSpreadsheet, Filter, MoreHorizontal, Plus,
    Search, Upload, UserRoundCheck, Users, X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';

type Student = {
    id: number; name: string; email: string; enrollment_number: string; campus: string;
    program: string; semester: number; group: string | null; status: { value: string; label: string };
    photo_url: string | null; updated_at: string;
};

type Paginated<T> = { data: T[]; links: PageLink[]; from: number | null; to: number | null; total: number };

const props = defineProps<{
    students: Paginated<Student>;
    filters: { search?: string; status?: string; campus?: number };
    campuses: { id: number; name: string }[];
    statuses: { value: string; label: string }[];
    statistics: { total: number; active: number; incomplete: number; recent: number };
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const campus = ref(props.filters.campus ? String(props.filters.campus) : '');
const importDialog = ref<HTMLDialogElement | null>(null);
const importForm = useForm<{ file: File | null }>({ file: null });
let debounce: ReturnType<typeof setTimeout>;

const hasFilters = computed(() => Boolean(search.value || status.value || campus.value));

watch([search, status, campus], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/students', {
            search: search.value || undefined,
            status: status.value || undefined,
            campus: campus.value || undefined,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 350);
});

function clearFilters() {
    search.value = '';
    status.value = '';
    campus.value = '';
}

function initials(name: string) {
    return name.split(' ').slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase();
}

function submitImport() {
    importForm.post('/students/import', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { importDialog.value?.close(); importForm.reset(); },
    });
}
</script>

<template>
    <Head title="Cuentas y perfiles" />
    <AppLayout title="Cuentas y perfiles" eyebrow="Módulo 1.1">
        <div class="page-heading">
            <div>
                <p>Administra la identidad académica y los datos de contacto de cada estudiante.</p>
            </div>
            <div class="heading-actions">
                <button class="button button-secondary" type="button" @click="importDialog?.showModal()"><Upload :size="18" /> Importar CSV</button>
                <Link href="/students/create" class="button button-primary"><Plus :size="18" /> Nuevo estudiante</Link>
            </div>
        </div>

        <div class="stat-grid">
            <article class="stat-card">
                <span class="stat-icon icon-blue"><Users :size="21" /></span>
                <div><small>Total de estudiantes</small><strong>{{ statistics.total }}</strong><span>Perfiles registrados</span></div>
            </article>
            <article class="stat-card">
                <span class="stat-icon icon-green"><UserRoundCheck :size="21" /></span>
                <div><small>Activos</small><strong>{{ statistics.active }}</strong><span>Con acceso vigente</span></div>
            </article>
            <article class="stat-card">
                <span class="stat-icon icon-warning"><CircleAlert :size="21" /></span>
                <div><small>Perfil incompleto</small><strong>{{ statistics.incomplete }}</strong><span>Requieren atención</span></div>
            </article>
            <article class="stat-card">
                <span class="stat-icon icon-graph"><CalendarClock :size="21" /></span>
                <div><small>Actualizados</small><strong>{{ statistics.recent }}</strong><span>Últimos 7 días</span></div>
            </article>
        </div>

        <section class="data-card">
            <div class="toolbar">
                <label class="search-field">
                    <Search :size="18" />
                    <input v-model="search" type="search" placeholder="Buscar por nombre, matrícula o correo" aria-label="Buscar estudiantes">
                    <button v-if="search" type="button" aria-label="Limpiar búsqueda" @click="search = ''"><X :size="16" /></button>
                </label>
                <div class="filter-fields">
                    <label class="select-wrap"><Filter :size="16" /><select v-model="status" aria-label="Filtrar por estatus"><option value="">Todos los estatus</option><option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select></label>
                    <label class="select-wrap"><select v-model="campus" aria-label="Filtrar por campus"><option value="">Todos los campus</option><option v-for="item in campuses" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
                    <button v-if="hasFilters" class="clear-filter" type="button" @click="clearFilters">Limpiar</button>
                </div>
            </div>

            <div v-if="students.data.length" class="table-scroll">
                <table class="student-table">
                    <thead><tr><th>Estudiante</th><th>Matrícula</th><th>Campus</th><th>Carrera</th><th>Semestre</th><th>Estatus</th><th><span class="sr-only">Acciones</span></th></tr></thead>
                    <tbody>
                        <tr v-for="student in students.data" :key="student.id">
                            <td>
                                <div class="student-cell">
                                    <img v-if="student.photo_url" :src="student.photo_url" :alt="`Fotografía de ${student.name}`">
                                    <span v-else class="avatar student-avatar">{{ initials(student.name) }}</span>
                                    <div><strong>{{ student.name }}</strong><small>{{ student.email }}</small></div>
                                </div>
                            </td>
                            <td><code>{{ student.enrollment_number }}</code></td>
                            <td>{{ student.campus }}</td>
                            <td><span class="program-name">{{ student.program }}</span></td>
                            <td>{{ student.semester }}<span v-if="student.group"> · {{ student.group }}</span></td>
                            <td><StatusBadge :status="student.status.value" :label="student.status.label" /></td>
                            <td><Link :href="`/students/${student.id}/edit`" class="row-action" :aria-label="`Editar ${student.name}`"><MoreHorizontal :size="20" /></Link></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState v-else />

            <footer v-if="students.total" class="pagination-row">
                <span>Mostrando {{ students.from }}–{{ students.to }} de {{ students.total }}</span>
                <nav aria-label="Paginación">
                    <template v-for="(link, index) in students.links" :key="index">
                        <Link v-if="link.url" :href="link.url" class="page-link" :class="{ active: link.active }" preserve-scroll preserve-state>
                            <ChevronLeft v-if="index === 0" :size="17" />
                            <ChevronRight v-else-if="index === students.links.length - 1" :size="17" />
                            <span v-else v-html="link.label" />
                        </Link>
                        <span v-else class="page-link disabled"><ChevronLeft v-if="index === 0" :size="17" /><ChevronRight v-else-if="index === students.links.length - 1" :size="17" /></span>
                    </template>
                </nav>
            </footer>
        </section>

        <dialog ref="importDialog" class="modal" @click.self="importDialog?.close()">
            <form class="modal-card" @submit.prevent="submitImport">
                <header><span class="modal-icon"><FileSpreadsheet :size="23" /></span><div><h2>Importar estudiantes</h2><p>Agrega o actualiza perfiles usando la plantilla CSV.</p></div><button type="button" aria-label="Cerrar" @click="importDialog?.close()"><X :size="20" /></button></header>
                <div class="modal-body">
                    <a href="/templates/student_import_template.csv" download class="template-download"><span><Download :size="19" /><span><strong>Descargar plantilla</strong><small>CSV · encabezados y ejemplo incluidos</small></span></span><ArrowRight :size="18" /></a>
                    <label class="drop-zone" :class="{ 'has-file': importForm.file }">
                        <input type="file" accept=".csv,text/csv" @change="importForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null">
                        <span class="drop-icon"><Upload :size="23" /></span>
                        <strong>{{ importForm.file?.name ?? 'Selecciona un archivo CSV' }}</strong>
                        <small>{{ importForm.file ? `${Math.ceil(importForm.file.size / 1024)} KB` : 'Tamaño máximo: 2 MB' }}</small>
                    </label>
                    <div v-if="importForm.errors.file" class="field-error import-errors">{{ importForm.errors.file }}</div>
                    <div class="notice notice-blue"><CheckCircle2 :size="18" /><span>Las matrículas existentes se actualizan; las nuevas se registran. Si una fila falla, no se importa ninguna.</span></div>
                </div>
                <footer><button type="button" class="button button-ghost" @click="importDialog?.close()">Cancelar</button><button type="submit" class="button button-primary" :disabled="!importForm.file || importForm.processing"><Upload :size="17" /> {{ importForm.processing ? 'Procesando…' : 'Iniciar importación' }}</button></footer>
            </form>
        </dialog>
    </AppLayout>
</template>

