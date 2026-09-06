<script setup lang="ts">
import { Users } from '@lucide/vue';
</script>

<template>
    <div class="empty-state">
        <span class="empty-state-icon"><Users :size="28" /></span>
        <h3>No encontramos estudiantes</h3>
        <p>Ajusta los filtros o registra una nueva cuenta.</p>
    </div>
</template>

