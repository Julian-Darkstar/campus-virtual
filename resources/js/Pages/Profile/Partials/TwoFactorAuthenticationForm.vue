<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({
    initiallyEnabled: {
        type: Boolean,
        default: false,
    },
});

const enabled = ref(props.initiallyEnabled);
const configuring = ref(false);
const busy = ref(false);
const qrSvg = ref('');
const secretKey = ref('');
const recoveryCodes = ref([]);
const code = ref('');
const password = ref('');
const passwordAction = ref(null);
const showPasswordDialog = ref(false);
const error = ref('');
const success = ref('');
const fieldErrors = ref({});

const statusText = computed(() => {
    if (enabled.value) return 'Activada';
    if (configuring.value) return 'Pendiente de confirmación';
    return 'Desactivada';
});

const clearMessages = () => {
    error.value = '';
    success.value = '';
    fieldErrors.value = {};
};

const normalizeError = (e, fallback) => {
    if (e?.response?.status === 422) {
        fieldErrors.value = e.response.data?.errors ?? {};
        return e.response.data?.message ?? fallback;
    }

    if (e?.response?.status === 429) {
        return 'Demasiados intentos. Espera un momento y vuelve a intentarlo.';
    }

    return e?.response?.data?.message ?? fallback;
};

const askForPassword = (action) => {
    clearMessages();
    password.value = '';
    passwordAction.value = action;
    showPasswordDialog.value = true;
};

const confirmPasswordAndContinue = async () => {
    busy.value = true;
    clearMessages();

    try {
        // The project already exposes this Breeze-compatible endpoint in routes/auth.php.
        // It sets auth.password_confirmed_at in the current session, which satisfies
        // Fortify's password.confirm middleware for 2FA management endpoints.
        await axios.post('/confirm-password', { password: password.value });

        showPasswordDialog.value = false;
        const action = passwordAction.value;
        passwordAction.value = null;
        password.value = '';

        if (action === 'enable') await enableTwoFactor();
        if (action === 'disable') await disableTwoFactor();
        if (action === 'regenerate') await regenerateRecoveryCodes();
    } catch (e) {
        error.value = normalizeError(e, 'No fue posible confirmar la contraseña.');
    } finally {
        busy.value = false;
    }
};

const loadQr = async () => {
    const [{ data: qr }, { data: secret }] = await Promise.all([
        axios.get('/user/two-factor-qr-code'),
        axios.get('/user/two-factor-secret-key'),
    ]);

    qrSvg.value = qr.svg;
    secretKey.value = secret.secretKey ?? secret.secret_key ?? '';
};

const loadRecoveryCodes = async () => {
    const { data } = await axios.get('/user/two-factor-recovery-codes');
    recoveryCodes.value = Array.isArray(data) ? data : [];
};

const enableTwoFactor = async () => {
    busy.value = true;
    clearMessages();

    try {
        await axios.post('/user/two-factor-authentication');
        configuring.value = true;
        enabled.value = false;
        await loadQr();
        success.value = 'Escanea el QR con Google Authenticator y confirma con el código de 6 dígitos.';
    } catch (e) {
        error.value = normalizeError(e, 'No fue posible iniciar la configuración de 2FA.');
    } finally {
        busy.value = false;
    }
};

const confirmTwoFactor = async () => {
    busy.value = true;
    clearMessages();

    try {
        await axios.post('/user/confirmed-two-factor-authentication', {
            code: code.value.replace(/\s/g, ''),
        });

        code.value = '';
        configuring.value = false;
        enabled.value = true;
        qrSvg.value = '';
        secretKey.value = '';
        await loadRecoveryCodes();
        success.value = 'Autenticación de dos factores activada correctamente.';
    } catch (e) {
        error.value = normalizeError(e, 'El código no es válido o ya expiró.');
    } finally {
        busy.value = false;
    }
};

const cancelSetup = async () => {
    busy.value = true;
    clearMessages();

    try {
        await axios.delete('/user/two-factor-authentication');
        configuring.value = false;
        enabled.value = false;
        qrSvg.value = '';
        secretKey.value = '';
        recoveryCodes.value = [];
        success.value = 'Configuración cancelada.';
    } catch (e) {
        error.value = normalizeError(e, 'No fue posible cancelar la configuración.');
    } finally {
        busy.value = false;
    }
};

const disableTwoFactor = async () => {
    busy.value = true;
    clearMessages();

    try {
        await axios.delete('/user/two-factor-authentication');
        enabled.value = false;
        configuring.value = false;
        qrSvg.value = '';
        secretKey.value = '';
        recoveryCodes.value = [];
        success.value = 'Autenticación de dos factores desactivada.';
    } catch (e) {
        error.value = normalizeError(e, 'No fue posible desactivar 2FA.');
    } finally {
        busy.value = false;
    }
};

const showRecoveryCodes = async () => {
    busy.value = true;
    clearMessages();

    try {
        await loadRecoveryCodes();
    } catch (e) {
        error.value = normalizeError(e, 'No fue posible cargar los códigos de recuperación.');
    } finally {
        busy.value = false;
    }
};

