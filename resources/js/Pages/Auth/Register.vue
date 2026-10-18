<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import FormField from '@/Components/ui/FormField.vue';

/** Criar a conta da empresa: quem cadastra é o admin. Membros entram por convite (Equipe). */
const form = useForm({ empresa: '', name: '', email: '', password: '', password_confirmation: '' });
const enviar = () => form.post(route('register'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head title="Criar conta" />
    <AuthLayout titulo="Criar conta" descricao="Você será o admin da empresa e poderá convidar a equipe depois.">
        <form class="space-y-4" @submit.prevent="enviar">
            <FormField rotulo="Nome da empresa" obrigatorio :erro="form.errors.empresa">
                <AppInput v-model="form.empresa" autocomplete="organization" autofocus />
            </FormField>
            <FormField rotulo="Seu nome" obrigatorio :erro="form.errors.name">
                <AppInput v-model="form.name" autocomplete="name" />
            </FormField>
            <FormField rotulo="E-mail" obrigatorio :erro="form.errors.email">
                <AppInput v-model="form.email" type="email" autocomplete="username" />
            </FormField>
            <FormField rotulo="Senha" obrigatorio :erro="form.errors.password" dica="Mínimo de 8 caracteres.">
                <AppInput v-model="form.password" type="password" autocomplete="new-password" />
            </FormField>
            <FormField rotulo="Confirmar senha" obrigatorio :erro="form.errors.password_confirmation">
                <AppInput v-model="form.password_confirmation" type="password" autocomplete="new-password" />
            </FormField>
            <AppButton type="submit" bloco :carregando="form.processing">Criar conta</AppButton>
        </form>
        <template #rodape>Já tem conta? <Link :href="route('login')" class="font-medium text-primary hover:underline">Entrar</Link></template>
    </AuthLayout>
</template>
