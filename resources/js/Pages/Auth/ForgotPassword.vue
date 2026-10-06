<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps({ status: String });
const form = useForm({ email: '' });
const enviar = () => form.post(route('password.email'));
</script>

<template>
    <GuestLayout titulo="Recuperar contraseña">
        <div>
            <h2>Recuperar contraseña</h2>
            <p class="muted small" style="margin-top: 4px">Te enviaremos un enlace para definir una nueva contraseña.</p>
        </div>
        <Message v-if="status" severity="success" size="small">{{ status }}</Message>
        <form class="stack" @submit.prevent="enviar">
            <div class="field">
                <label for="email">Correo electrónico</label>
                <InputText id="email" v-model="form.email" type="email" autofocus :invalid="!!form.errors.email" />
                <small v-if="form.errors.email" style="color: var(--danger)">{{ form.errors.email }}</small>
            </div>
            <Button type="submit" label="Enviar enlace" icon="pi pi-envelope" :loading="form.processing" fluid />
            <Link :href="route('login')" class="small" style="text-align: center">Volver a iniciar sesión</Link>
        </form>
    </GuestLayout>
</template>
