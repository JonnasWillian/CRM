<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AppAlert from '@/Components/ui/AppAlert.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppCheckbox from '@/Components/ui/AppCheckbox.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import FormField from '@/Components/ui/FormField.vue';

defineProps({ canResetPassword: { type: Boolean }, status: { type: String } });

const form = useForm({ email: '', password: '', remember: false });
const enviar = () => form.post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Entrar" />
    <AuthLayout titulo="Entrar" descricao="Acesse o CRM da sua empresa.">
        <AppAlert v-if="status" tom="ok" class="mb-4">{{ status }}</AppAlert>
        <form class="space-y-4" @submit.prevent="enviar">
            <FormField rotulo="E-mail" :erro="form.errors.email">
                <AppInput v-model="form.email" type="email" autocomplete="username" autofocus />
            </FormField>
            <FormField rotulo="Senha" :erro="form.errors.password">
                <AppInput v-model="form.password" type="password" autocomplete="current-password" />
            </FormField>
            <div class="flex items-center justify-between gap-3">
                <AppCheckbox v-model="form.remember" rotulo="Manter conectado" />
                <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm font-medium text-primary hover:underline">Esqueci a senha</Link>
            </div>
            <AppButton type="submit" bloco :carregando="form.processing">Entrar</AppButton>
        </form>
        <template #rodape>Sua empresa ainda não usa? <Link :href="route('register')" class="font-medium text-primary hover:underline">Criar conta</Link></template>
    </AuthLayout>
</template>
