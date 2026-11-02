<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import FormField from '@/Components/ui/FormField.vue';

const props = defineProps({ email: { type: String, required: true }, token: { type: String, required: true } });
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
const enviar = () => form.post(route('password.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head title="Nova senha" />
    <AuthLayout titulo="Nova senha" descricao="Escolha a senha que você vai usar daqui para frente.">
        <form class="space-y-4" @submit.prevent="enviar">
            <FormField flutuante rotulo="E-mail" :erro="form.errors.email">
                <AppInput placeholder=" " v-model="form.email" type="email" autocomplete="username" readonly />
            </FormField>
            <FormField flutuante rotulo="Nova senha" :erro="form.errors.password" dica="Mínimo de 8 caracteres.">
                <AppInput placeholder=" " v-model="form.password" type="password" autocomplete="new-password" autofocus />
            </FormField>
            <FormField flutuante rotulo="Confirmar senha" :erro="form.errors.password_confirmation">
                <AppInput placeholder=" " v-model="form.password_confirmation" type="password" autocomplete="new-password" />
            </FormField>
            <AppButton type="submit" bloco :carregando="form.processing">Salvar senha</AppButton>
        </form>
    </AuthLayout>
</template>
