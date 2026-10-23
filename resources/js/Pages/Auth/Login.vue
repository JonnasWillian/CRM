<script setup>
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import AuthLayout from "@/Layouts/AuthLayout.vue";
import PainelDeEntrada from "@/Components/auth/PainelDeEntrada.vue";
import CampoDeSenha from "@/Components/auth/CampoDeSenha.vue";
import AppAlert from "@/Components/ui/AppAlert.vue";
import AppButton from "@/Components/ui/AppButton.vue";
import AppCheckbox from "@/Components/ui/AppCheckbox.vue";
import AppInput from "@/Components/ui/AppInput.vue";
import FormField from "@/Components/ui/FormField.vue";

defineProps({ canResetPassword: { type: Boolean }, status: { type: String } });

const dias = usePage().props.app?.dias_de_teste ?? 14;
const form = useForm({ email: "", password: "", remember: false });
const enviar = () =>
    form.post(route("login"), { onFinish: () => form.reset("password") });
</script>

<template>
    <Head title="Entrar" />
    <AuthLayout
        titulo="Bem-vindo de volta"
        descricao="Entre para ver sua carteira, as tarefas de hoje e o que a equipe fatura este mês."
        lema="Vendas sob controle"
    >
        <AppAlert v-if="status" tom="ok" class="mb-5">{{ status }}</AppAlert>
        <form class="space-y-5" @submit.prevent="enviar">
            <FormField rotulo="E-mail" :erro="form.errors.email">
                <AppInput
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    placeholder="voce@suaempresa.com.br"
                    autofocus
                />
            </FormField>
            <FormField rotulo="Senha" :erro="form.errors.password">
                <CampoDeSenha
                    v-model="form.password"
                    autocomplete="current-password"
                />
            </FormField>
            <div class="flex items-center justify-between gap-3">
                <AppCheckbox
                    v-model="form.remember"
                    rotulo="Manter conectado"
                />
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-medium text-primary hover:underline"
                    >Esqueci a senha</Link
                >
            </div>
            <AppButton
                type="submit"
                bloco
                :carregando="form.processing"
                class="h-11 text-[15px]"
                >Entrar</AppButton
            >
        </form>

        <template #rodape>
            Sua empresa ainda não usa?
            <Link
                :href="route('register')"
                class="font-medium text-primary hover:underline"
                >Criar conta grátis por {{ dias }} dias</Link
            >
        </template>

        <template #painel>
            <PainelDeEntrada
                titulo="O que você já fatura e o que ainda vai entrar, numa tela só."
                descricao="Pipeline, tarefas e equipe em português. Contratos mensais contados mês a mês, com renovações e aditivos. Sem conversão de dólar e sem cem recursos que ninguém abre."
            />
        </template>
    </AuthLayout>
</template>
