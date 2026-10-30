<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps({ canResetPassword: Boolean, status: String, accesosDemo: { type: Array, default: () => [] } });
const page = usePage();
const form = useForm({ email: '', password: '', remember: false });
const enviar = () => form.post(route('login'), { onFinish: () => form.reset('password') });
const entrarComo = (a) => {
    form.email = a.email;
    form.password = a.password;
    enviar();
};
</script>

<template>
    <GuestLayout titulo="Iniciar sesión">
        <div>
            <h2>Iniciar sesión</h2>
            <p class="muted small" style="margin-top: 4px">Ingresa con tu cuenta institucional.</p>
        </div>
        <Message v-if="status" severity="success" size="small">{{ status }}</Message>
        <Message v-if="page.props.flash?.error" severity="error" size="small">{{ page.props.flash.error }}</Message>
        <form class="stack" @submit.prevent="enviar">
            <div class="field">
                <label for="email">Correo electrónico</label>
                <InputText id="email" v-model="form.email" type="email" autocomplete="username" autofocus :invalid="!!form.errors.email" />
                <small v-if="form.errors.email" style="color: var(--danger)">{{ form.errors.email }}</small>
            </div>
            <div class="field">
                <label for="password">Contraseña</label>
                <Password v-model="form.password" input-id="password" :feedback="false" toggle-mask fluid autocomplete="current-password" :invalid="!!form.errors.password" />
                <small v-if="form.errors.password" style="color: var(--danger)">{{ form.errors.password }}</small>
            </div>
            <div class="row" style="justify-content: space-between">
                <label class="chk"><Checkbox v-model="form.remember" binary />Recordarme</label>
                <Link v-if="canResetPassword" :href="route('password.request')" class="small">¿Olvidaste tu contraseña?</Link>
            </div>
            <Button type="submit" label="Entrar" icon="pi pi-sign-in" :loading="form.processing" fluid />
        </form>
        <section v-if="accesosDemo.length" class="demo">
            <p class="demo__t"><i class="pi pi-bolt" />Accesos de demostración</p>
            <div class="demo__grid">
                <button v-for="a in accesosDemo" :key="a.email" type="button" class="demo__btn" :disabled="form.processing" :title="`Entrar como ${a.email}`" @click="entrarComo(a)">
                    <span class="demo__ic" :style="{ background: a.color }"><i :class="a.icono" /></span>
                    <span class="demo__txt"><b>{{ a.rol }}</b><small>{{ a.email.split('@')[0] }}</small></span>
                </button>
            </div>
        </section>
    </GuestLayout>
</template>

<style scoped>
.demo { border: 1px dashed var(--line); border-radius: 14px; padding: 12px; background: var(--surface-2); }
.demo__t { margin: 0 0 10px; text-align: center; font-size: 12px; color: var(--muted); }
.demo__t .pi { color: var(--accent); margin-right: 6px; font-size: 11px; }
.demo__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.demo__btn { display: flex; align-items: center; gap: 10px; min-width: 0; padding: 8px 10px; border: 1px solid var(--line); border-radius: 999px; background: #fff; cursor: pointer; text-align: left; font: inherit; color: var(--ink); transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s; }
.demo__btn:hover:not(:disabled) { border-color: var(--navy-100); box-shadow: var(--shadow-sm); transform: translateY(-1px); }
.demo__btn:disabled { opacity: 0.6; cursor: wait; }
.demo__ic { width: 32px; height: 32px; flex: none; border-radius: 50%; display: grid; place-items: center; color: #fff; font-size: 13px; }
.demo__txt { min-width: 0; display: grid; line-height: 1.2; }
.demo__txt b { font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.demo__txt small { font-size: 11px; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
@media (max-width: 420px) { .demo__grid { grid-template-columns: 1fr; } }
</style>
