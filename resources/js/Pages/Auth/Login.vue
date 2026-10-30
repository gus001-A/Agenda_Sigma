<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps({ canResetPassword: Boolean, status: String, accesosDemo: { type: Array, default: () => [] } });
const page = usePage();
const form = useForm({ email: '', password: '', remember: false });

const enviar = () => form.post(route('login'), { onFinish: () => form.reset('password') });

// Contraseñas por rol como respaldo (si el backend no envía `password` en accesosDemo)
const passwordsPorRol = {
    admin: 'Admin123!',
    administrador: 'Admin123!',
    doctor: 'Doctor123!',
    médico: 'Doctor123!',
    medico: 'Doctor123!',
    paciente: 'Paciente123!',
    recepcion: 'Recepcion123!',
    recepcionista: 'Recepcion123!',
};

const entrarComo = (a) => {
    // Si el backend ya manda password, se usa; si no, se busca por rol
    const rolKey = String(a.rol || '').toLowerCase().trim();
    form.email = a.email;
    form.password = a.password || passwordsPorRol[rolKey] || '';
    form.remember = false;
    enviar();
};
</script>

<template>
    <GuestLayout titulo="Iniciar sesión">
        <div class="login">
            <header class="login__head">
                <div class="login__logo">
                    <div class="mark login__mark">S24</div>
                    <div class="login__brand">
                        <b>SIGMA24</b>
                        <small>Clínica San Antonio</small>
                    </div>
                </div>
                <h2 class="login__title">Iniciar sesión</h2>
                <p class="login__sub">Ingresa con tu cuenta institucional.</p>
            </header>

            <Message v-if="status" severity="success" size="small">{{ status }}</Message>
            <Message v-if="page.props.flash?.error" severity="error" size="small">{{ page.props.flash.error }}</Message>

            <form class="login__form stack" @submit.prevent="enviar">
                <div class="field">
                    <label for="email">Correo electrónico</label>
                    <InputText id="email" v-model="form.email" type="email" autocomplete="username" autofocus
                        :invalid="!!form.errors.email" class="w-full" />
                    <small v-if="form.errors.email" style="color: var(--danger)">{{ form.errors.email }}</small>
                </div>
                <div class="field">
                    <label for="password">Contraseña</label>
                    <Password v-model="form.password" input-id="password" :feedback="false" toggle-mask fluid
                        autocomplete="current-password" :invalid="!!form.errors.password" class="w-full" />
                    <small v-if="form.errors.password" style="color: var(--danger)">{{ form.errors.password }}</small>
                </div>
                <div class="row" style="justify-content: space-between">
                    <label class="chk">
                        <Checkbox v-model="form.remember" binary />Recordarme
                    </label>
                    <Link v-if="canResetPassword" :href="route('password.request')" class="small">¿Olvidaste tu
                        contraseña?</Link>
                </div>
                <Button type="submit" label="Entrar" icon="pi pi-sign-in" :loading="form.processing" fluid />
            </form>

            <section v-if="accesosDemo.length" class="demo">
                <p class="demo__t"><i class="pi pi-bolt" />Accesos de demostración (solo desarrollo)</p>
                <div class="demo__grid">
                    <button v-for="a in accesosDemo" :key="a.email" type="button" class="demo__btn"
                        :disabled="form.processing" :title="`Entrar como ${a.email}`" @click="entrarComo(a)">
                        <span class="demo__ic" :style="{ background: a.color }"><i :class="a.icono" /></span>
                        <span class="demo__txt"><b>{{ a.rol }}</b><small>{{ a.email.split('@')[0] }}</small></span>
                    </button>
                </div>
            </section>
        </div>
    </GuestLayout>
</template>

<style scoped>
.login {
    width: 100%;
    max-width: 720px;
    margin: 0 auto;
    display: grid;
    gap: 18px;
}

.login__head {
    text-align: center;
    padding: 8px 0 18px;
    border-bottom: 1px solid var(--line);
    margin-bottom: 6px;
    display: grid;
    justify-items: center;
    gap: 14px;
}

.login__logo {
    display: inline-flex;
    align-items: center;
    gap: 16px;
    padding: 14px 22px;
    border-radius: 18px;
    background: linear-gradient(155deg, var(--navy-700, #1e3a5f) 0%, var(--navy, #24476b) 60%, var(--teal-700, #1a6f61) 100%);
    box-shadow: var(--shadow-md, 0 8px 24px rgba(0, 0, 0, .12));
}

.login__mark {
    width: 64px;
    height: 64px;
    font-size: 22px;
    border-radius: 16px;
    display: grid;
    place-items: center;
    background: rgba(255, 255, 255, 0.14);
    color: #fff;
    font-weight: 800;
    letter-spacing: 0.02em;
    border: 1px solid rgba(255, 255, 255, 0.22);
}

.login__brand {
    display: grid;
    line-height: 1.15;
    text-align: left;
}

.login__brand b {
    color: #fff;
    font-size: 24px;
    font-weight: 800;
    letter-spacing: 0.02em;
}

.login__brand small {
    color: rgba(255, 255, 255, 0.78);
    font-size: 13px;
}

.login__title {
    margin: 0;
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--ink);
}

.login__sub {
    margin: 0;
    font-size: 14px;
    color: var(--muted);
}

.login__form {
    width: 100%;
    display: grid;
    gap: 16px;
    padding: 20px;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: #fff;
    box-shadow: var(--shadow-sm);
}

.login__form .field {
    display: grid;
    gap: 6px;
}

.login__form :deep(.p-inputtext),
.login__form :deep(.p-password),
.login__form :deep(.p-password-input),
.login__form :deep(.p-button) {
    width: 100%;
}

.demo {
    border: 1px dashed var(--line);
    border-radius: 14px;
    padding: 12px;
    background: var(--surface-2);
}

.demo__t {
    margin: 0 0 10px;
    text-align: center;
    font-size: 12px;
    color: var(--muted);
}

.demo__t .pi {
    color: var(--accent);
    margin-right: 6px;
    font-size: 11px;
}

.demo__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.demo__btn {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    padding: 8px 10px;
    border: 1px solid var(--line);
    border-radius: 999px;
    background: #fff;
    cursor: pointer;
    text-align: left;
    font: inherit;
    color: var(--ink);
    transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
}

.demo__btn:hover:not(:disabled) {
    border-color: var(--navy-100);
    box-shadow: var(--shadow-sm);
    transform: translateY(-1px);
}

.demo__btn:disabled {
    opacity: 0.6;
    cursor: wait;
}

.demo__ic {
    width: 32px;
    height: 32px;
    flex: none;
    border-radius: 50%;
    display: grid;
    place-items: center;
    color: #fff;
    font-size: 13px;
}

.demo__txt {
    min-width: 0;
    display: grid;
    line-height: 1.2;
}

.demo__txt b {
    font-size: 13px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.demo__txt small {
    font-size: 11px;
    color: var(--muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

@media (max-width: 420px) {
    .demo__grid {
        grid-template-columns: 1fr;
    }
}
</style>