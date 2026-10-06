<script setup>
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const props = defineProps({ email: String, token: String });
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
const enviar = () => form.post(route('password.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <GuestLayout titulo="Nueva contraseña">
        <h2>Definir contraseña</h2>
        <form class="stack" @submit.prevent="enviar">
            <div class="field">
                <label for="email">Correo electrónico</label>
                <InputText id="email" v-model="form.email" type="email" :invalid="!!form.errors.email" />
                <small v-if="form.errors.email" style="color: var(--danger)">{{ form.errors.email }}</small>
            </div>
            <div class="field">
                <label for="password">Nueva contraseña</label>
                <Password v-model="form.password" input-id="password" toggle-mask fluid :invalid="!!form.errors.password" />
                <small v-if="form.errors.password" style="color: var(--danger)">{{ form.errors.password }}</small>
            </div>
            <div class="field">
                <label for="password2">Confirmar contraseña</label>
                <Password v-model="form.password_confirmation" input-id="password2" :feedback="false" toggle-mask fluid />
            </div>
            <Button type="submit" label="Guardar contraseña" :loading="form.processing" fluid />
        </form>
    </GuestLayout>
</template>