const regenerateRecoveryCodes = async () => {
    busy.value = true;
    clearMessages();

    try {
        await axios.post('/user/two-factor-recovery-codes');
        await loadRecoveryCodes();
        success.value = 'Se generaron nuevos códigos. Los anteriores dejaron de ser válidos.';
    } catch (e) {
        error.value = normalizeError(e, 'No fue posible regenerar los códigos de recuperación.');
    } finally {
        busy.value = false;
    }
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Autenticación de dos factores (Google Authenticator)
            </h2>
            <p class="mt-1 text-sm text-gray-600">
                Protege el inicio de sesión con un código TOTP de 6 dígitos. También funciona con
                Microsoft Authenticator, Authy, 1Password y otras aplicaciones TOTP compatibles.
            </p>
        </header>

        <div class="mt-5 rounded-lg border border-gray-200 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-gray-900">Estado</p>
                    <p
                        class="mt-1 text-sm font-semibold"
                        :class="enabled ? 'text-emerald-600' : configuring ? 'text-amber-600' : 'text-gray-500'"
                    >
                        {{ statusText }}
                    </p>
                </div>

                <PrimaryButton
                    v-if="!enabled && !configuring"
                    type="button"
                    :disabled="busy"
                    @click="askForPassword('enable')"
                >
                    Activar 2FA
                </PrimaryButton>

                <SecondaryButton
                    v-if="enabled"
                    type="button"
                    :disabled="busy"
                    @click="askForPassword('disable')"
                >
                    Desactivar 2FA
                </SecondaryButton>
            </div>
        </div>

        <p v-if="success" class="mt-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-700">
            {{ success }}
        </p>
        <p v-if="error" class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700">
            {{ error }}
        </p>

        <div v-if="configuring" class="mt-6 space-y-5">
            <div>
                <h3 class="font-medium text-gray-900">1. Abre Google Authenticator</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Pulsa <strong>+</strong> → <strong>Escanear código QR</strong>. No uses la cámara normal del iPhone;
                    este QR contiene un URI <code>otpauth://</code> diseñado para apps autenticadoras.
                </p>
            </div>

            <div v-if="qrSvg" class="inline-block rounded-xl border bg-white p-4" v-html="qrSvg"></div>

            <div v-if="secretKey" class="rounded-lg bg-gray-50 p-4">
                <p class="text-sm font-medium text-gray-900">Clave manual (solo si no puedes escanear)</p>
                <code class="mt-2 block break-all text-sm text-gray-700">{{ secretKey }}</code>
                <p class="mt-2 text-xs text-gray-500">
                    No compartas esta clave ni la guardes en capturas, logs o repositorios.
                </p>
            </div>

            <div>
                <h3 class="font-medium text-gray-900">2. Confirma el código</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Introduce el código actual de 6 dígitos que muestra la aplicación.
                </p>

                <div class="mt-3 max-w-xs">
                    <InputLabel for="two_factor_code" value="Código de autenticación" />
                    <TextInput
                        id="two_factor_code"
                        v-model="code"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        class="mt-1 block w-full tracking-[0.35em]"
                        placeholder="000000"
                        @keyup.enter="confirmTwoFactor"
                    />
                    <InputError class="mt-2" :message="fieldErrors.code?.[0]" />
                </div>

                <div class="mt-4 flex flex-wrap gap-3">
                    <PrimaryButton type="button" :disabled="busy || code.length < 6" @click="confirmTwoFactor">
                        Confirmar y activar
                    </PrimaryButton>
                    <SecondaryButton type="button" :disabled="busy" @click="cancelSetup">
                        Cancelar
                    </SecondaryButton>
                </div>
            </div>
        </div>

        <div v-if="enabled" class="mt-6 space-y-4">
            <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-800">
                En el próximo inicio de sesión, después del correo y contraseña, se solicitará el código TOTP.
            </div>

            <div class="flex flex-wrap gap-3">
                <SecondaryButton type="button" :disabled="busy" @click="showRecoveryCodes">
                    Mostrar códigos de recuperación
                </SecondaryButton>
                <SecondaryButton type="button" :disabled="busy" @click="askForPassword('regenerate')">
                    Regenerar códigos
                </SecondaryButton>
            </div>

            <div v-if="recoveryCodes.length" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <p class="font-medium text-amber-900">Códigos de recuperación</p>
                <p class="mt-1 text-sm text-amber-800">
                    Guarda estos códigos fuera del proyecto. Cada código es de un solo uso.
                </p>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    <code
                        v-for="item in recoveryCodes"
                        :key="item"
                        class="rounded bg-white px-3 py-2 text-sm text-gray-800"
                    >{{ item }}</code>
                </div>
            </div>
        </div>

        <div
            v-if="showPasswordDialog"
            class="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-4"
        >
            <p class="font-medium text-gray-900">Confirma tu contraseña</p>
            <p class="mt-1 text-sm text-gray-600">
                Esta operación modifica la seguridad de la cuenta y requiere reautenticación.
            </p>

            <div class="mt-3 max-w-sm">
                <InputLabel for="two_factor_password" value="Contraseña actual" />
                <TextInput
                    id="two_factor_password"
                    v-model="password"
                    type="password"
                    autocomplete="current-password"
                    class="mt-1 block w-full"
                    @keyup.enter="confirmPasswordAndContinue"
                />
                <InputError class="mt-2" :message="fieldErrors.password?.[0]" />
            </div>

            <div class="mt-4 flex gap-3">
                <PrimaryButton type="button" :disabled="busy || !password" @click="confirmPasswordAndContinue">
                    Continuar
                </PrimaryButton>
                <SecondaryButton type="button" :disabled="busy" @click="showPasswordDialog = false">
                    Cancelar
                </SecondaryButton>
            </div>
        </div>
    </section>
</template>
