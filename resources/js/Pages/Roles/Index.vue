<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    availableRoles: {
        type: Array,
        default: () => []
    },
    userRoles: {
        type: Array,
        default: () => []
    },
    twoFactorEnabled: {
        type: Boolean,
        default: null
    }
});

const page = usePage();

const localTwoFactorConfirmed = ref(null);
const qrSvg = ref('');
const secretKey = ref('');
const twoFactorCode = ref('');
const recoveryCodes = ref([]);
const loading2fa = ref(false);
const confirming2fa = ref(false);
const disabling2fa = ref(false);
const error2fa = ref('');
const success2fa = ref('');

const requiresPasswordConfirmation = ref(false);
const password = ref('');
const passwordError = ref('');
const passwordAction = ref('enable');

const isTwoFactorActive = computed(() => {
    if (localTwoFactorConfirmed.value !== null) {
        return localTwoFactorConfirmed.value;
    }

    if (props.twoFactorEnabled !== null && props.twoFactorEnabled !== undefined) {
        return props.twoFactorEnabled;
    }

    const authUser = page.props.auth?.user;
    return !!(authUser?.two_factor_enabled || authUser?.two_factor_confirmed_at);
});

const isTwoFactorSetupPending = computed(() => {
    return !isTwoFactorActive.value && !!qrSvg.value;
});

const jsonConfig = {
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
};

const roleForm = useForm({
    role_name: '',
    scope_type: '',
    scope_id: '',
});

const submitRole = () => {
    roleForm.post(route('roles.assign'), {
        onSuccess: () => roleForm.reset(),
    });
};

const clearTwoFactorMessages = () => {
    error2fa.value = '';
    success2fa.value = '';
    passwordError.value = '';
};

const loadTwoFactorSetup = async () => {
    const qrResponse = await axios.get('/user/two-factor-qr-code', jsonConfig);
    qrSvg.value = qrResponse.data.svg ?? '';

    try {
        const secretResponse = await axios.get('/user/two-factor-secret-key', jsonConfig);
        secretKey.value = secretResponse.data.secretKey ?? '';
    } catch (error) {
        // La clave manual es auxiliar. El QR puede seguir funcionando aunque esta llamada falle.
        console.warn('No fue posible obtener la clave manual 2FA.', error);
    }
};

const requestPasswordConfirmation = (action = 'enable') => {
    passwordAction.value = action;
    requiresPasswordConfirmation.value = true;
    password.value = '';
    passwordError.value = '';
};

const enable2FA = async () => {
    loading2fa.value = true;
    clearTwoFactorMessages();

    try {
        await axios.post('/user/two-factor-authentication', {}, jsonConfig);
        await loadTwoFactorSetup();
        success2fa.value = 'Escanea el código QR y confirma el código de 6 dígitos para terminar la configuración.';
    } catch (error) {
        if (error.response?.status === 423) {
            requestPasswordConfirmation('enable');
            return;
        }

        error2fa.value = error.response?.data?.message
            ?? 'No fue posible iniciar la configuración de autenticación en dos factores.';
    } finally {
        loading2fa.value = false;
    }
};

const confirmPasswordAndContinue = async () => {
    passwordError.value = '';

    if (!password.value) {
        passwordError.value = 'Ingresa tu contraseña actual.';
        return;
    }

    try {
        await axios.post('/user/confirm-password', {
            password: password.value,
        }, jsonConfig);

        requiresPasswordConfirmation.value = false;
        password.value = '';

        if (passwordAction.value === 'disable') {
            await disable2FA();
        } else if (passwordAction.value === 'regenerate') {
            await regenerateRecoveryCodes();
        } else {
            await enable2FA();
        }
    } catch (error) {
        passwordError.value = error.response?.data?.message
            ?? error.response?.data?.errors?.password?.[0]
            ?? 'La contraseña no es válida.';
    }
};

