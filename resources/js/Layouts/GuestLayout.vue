<script setup>
import { Head } from '@inertiajs/vue3';
defineProps({ titulo: String });
</script>

<template>
    <Head :title="titulo" />
    <div style="min-height: 100%; display: grid; place-items: center; padding: 24px 16px; background: linear-gradient(160deg, var(--side) 0%, #13525E 55%, var(--bg) 55.1%)">
        <div style="width: 100%; max-width: 420px">
            <div class="brand" style="color: #fff; padding: 0 4px 18px">
                <div class="mark">S24</div>
                <div><b>SIGMA24</b><small style="color: #B8D2D8">Agenda, preconsulta e historias clínicas</small></div>
            </div>
            <div class="card pad stack" style="box-shadow: var(--shadow); padding: 26px">
                <slot />
            </div>
            <p class="muted small" style="text-align: center; margin-top: 14px">Clínica San Antonio</p>
        </div>
    </div>
</template>
