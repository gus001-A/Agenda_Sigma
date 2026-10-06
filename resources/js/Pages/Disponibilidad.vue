<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Fecha from '@/Components/Fecha.vue';
import { ui, usePermisos } from '@/lib/ui';
import { filtrar } from '@/lib/api';
import { fmtD, money, hoyISO } from '@/lib/util';

const props = defineProps({ filtros: Object, resultados: Array });
const page = usePage();
const { puede } = usePermisos();
const op = computed(() => page.props.opciones);
const medicos = computed(() => op.value.medicos.filter((d) => d.active && (!props.filtros.spec || d.specialty_id === props.filtros.spec)));
function ir(c) {
    const f = { ...props.filtros, ...c };
    if (c.spec !== undefined && f.doc && op.value.medicos.find((d) => d.id === f.doc)?.specialty_id !== c.spec) f.doc = null;
    filtrar('disponibilidad', { ...f, pref: f.pref === 'Cualquier horario' ? null : f.pref });
}
const agendar = (x, t) => puede('cita') && ui.abrir('nuevaCita', { doctor_id: x.doctor_id, date: x.date, time: t });
</script>

<template>
    <AppLayout titulo="Próxima disponibilidad" subtitulo="Encuentra el siguiente espacio libre">
        <div class="card pad" style="margin-bottom: 16px">
            <div class="grid4">
                <div class="field"><label>Especialidad</label><Select :model-value="filtros.spec" :options="op.especialidades" option-label="name" option-value="id" placeholder="Cualquier especialidad" show-clear @update:model-value="ir({ spec: $event })" /></div>
                <div class="field"><label>Médico</label><Select :model-value="filtros.doc" :options="medicos" option-label="name" option-value="id" placeholder="Cualquier médico" show-clear filter @update:model-value="ir({ doc: $event })" /></div>
                <div class="field"><label>A partir de</label><Fecha :model-value="filtros.from" :min="hoyISO()" @update:model-value="ir({ from: $event })" /></div>
                <div class="field"><label>Preferencia</label><Select :model-value="filtros.pref" :options="['Cualquier horario', 'Mañana', 'Tarde']" @update:model-value="ir({ pref: $event })" /></div>
            </div>
        </div>
        <template v-if="resultados.length">
            <div class="card tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Fecha</th><th>Médico</th><th>Horarios disponibles</th></tr></thead>
                    <tbody>
                        <tr v-for="x in resultados" :key="x.date + x.doctor_id">
                            <td style="white-space: nowrap"><b style="font-weight: 500">{{ fmtD(x.date) }}</b> <span v-if="x.date === hoyISO()" class="tag">hoy</span></td>
                            <td>{{ x.doctor }}<div class="muted small">{{ x.specialty }}, {{ x.duration }} min, {{ money(x.cost) }}</div></td>
                            <td><div class="times">
                                <button v-for="t in x.times.slice(0, 12)" :key="t" type="button" :title="'Agendar ' + t" @click="agendar(x, t)">{{ t }}</button>
                                <span v-if="x.times.length > 12" class="muted small">+{{ x.times.length - 12 }}</span>
                            </div></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="muted small" style="margin-top: 8px">Haz clic en un horario para agendar. Se muestran las primeras 8 fechas con espacio en los próximos 60 días.</p>
        </template>
        <div v-else class="card empty">
            <b>Sin espacios disponibles en los próximos 60 días</b>Agrega al paciente a la lista de espera para avisarle cuando se libere un horario.
            <div v-if="puede('cita')" style="margin-top: 12px"><Button label="Agregar a lista de espera" @click="ui.abrir('nuevaEspera', { specialty_id: filtros.spec, doctor_id: filtros.doc })" /></div>
        </div>
    </AppLayout>
</template>