const loadRecoveryCodes = async () => {
    const response = await axios.get('/user/two-factor-recovery-codes', jsonConfig);
    recoveryCodes.value = Array.isArray(response.data)
        ? response.data
        : (response.data?.recoveryCodes ?? []);
};

const confirmTwoFactor = async () => {
    clearTwoFactorMessages();

    const code = twoFactorCode.value.replace(/\s+/g, '');
    if (!/^\d{6}$/.test(code)) {
        error2fa.value = 'Ingresa el código de 6 dígitos generado por Google Authenticator.';
        return;
    }

    confirming2fa.value = true;

    try {
        await axios.post('/user/confirmed-two-factor-authentication', {
            code,
        }, jsonConfig);

        localTwoFactorConfirmed.value = true;
        twoFactorCode.value = '';
        qrSvg.value = '';
        secretKey.value = '';
        success2fa.value = 'Autenticación en dos factores activada correctamente.';

        await loadRecoveryCodes();
        router.reload({ only: ['auth', 'twoFactorEnabled'] });
    } catch (error) {
        error2fa.value = error.response?.data?.message
            ?? error.response?.data?.errors?.code?.[0]
            ?? 'El código no es válido o ya expiró. Espera al siguiente código e inténtalo de nuevo.';
    } finally {
        confirming2fa.value = false;
    }
};

const regenerateRecoveryCodes = async () => {
    clearTwoFactorMessages();

    try {
        await axios.post('/user/two-factor-recovery-codes', {}, jsonConfig);
        await loadRecoveryCodes();
        success2fa.value = 'Se generaron nuevos códigos de recuperación.';
    } catch (error) {
        if (error.response?.status === 423) {
            requestPasswordConfirmation('regenerate');
            error2fa.value = 'Confirma tu contraseña para regenerar los códigos de recuperación.';
            return;
        }

        error2fa.value = error.response?.data?.message
            ?? 'No fue posible regenerar los códigos de recuperación.';
    }
};

const disable2FA = async () => {
    disabling2fa.value = true;
    clearTwoFactorMessages();

    try {
        await axios.delete('/user/two-factor-authentication', jsonConfig);

        localTwoFactorConfirmed.value = false;
        qrSvg.value = '';
        secretKey.value = '';
        twoFactorCode.value = '';
        recoveryCodes.value = [];
        success2fa.value = 'Autenticación en dos factores desactivada.';

        router.reload({ only: ['auth', 'twoFactorEnabled'] });
    } catch (error) {
        if (error.response?.status === 423) {
            requestPasswordConfirmation('disable');
            return;
        }

        error2fa.value = error.response?.data?.message
            ?? 'No fue posible desactivar la autenticación en dos factores.';
    } finally {
        disabling2fa.value = false;
    }
};

const resumePendingTwoFactorSetup = async () => {
    if (isTwoFactorActive.value) {
        return;
    }

    try {
        await loadTwoFactorSetup();
    } catch (error) {
        // 404/409/423 son normales cuando todavía no existe un secreto 2FA pendiente.
        if (![404, 409, 423].includes(error.response?.status)) {
            console.warn('No fue posible recuperar una configuración 2FA pendiente.', error);
        }
    }
};

onMounted(() => {
    resumePendingTwoFactorSetup();
});
</script>

