<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const recovery = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

const submit = () => {
    form.post('/two-factor-challenge');
};

const toggleRecovery = () => {
    recovery.value = !recovery.value;
    form.clearErrors();
    form.code = '';
    form.recovery_code = '';
};
</script>

<template>
    <GuestLayout>
        <Head title="Verificación en dos pasos" />

        <div class="mb-4 text-sm text-slate-600">
            <template v-if="!recovery">
                Confirma el acceso a tu cuenta ingresando el código de autenticación de tu dispositivo.
            </template>
            <template v-else>
                Confirma el acceso ingresando uno de tus códigos de recuperación de emergencia.
            </template>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div v-if="!recovery">
                <InputLabel for="code" value="Código de autenticación" class="text-xs font-semibold uppercase text-slate-600" />
                <TextInput
                    id="code"
                    type="text"
                    inputmode="numeric"
                    class="mt-1 block w-full rounded-xl border-slate-200 text-sm bg-slate-50"
                    v-model="form.code"
                    autofocus
                    autocomplete="one-time-code"
                />
                <InputError class="mt-2" :message="form.errors.code" />
            </div>

            <div v-else>
                <InputLabel for="recovery_code" value="Código de recuperación" class="text-xs font-semibold uppercase text-slate-600" />
                <TextInput
                    id="recovery_code"
                    type="text"
                    class="mt-1 block w-full rounded-xl border-slate-200 text-sm bg-slate-50"
                    v-model="form.recovery_code"
                    autocomplete="one-time-code"
                />
                <InputError class="mt-2" :message="form.errors.recovery_code" />
            </div>

            <div class="flex items-center justify-between pt-2">
                <button
                    type="button"
                    class="text-xs text-blue-600 underline hover:text-blue-800"
                    @click="toggleRecovery"
                >
                    {{ !recovery ? 'Usar código de recuperación' : 'Usar código de autenticación' }}
                </button>

                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Confirmar acceso
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>