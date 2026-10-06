<script setup>
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ui } from '@/lib/ui';
import { api, avisar, refrescar } from '@/lib/api';
import { money, fmtStamp } from '@/lib/util';

const page = usePage();
const visible = computed({ get: () => ui.dialogo === 'pago', set: (v) => { if (!v) ui.cerrar(); } });
const a = computed(() => ui.datos.cita || {});
const saldo = computed(() => Math.max(0, (a.value.deposit || 0) - (a.value.paid || 0)));
const f = ref({});
const guardando = ref(false);
watch(visible, (v) => { if (v) f.value = { amount: saldo.value, method: 'Transferencia', reference: '' }; });

async function guardar() {
    guardando.value = true;
    try {
        const r = await api.post(route('api.citas.pago', a.value.id), f.value);
        avisar(r.message);
        ui.cerrar();
        refrescar();
        ui.recargarCita();
    } finally { guardando.value = false; }
}
</script>

<template>
    <Dialog v-model:visible="visible" modal header="Registrar anticipo" :style="{ width: '560px' }" :breakpoints="{ '600px': '96vw' }">
        <div class="summary">
            <div><span>Paciente</span><b>{{ a.patient }}</b></div>
            <div><span>Anticipo requerido</span><b>{{ money(a.deposit) }}</b></div>
            <div><span>Pagado</span><b>{{ money(a.paid) }}</b></div>
            <div><span>Saldo</span><b style="color: var(--accent)">{{ money(saldo) }}</b></div>
        </div>
        <div class="grid2">
            <div class="field"><label>Monto recibido</label><InputNumber v-model="f.amount" mode="currency" currency="MXN" locale="es-MX" :min="0" :max="saldo" fluid /></div>
            <div class="field"><label>Forma de pago</label><Select v-model="f.method" :options="page.props.catalogo.formasPago" /></div>
        </div>
        <div class="field"><label for="pm-ref">Referencia o folio</label><InputText id="pm-ref" v-model="f.reference" placeholder="Opcional" /></div>
        <div v-if="a.payments?.length">
            <div class="sec-t">Pagos anteriores</div>
            <div v-for="(x, i) in a.payments" :key="i" class="row small" style="justify-content: space-between">
                <span>{{ fmtStamp(x.at) }}, {{ x.method }}{{ x.reference ? ' (' + x.reference + ')' : '' }}</span><b>{{ money(x.amount) }}</b>
            </div>
        </div>
        <template #footer>
            <Button label="Cancelar" text @click="ui.cerrar()" />
            <Button label="Registrar pago" icon="pi pi-check" :loading="guardando" @click="guardar" />
        </template>
    </Dialog>
</template>
