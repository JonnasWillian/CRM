<script setup>
import { computed } from "vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import AuthLayout from "@/Layouts/AuthLayout.vue";
import PainelDeEntrada from "@/Components/auth/PainelDeEntrada.vue";
import CampoDeSenha from "@/Components/auth/CampoDeSenha.vue";
import AppButton from "@/Components/ui/AppButton.vue";
import AppInput from "@/Components/ui/AppInput.vue";
import FormField from "@/Components/ui/FormField.vue";

/**
 * Criar a conta da empresa: quem cadastra é o admin e convida a equipe depois.
 * `?plano=` vem dos botões do site; se bater com um plano configurado, a tela
 * diz qual é e deixa trocar. Toda conta nasce em teste grátis, sem cartão.
 */
const pagina = usePage();
const dias = pagina.props.app?.dias_de_teste ?? 14;
const planos = pagina.props.app?.planos ?? [];
const chaveDoPlano = new URLSearchParams(window.location.search).get("plano");
const plano = computed(
    () => planos.find((p) => p.chave === chaveDoPlano) ?? null,
);

const form = useForm({
    empresa: "",
    name: "",
    email: "",
    password: "",
    plano: plano.value?.chave ?? null,
});
const enviar = () =>
    form.post(route("register"), { onFinish: () => form.reset("password") });

const PASSOS = [
    "Crie a conta da sua empresa: você é o admin.",
    "Importe a planilha de leads ou cadastre o primeiro.",
    "Convide a equipe e veja o pipeline, as tarefas e o faturamento no dashboard.",
];
</script>

<template>
    <Head title="Criar conta" />
    <AuthLayout
        titulo="Comece grátis"
        :descricao="`${dias} dias para testar com a sua equipe. Sem cartão, sem contrato.`"
        :lema="`${dias} dias grátis`"
    >
        <p
            v-if="plano"
            data-plano-escolhido
            class="mb-6 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-md border border-line bg-bg-2 px-3.5 py-2.5 text-sm text-fg-1"
        >
            <span
                >Plano
                <strong class="font-semibold text-fg-0">{{ plano.nome }}</strong
                >, grátis por {{ dias }} dias. Depois, R$
                {{ plano.preco_mensal }} por usuário/mês.</span
            >
            <a href="/precos" class="font-medium text-primary hover:underline"
                >Trocar</a
            >
        </p>

        <form class="space-y-5" @submit.prevent="enviar">
            <FormField
                rotulo="Nome da empresa"
                obrigatorio
                :erro="form.errors.empresa"
            >
                <AppInput
                    v-model="form.empresa"
                    autocomplete="organization"
                    placeholder="Ex.: Agência Prisma"
                    autofocus
                />
            </FormField>
            <FormField rotulo="Seu nome" obrigatorio :erro="form.errors.name">
                <AppInput v-model="form.name" autocomplete="name" />
            </FormField>
            <FormField
                rotulo="E-mail de trabalho"
                obrigatorio
                :erro="form.errors.email"
            >
                <AppInput
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    placeholder="voce@suaempresa.com.br"
                />
            </FormField>
            <FormField
                rotulo="Senha"
                obrigatorio
                :erro="form.errors.password"
                dica="Mínimo de 8 caracteres."
            >
                <CampoDeSenha
                    v-model="form.password"
                    autocomplete="new-password"
                />
            </FormField>
            <AppButton
                type="submit"
                bloco
                :carregando="form.processing"
                class="h-11 text-[15px]"
                >Criar conta e começar o teste</AppButton
            >
            <p class="text-xs leading-relaxed text-fg-2">
                Ao criar a conta você concorda com os
                <a href="/termos" class="underline hover:text-fg-0"
                    >Termos de uso</a
                >
                e a
                <a href="/privacidade" class="underline hover:text-fg-0"
                    >Política de privacidade</a
                >.
            </p>
        </form>

        <template #rodape
            >Já tem conta?
            <Link
                :href="route('login')"
                class="font-medium text-primary hover:underline"
                >Entrar</Link
            ></template
        >

        <template #painel>
            <PainelDeEntrada
                :titulo="`Seus primeiros ${dias} dias, sem cartão.`"
                descricao="Em cinco minutos a planilha vira um pipeline que avisa quem está parado, o que vence hoje e quanto entra este mês."
                :passos="PASSOS"
            />
        </template>
    </AuthLayout>
</template>
