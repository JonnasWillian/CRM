<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AppAlert from '@/Components/ui/AppAlert.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import FormField from '@/Components/ui/FormField.vue';

defineProps({ status: { type: String } });
const form = useForm({ email: '' });
</script>

<template>
    <Head title="Esqueci a senha" />
    <AuthLayout titulo="Esqueci a senha" descricao="Informe seu e-mail e enviaremos um link para criar uma senha nova.">
        <AppAlert v-if="status" tom="ok" class="mb-4">{{ status }}</AppAlert>
        <form class="space-y-4" @submit.prevent="form.post(route('password.email'))">
            <FormField rotulo="E-mail" :erro="form.errors.email">
                <AppInput v-model="form.email" type="email" autocomplete="username" autofocus />
            </FormField>
            <AppButton type="submit" bloco :carregando="form.processing">Enviar link</AppButton>
        </form>
        <template #rodape><Link :href="route('login')" class="hover:text-fg-0">Voltar para o login</Link></template>
    </AuthLayout>
</template>
