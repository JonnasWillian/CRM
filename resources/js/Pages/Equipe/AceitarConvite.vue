<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import AppAlert from '@/Components/ui/AppAlert.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import FormField from '@/Components/ui/FormField.vue';

/** Aceitar convite: nome e senha; o e-mail e a empresa vêm do convite. */
const props = defineProps({
    token: { type: String, required: true },
    convite: { type: Object, required: true }, // { email, papel, empresa, convidado_por, estado }
});

const PAPEIS = { admin: 'Admin', gestor: 'Gestor', vendedor: 'Vendedor' };
const form = useForm({ name: '', password: '', password_confirmation: '' });
const pendente = computed(() => props.convite.estado === 'pendente');
const MENSAGENS = {
    usado: 'Este convite já foi usado. Entre com a conta criada.',
    cancelado: 'Este convite foi cancelado. Peça um novo a quem convidou você.',
    expirado: 'Este convite expirou. Peça um novo a quem convidou você.',
};
const enviar = () => form.post(route('convites.store', props.token), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head title="Aceitar convite" />
    <AuthLayout :titulo="pendente ? `Entrar na ${convite.empresa}` : 'Convite indisponível'" :descricao="pendente ? `${convite.convidado_por ?? 'Alguém'} convidou você para a ${convite.empresa} como ${PAPEIS[convite.papel] ?? convite.papel}.` : null">
        <AppAlert v-if="!pendente" tom="warn">{{ MENSAGENS[convite.estado] }}</AppAlert>
        <form v-else class="space-y-4" @submit.prevent="enviar">
            <FormField rotulo="E-mail">
                <AppInput :model-value="convite.email" readonly />
            </FormField>
            <FormField rotulo="Seu nome" obrigatorio :erro="form.errors.name">
                <AppInput v-model="form.name" autocomplete="name" autofocus />
            </FormField>
            <FormField rotulo="Senha" obrigatorio :erro="form.errors.password || form.errors.email" dica="Mínimo de 8 caracteres.">
                <AppInput v-model="form.password" type="password" autocomplete="new-password" />
            </FormField>
            <FormField rotulo="Confirmar senha" obrigatorio :erro="form.errors.password_confirmation">
                <AppInput v-model="form.password_confirmation" type="password" autocomplete="new-password" />
            </FormField>
            <AppButton type="submit" bloco :carregando="form.processing" :disabled="!form.name || !form.password">Criar acesso e entrar</AppButton>
        </form>
        <template #rodape><Link :href="route('login')" class="hover:text-fg-0">Já tenho conta · Entrar</Link></template>
    </AuthLayout>
</template>
