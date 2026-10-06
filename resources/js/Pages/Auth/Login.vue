<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps({ canResetPassword: Boolean, status: String });
const page = usePage();
const form = useForm({ email: '', password: '', remember: false });
const enviar = () => form.post(route('login'), { onFinish: () => form.reset('password') });
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
    </GuestLayout>
</template>
