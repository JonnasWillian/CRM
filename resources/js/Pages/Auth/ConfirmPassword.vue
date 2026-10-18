<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import FormField from '@/Components/ui/FormField.vue';

const form = useForm({ password: '' });
const enviar = () => form.post(route('password.confirm'), { onFinish: () => form.reset() });
</script>

<template>
    <Head title="Confirmar senha" />
    <AuthLayout titulo="Confirme sua senha" descricao="Esta é uma área protegida. Confirme a senha para continuar.">
        <form class="space-y-4" @submit.prevent="enviar">
            <FormField rotulo="Senha" :erro="form.errors.password">
                <AppInput v-model="form.password" type="password" autocomplete="current-password" autofocus />
            </FormField>
            <AppButton type="submit" bloco :carregando="form.processing">Confirmar</AppButton>
        </form>
    </AuthLayout>
</template>
