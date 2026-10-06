<script setup>
import Modal from '@/Components/Modal.vue';
import SeccionModal from '@/Components/SeccionModal.vue';
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
const ICONO_PAGO = { Efectivo: 'pi pi-money-bill', 'Tarjeta de débito': 'pi pi-credit-card', 'Tarjeta de crédito': 'pi pi-credit-card', Transferencia: 'pi pi-arrow-right-arrow-left' };
const COLOR_PAGO = { Efectivo: '#16a34a', 'Tarjeta de débito': '#2b5480', 'Tarjeta de crédito': '#6b4bc9', Transferencia: '#0e7490' };

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
    <Modal v-model:visible="visible" titulo="Registrar anticipo" :subtitulo="a.patient ? `${a.patient} · el pago queda en el historial y se notifica al paciente` : ''" icono="pi pi-wallet" color="#e08a1e" ancho="640px">
        <div class="kpis linea" style="margin: 0">
            <div class="kpi" style="--acc: #173a5f"><span class="kpi-ic"><i class="pi pi-tag" /></span><div><span>Requerido</span><b>{{ money(a.deposit) }}</b></div></div>
            <div class="kpi" style="--acc: #16a34a"><span class="kpi-ic"><i class="pi pi-check-circle" /></span><div><span>Pagado</span><b>{{ money(a.paid) }}</b></div></div>
            <div class="kpi" style="--acc: #e08a1e"><span class="kpi-ic"><i class="pi pi-hourglass" /></span><div><span>Saldo</span><b>{{ money(saldo) }}</b></div></div>
        </div>
        <SeccionModal titulo="Datos del pago" icono="pi pi-credit-card" color="#e08a1e">
            <div class="grid2">
                <div class="field"><label>Monto recibido</label><InputNumber v-model="f.amount" mode="currency" currency="MXN" locale="es-MX" :min="0" :max="saldo" fluid /></div>
                <div class="field"><label for="pm-ref">Referencia o folio</label><div class="campo-ic"><i class="pi pi-hashtag" /><InputText id="pm-ref" v-model="f.reference" placeholder="Opcional" fluid /></div></div>
            </div>
            <div class="field">
                <label>Forma de pago</label>
                <div class="opciones-ic">
                    <button v-for="p in page.props.catalogo.formasPago" :key="p" type="button" class="op-ic" :class="{ on: f.method === p }" :style="{ '--oc': COLOR_PAGO[p] }" @click="f.method = p"><i :class="ICONO_PAGO[p]" />{{ p }}</button>
                </div>
            </div>
        </SeccionModal>
        <SeccionModal v-if="a.payments?.length" titulo="Pagos anteriores" icono="pi pi-history" color="#64748b">
            <div v-for="(x, i) in a.payments" :key="i" class="row small" style="justify-content: space-between; padding: 4px 0; border-bottom: 1px dashed #eef2f6">
                <span><i class="pi pi-calendar" style="color: #94a3b8" /> {{ fmtStamp(x.at) }} · {{ x.method }}{{ x.reference ? ' (' + x.reference + ')' : '' }}</span><b style="color: #16806c">{{ money(x.amount) }}</b>
            </div>
        </SeccionModal>
        <template #footer>
            <Button label="Cancelar" text @click="ui.cerrar()" />
            <Button label="Registrar pago" icon="pi pi-check" severity="warn" :loading="guardando" @click="guardar" />
        </template>
    </Modal>
</template>