<template>
    <Head title="Roles y Seguridad" />

    <AuthenticatedLayout>
        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Encabezado de sección estilo Campus Digital -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <span class="text-xs font-semibold tracking-wider text-blue-600 uppercase">
                            IDENTIDAD Y ACCESO (EQUIPO 1)
                        </span>
                        <h1 class="text-2xl font-bold text-gray-900">
                            Roles contextuales y seguridad
                        </h1>
                    </div>
                </div>

                <!-- Banner superior azul institucional (Módulo 1.2 Two-Factor) -->
                <div class="bg-gradient-to-r from-blue-900 to-blue-800 rounded-2xl p-6 sm:p-8 text-white shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                    <div class="space-y-2 max-w-2xl">
                        <span class="text-xs font-semibold uppercase tracking-wider text-blue-200">
                            MÓDULO 1.2 • FORTIFY 2FA
                        </span>
                        <div class="flex items-center gap-3">
                            <span
                                class="inline-block h-3.5 w-3.5 rounded-full"
                                :class="isTwoFactorActive ? 'bg-emerald-400' : (qrSvg ? 'bg-sky-300' : 'bg-amber-400')"
                            ></span>
                            <h2 class="text-3xl font-bold">
                                {{ isTwoFactorActive ? '2FA Habilitado' : (qrSvg ? '2FA pendiente de confirmación' : '2FA No configurado') }}
                            </h2>
                        </div>
                        <p class="text-sm text-blue-100">
                            La autenticación en dos pasos es obligatoria para tesoreros, cajeros y administradores según la arquitectura del Campus.
                        </p>
                    </div>

                    <div class="flex items-center">
                        <button
                            v-if="!isTwoFactorActive && !qrSvg"
                            type="button"
                            :disabled="loading2fa"
                            @click="enable2FA"
                            class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-semibold bg-white text-blue-900 shadow-sm hover:bg-blue-50 transition-colors disabled:opacity-50"
                        >
                            {{ loading2fa ? 'Preparando...' : 'Activar 2FA' }}
                        </button>
                        <button
                            v-else-if="isTwoFactorActive"
                            type="button"
                            :disabled="disabling2fa"
                            @click="disable2FA"
                            class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-semibold bg-blue-950/60 border border-blue-400/30 text-white hover:bg-blue-950 transition-colors disabled:opacity-50"
                        >
                            {{ disabling2fa ? 'Desactivando...' : 'Desactivar 2FA' }}
                        </button>
                        <span
                            v-else
                            class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-semibold bg-sky-100 text-blue-900"
                        >
                            Escanea y confirma el código
                        </span>
                    </div>
                </div>

                <!-- Configuración de Google Authenticator -->
                <div
                    v-if="!isTwoFactorActive || isTwoFactorSetupPending || requiresPasswordConfirmation || recoveryCodes.length"
                    class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm"
                >
                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                        <div class="max-w-2xl">
                            <span class="text-xs font-semibold uppercase tracking-wider text-blue-600">
                                GOOGLE AUTHENTICATOR • TOTP
                            </span>
                            <h3 class="mt-1 text-lg font-bold text-gray-900">
                                Configuración de autenticación en dos pasos
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">
                                Escanea el QR desde Google Authenticator y confirma el código de 6 dígitos. El QR de esta sección es exclusivo para TOTP y no debe escanearse con la cámara normal del teléfono.
                            </p>
                        </div>

                        <button
                            v-if="!isTwoFactorActive && !qrSvg"
                            type="button"
                            :disabled="loading2fa"
                            @click="enable2FA"
                            class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700 transition-colors disabled:opacity-50"
                        >
                            {{ loading2fa ? 'Preparando...' : 'Generar QR 2FA' }}
                        </button>
                    </div>

                    <div
                        v-if="error2fa"
                        class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                    >
                        {{ error2fa }}
                    </div>

                    <div
                        v-if="success2fa"
                        class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
                    >
                        {{ success2fa }}
                    </div>

                    <!-- Confirmación de contraseña exigida por Fortify -->
                    <div
                        v-if="requiresPasswordConfirmation"
                        class="mt-6 max-w-xl rounded-xl border border-amber-200 bg-amber-50 p-5"
                    >
                        <h4 class="font-semibold text-gray-900">Confirma tu contraseña</h4>
                        <p class="mt-1 text-sm text-gray-600">
                            Fortify requiere confirmar tu contraseña antes de modificar la configuración 2FA.
                        </p>

                        <div class="mt-4">
                            <InputLabel for="two_factor_password" value="Contraseña actual" />
                            <TextInput
                                id="two_factor_password"
                                v-model="password"
                                type="password"
                                autocomplete="current-password"
                                class="mt-1 block w-full"
                                @keyup.enter="confirmPasswordAndContinue"
                            />
                            <p v-if="passwordError" class="mt-2 text-sm text-red-600">
                                {{ passwordError }}
                            </p>
                        </div>

                        <div class="mt-4 flex gap-3">
                            <button
                                type="button"
                                @click="confirmPasswordAndContinue"
                                class="inline-flex items-center justify-center px-4 py-2 rounded-xl text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700"
                            >
                                Confirmar contraseña
                            </button>
                            <button
                                type="button"
                                @click="requiresPasswordConfirmation = false"
                                class="inline-flex items-center justify-center px-4 py-2 rounded-xl text-sm font-semibold border border-slate-300 text-slate-700 hover:bg-slate-50"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>

                    <!-- QR y confirmación del código TOTP -->
                    <div
                        v-if="qrSvg"
                        class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6"
                    >
                        <div class="rounded-xl border border-slate-200 p-5">
                            <h4 class="font-semibold text-gray-900">1. Escanea el código QR</h4>
                            <p class="mt-1 text-sm text-gray-600">
                                En Google Authenticator pulsa <strong>+</strong> y selecciona <strong>Escanear código QR</strong>.
                            </p>

                            <div class="mt-5 inline-block rounded-xl border border-slate-200 bg-white p-4" v-html="qrSvg"></div>

                            <div v-if="secretKey" class="mt-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Clave manual alternativa
                                </p>
                                <code class="mt-1 block break-all rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-700">
                                    {{ secretKey }}
                                </code>
                                <p class="mt-2 text-xs text-slate-500">
                                    No compartas esta clave. Permite generar tus códigos 2FA.
                                </p>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 p-5">
                            <h4 class="font-semibold text-gray-900">2. Confirma el código</h4>
                            <p class="mt-1 text-sm text-gray-600">
                                Introduce el código actual de 6 dígitos mostrado por Google Authenticator.
                            </p>

                            <div class="mt-5">
                                <InputLabel for="two_factor_code" value="Código de autenticación" />
                                <TextInput
                                    id="two_factor_code"
                                    v-model="twoFactorCode"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="one-time-code"
                                    maxlength="6"
                                    placeholder="000000"
                                    class="mt-1 block w-full tracking-[0.35em] text-center text-lg"
                                    @keyup.enter="confirmTwoFactor"
                                />
                            </div>

                            <button
                                type="button"
                                :disabled="confirming2fa"
                                @click="confirmTwoFactor"
                                class="mt-4 w-full inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700 transition-colors disabled:opacity-50"
                            >
                                {{ confirming2fa ? 'Verificando...' : 'Confirmar y activar 2FA' }}
                            </button>
                        </div>
                    </div>

                    <!-- Códigos de recuperación -->
                    <div
                        v-if="recoveryCodes.length"
                        class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-5"
                    >
                        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                            <div>
                                <h4 class="font-semibold text-gray-900">Códigos de recuperación</h4>
                                <p class="mt-1 text-sm text-gray-600">
                                    Guarda estos códigos en un lugar seguro. Cada uno puede usarse para recuperar el acceso si pierdes el autenticador.
                                </p>
                            </div>
                            <button
                                type="button"
                                @click="regenerateRecoveryCodes"
                                class="inline-flex items-center justify-center px-4 py-2 rounded-xl text-xs font-semibold border border-emerald-300 text-emerald-800 hover:bg-emerald-100"
                            >
                                Regenerar códigos
                            </button>
                        </div>

                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <code
                                v-for="code in recoveryCodes"
                                :key="code"
                                class="rounded-lg border border-emerald-200 bg-white px-3 py-2 text-sm text-slate-700"
                            >
                                {{ code }}
                            </code>
                        </div>
                    </div>
                </div>

                <!-- Grid de dos columnas con tarjetas blancas -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                    <!-- Columna Izquierda: Mis Roles Actuales (Módulo 1.3) -->
                    <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wider text-blue-600">
                                    MÓDULO 1.3
                                </span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    RBAC Activo
                                </span>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mt-1">
                                Mis roles y ámbitos asignados
                            </h3>
                            <p class="text-xs text-gray-500">
                                Listado de permisos contextuales otorgados en la plataforma.
                            </p>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-600">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Rol</th>
                                        <th class="px-4 py-3 text-left">Contexto</th>
                                        <th class="px-4 py-3 text-left">Identificador</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <tr v-for="(r, index) in userRoles" :key="index" class="hover:bg-slate-50/70 transition-colors">
                                        <td class="px-4 py-3 font-semibold text-blue-900 uppercase">
                                            {{ r.name }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">
                                            <span v-if="!r.scope_type" class="text-xs font-medium px-2 py-0.5 rounded bg-slate-100 text-slate-700">
                                                Global
                                            </span>
                                            <span v-else class="text-xs font-medium px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200">
                                                {{ r.scope_type }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs font-mono text-gray-500">
                                            {{ r.scope_id || '—' }}
                                        </td>
                                    </tr>
                                    <tr v-if="!userRoles || userRoles.length === 0">
                                        <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-400">
                                            Sin roles asignados todavía. Asigna uno usando el simulador.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Columna Derecha: Simulador de asignación contextual (Módulo 1.3) -->
                    <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-blue-600">
                                GESTIÓN DE ROLES
                            </span>
                            <h3 class="text-lg font-bold text-gray-900 mt-1">
                                Asignar nuevo rol
                            </h3>
                            <p class="text-xs text-gray-500">
                                Otorga permisos contextuales vinculados a un negocio o asociación.
                            </p>
                        </div>

                        <form @submit.prevent="submitRole" class="space-y-4">
                            <div>
                                <InputLabel for="role_name" value="Rol a otorgar" class="text-xs font-semibold uppercase text-slate-600" />
                                <select
                                    id="role_name"
                                    v-model="roleForm.role_name"
                                    class="mt-1 block w-full rounded-xl border-slate-200 text-sm focus:border-blue-500 focus:ring-blue-500 bg-slate-50"
                                    required
                                >
                                    <option value="" disabled>Selecciona un rol</option>
                                    <option v-for="role in availableRoles" :key="role._id || role.id" :value="role.name">
                                        {{ role.display_name }} ({{ role.name }})
                                    </option>
                                </select>
                            </div>

                            <div class="space-y-3">
                                <div>
                                    <InputLabel for="scope_type" value="Ámbito (Contexto)" class="text-xs font-semibold uppercase text-slate-600" />
                                    <select
                                        id="scope_type"
                                        v-model="roleForm.scope_type"
                                        class="mt-1 block w-full rounded-xl border-slate-200 text-sm focus:border-blue-500 focus:ring-blue-500 bg-slate-50"
                                    >
                                        <option value="">Global (Toda la plataforma)</option>
                                        <option value="business">Negocio (Tienda/Comercio)</option>
                                        <option value="association">Asociación Estudiantil</option>
                                        <option value="service">Servicio Universitario</option>
                                    </select>
                                </div>

                                <div>
                                    <InputLabel for="scope_id" value="ID de la entidad (Opcional)" class="text-xs font-semibold uppercase text-slate-600" />
                                    <TextInput
                                        id="scope_id"
                                        type="text"
                                        class="mt-1 block w-full rounded-xl border-slate-200 text-sm bg-slate-50"
                                        v-model="roleForm.scope_id"
                                        placeholder="Ej. NEG-CAFETERIA, ASOC-SISTEMAS"
                                    />
                                </div>
                            </div>

                            <div class="pt-2">
                                <button
                                    type="submit"
                                    :disabled="roleForm.processing"
                                    class="w-full inline-flex justify-center items-center px-4 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700 active:bg-blue-800 transition-colors shadow-sm disabled:opacity-50"
                                >
                                    Asignar rol
                                </button>
                            </div>
                        </form>
                    </div>

                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>