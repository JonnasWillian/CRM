<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AppAlert from '@/Components/ui/AppAlert.vue';
import AppButton from '@/Components/ui/AppButton.vue';

const props = defineProps({ status: { type: String } });
const form = useForm({});
const enviado = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head title="Confirmar e-mail" />
    <AuthLayout titulo="Confirme seu e-mail" descricao="Enviamos um link para o seu e-mail. Clique nele para ativar a conta.">
        <AppAlert v-if="enviado" tom="ok" class="mb-4">Enviamos um novo link para o e-mail do cadastro.</AppAlert>
        <form class="flex flex-wrap items-center justify-between gap-3" @submit.prevent="form.post(route('verification.send'))">
            <AppButton type="submit" :carregando="form.processing">Reenviar e-mail</AppButton>
            <Link :href="route('logout')" method="post" as="button" class="text-sm text-fg-1 hover:text-fg-0">Sair</Link>
        </form>
    </AuthLayout>
</template>
