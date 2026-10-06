<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({ titulo: String });
const anio = new Date().getFullYear();
const destacados = [
    { i: 'pi pi-calendar', t: 'Agenda inteligente', d: 'Disponibilidad generada desde los horarios de cada médico.' },
    { i: 'pi pi-file-edit', t: 'Preconsulta digital', d: 'El paciente llena su historia clínica antes de llegar.' },
    { i: 'pi pi-shield', t: 'Trazabilidad completa', d: 'Permisos por rol y auditoría de cada movimiento.' },
];
</script>

<template>
    <Head :title="titulo" />
    <div class="au">
        <aside class="au-brand">
            <span class="au-dots" aria-hidden="true" />
            <span class="au-blob au-blob--1" aria-hidden="true" />
            <span class="au-blob au-blob--2" aria-hidden="true" />
            <div class="au-brand__inner">
                <div class="au-logo"><div class="mark" style="width: 54px; height: 54px; font-size: 18px; border-radius: 15px">S24</div><div><b>SIGMA24</b><small>Clínica San Antonio</small></div></div>
                <div class="au-rule" />
                <p class="au-sub">Sistema Integral de <span>Agenda, Preconsulta e Historias Clínicas</span></p>
                <ul class="au-feats">
                    <li v-for="(f, i) in destacados" :key="f.t" :style="{ '--d': i * 110 + 'ms' }">
                        <span class="au-feats__ic"><i :class="f.i" /></span>
                        <div><b>{{ f.t }}</b><small>{{ f.d }}</small></div>
                    </li>
                </ul>
                <p class="au-foot">&copy; SIGMA24 {{ anio }}</p>
            </div>
        </aside>
        <main class="au-side">
            <div class="au-card">
                <div class="au-card__bar" />
                <slot />
            </div>
        </main>
    </div>
</template>

<style scoped>
.au { display: grid; grid-template-columns: 1.15fr 1fr; min-height: 100vh; background: #fff; }
.au-brand { position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; padding: 56px 64px;
    background: linear-gradient(155deg, var(--navy-700) 0%, var(--navy) 48%, var(--teal-700) 100%); color: #fff; }
.au-brand::after { content: ''; position: absolute; top: -14%; right: -190px; width: 320px; height: 128%; background: #fff; border-radius: 50%; z-index: 1; }
.au-dots { position: absolute; top: 44px; left: 64px; width: 140px; height: 110px; background-image: radial-gradient(rgba(255, 255, 255, 0.22) 1.6px, transparent 1.8px); background-size: 18px 18px; }
.au-blob { position: absolute; border-radius: 50%; filter: blur(50px); }
.au-blob--1 { top: -120px; right: -60px; width: 360px; height: 360px; background: rgba(31, 158, 134, 0.45); }
.au-blob--2 { bottom: -140px; left: -80px; width: 380px; height: 380px; background: rgba(43, 84, 128, 0.55); }
.au-brand__inner { position: relative; z-index: 2; max-width: 460px; width: 100%; }
.au-logo { display: inline-flex; align-items: center; gap: 14px; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.18); padding: 14px 20px 14px 14px; border-radius: 18px; backdrop-filter: blur(6px); }
.au-logo b { display: block; font-size: 22px; font-weight: 800; letter-spacing: 0.02em; }
.au-logo small { color: rgba(255, 255, 255, 0.75); font-size: 13px; }
.au-rule { width: 64px; height: 4px; border-radius: 4px; margin: 26px 0 18px; background: linear-gradient(90deg, #fff, rgba(255, 255, 255, 0.2)); }
.au-sub { font-size: 20px; line-height: 1.35; color: rgba(255, 255, 255, 0.85); margin: 0; }
.au-sub span { display: block; color: #fff; font-weight: 800; font-size: 24px; }
.au-feats { list-style: none; padding: 0; margin: 30px 0 0; display: grid; gap: 14px; }
.au-feats li { display: flex; gap: 14px; align-items: flex-start; animation: s-fade-up 0.5s cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--d); }
.au-feats__ic { width: 40px; height: 40px; flex: none; border-radius: 12px; display: grid; place-items: center; background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.2); }
.au-feats b { display: block; font-size: 14.5px; }
.au-feats small { color: rgba(255, 255, 255, 0.72); font-size: 13px; }
.au-foot { margin-top: 36px; font-size: 12px; color: rgba(255, 255, 255, 0.55); }
.au-side { display: flex; align-items: center; justify-content: center; padding: 40px 24px; }
.au-card { position: relative; width: 100%; max-width: 410px; display: grid; gap: 16px; padding: 30px 30px 28px; background: #fff; border: 1px solid var(--line); border-radius: 18px; box-shadow: var(--shadow-md); animation: s-fade-up 0.45s cubic-bezier(0.16, 1, 0.3, 1) both; }
.au-card__bar { position: absolute; top: 0; left: 30px; right: 30px; height: 4px; border-radius: 0 0 4px 4px; background: var(--grad); }
@media (max-width: 900px) {
    .au { grid-template-columns: 1fr; }
    .au-brand { padding: 28px 22px 70px; }
    .au-brand::after { top: auto; right: -10%; bottom: -90px; width: 120%; height: 130px; }
    .au-feats, .au-foot, .au-dots { display: none; }
    .au-sub { font-size: 15px; } .au-sub span { font-size: 18px; }
    .au-side { margin-top: -50px; position: relative; z-index: 3; padding: 0 16px 32px; align-items: flex-start; }
}
</style>
