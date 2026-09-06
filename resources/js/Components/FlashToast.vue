<script setup lang="ts">
import { CheckCircle2, X } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{ message?: string | null }>();
const visible = ref(Boolean(props.message));
let timer: ReturnType<typeof setTimeout> | undefined;

watch(() => props.message, (message) => {
    visible.value = Boolean(message);
    if (timer) clearTimeout(timer);
    if (message) timer = setTimeout(() => visible.value = false, 4500);
}, { immediate: true });

onBeforeUnmount(() => timer && clearTimeout(timer));
</script>

<template>
    <Transition name="toast">
        <div v-if="visible && message" class="flash-toast" role="status" aria-live="polite">
            <CheckCircle2 :size="20" aria-hidden="true" />
            <span>{{ message }}</span>
            <button type="button" aria-label="Cerrar notificación" @click="visible = false">
                <X :size="17" />
            </button>
        </div>
    </Transition>
</template>

