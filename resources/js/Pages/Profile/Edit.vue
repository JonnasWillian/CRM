<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppAlert from '@/Components/ui/AppAlert.vue';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppCard from '@/Components/ui/AppCard.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import AppModal from '@/Components/ui/AppModal.vue';
import FormField from '@/Components/ui/FormField.vue';
import PageContainer from '@/Components/ui/PageContainer.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionHeader from '@/Components/ui/SectionHeader.vue';
import SeletorDeTema from '@/Components/ui/SeletorDeTema.vue';
import { useToast } from '@/Composables/useToast';
import { ref } from 'vue';

/** Perfil: empresa, dados pessoais, senha e exclusão da conta. */
const props = defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
    impedimentosDeExclusao: { type: Array, default: () => [] },
    empresa: { type: Object, required: true }, // { nome, papel, editavel }
});

const toast = useToast();
const user = computed(() => usePage().props.auth.user);
const PAPEIS = { admin: 'Admin', gestor: 'Gestor', vendedor: 'Vendedor' };

const formEmpresa = useForm({ nome: props.empresa.nome ?? '' });
const formPerfil = useForm({ name: user.value.name, email: user.value.email });
const formSenha = useForm({ current_password: '', password: '', password_confirmation: '' });
const formExcluir = useForm({ password: '' });
const excluindo = ref(false);

const salvarPerfil = () => formPerfil.patch(route('profile.update'), { preserveScroll: true, onSuccess: () => toast.sucesso('Dados salvos.') });
const salvarSenha = () => formSenha.put(route('password.update'), {
    preserveScroll: true,
    onSuccess: () => { formSenha.reset(); toast.sucesso('Senha atualizada.'); },
    onError: () => formSenha.reset('password', 'password_confirmation', 'current_password'),
});
const excluir = () => formExcluir.delete(route('profile.destroy'), { preserveScroll: true, onFinish: () => formExcluir.reset() });
</script>

<template>
    <Head title="Perfil" />
    <AuthenticatedLayout>
        <PageContainer>
            <PageHeader titulo="Perfil" subtitulo="Seus dados de acesso e a empresa." />
            <div class="mx-auto max-w-2xl space-y-4">
                <AppCard>
                    <SectionHeader titulo="Minha empresa">
                        <template #acoes><AppBadge :tom="empresa.papel === 'admin' ? 'primary' : 'neutral'">{{ PAPEIS[empresa.papel] ?? empresa.papel }}</AppBadge></template>
                    </SectionHeader>
                    <form v-if="empresa.editavel" class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="formEmpresa.patch(route('profile.empresa'), { preserveScroll: true })">
                        <FormField rotulo="Nome da empresa" :erro="formEmpresa.errors.nome" class="min-w-0 flex-1">
                            <AppInput v-model="formEmpresa.nome" :maxlength="255" />
                        </FormField>
                        <AppButton type="submit" variante="secondary" :carregando="formEmpresa.processing" :disabled="!formEmpresa.isDirty">Salvar</AppButton>
                    </form>
                    <p v-else class="mt-3 text-md text-fg-0">{{ empresa.nome }}</p>
                </AppCard>

                <AppCard>
                    <SectionHeader titulo="Aparência" descricao="Escolha o tema deste navegador. “Sistema” acompanha o claro/escuro do seu computador." />
                    <div class="mt-4 max-w-sm"><SeletorDeTema /></div>
                </AppCard>

                <AppCard>
                    <SectionHeader titulo="Dados pessoais" />
                    <form class="mt-4 space-y-4" @submit.prevent="salvarPerfil">
                        <FormField rotulo="Nome" :erro="formPerfil.errors.name"><AppInput v-model="formPerfil.name" autocomplete="name" /></FormField>
                        <FormField rotulo="E-mail" :erro="formPerfil.errors.email"><AppInput v-model="formPerfil.email" type="email" autocomplete="username" /></FormField>
                        <AppAlert v-if="mustVerifyEmail && user.email_verified_at === null" tom="warn">
                            Seu e-mail ainda não foi confirmado.
                            <Link :href="route('verification.send')" method="post" as="button" class="font-medium underline">Reenviar link</Link>
                        </AppAlert>
                        <div class="flex justify-end"><AppButton type="submit" :carregando="formPerfil.processing" :disabled="!formPerfil.isDirty">Salvar</AppButton></div>
                    </form>
                </AppCard>

                <AppCard>
                    <SectionHeader titulo="Senha" descricao="Use uma senha longa e que você não usa em outro lugar." />
                    <form class="mt-4 space-y-4" @submit.prevent="salvarSenha">
                        <FormField rotulo="Senha atual" :erro="formSenha.errors.current_password"><AppInput v-model="formSenha.current_password" type="password" autocomplete="current-password" /></FormField>
                        <FormField rotulo="Nova senha" :erro="formSenha.errors.password"><AppInput v-model="formSenha.password" type="password" autocomplete="new-password" /></FormField>
                        <FormField rotulo="Confirmar nova senha" :erro="formSenha.errors.password_confirmation"><AppInput v-model="formSenha.password_confirmation" type="password" autocomplete="new-password" /></FormField>
                        <div class="flex justify-end"><AppButton type="submit" :carregando="formSenha.processing" :disabled="!formSenha.password">Atualizar senha</AppButton></div>
                    </form>
                </AppCard>

                <AppCard>
                    <SectionHeader titulo="Excluir conta" descricao="Apaga o seu acesso. Os leads da empresa continuam com ela." />
                    <AppAlert v-if="impedimentosDeExclusao.length" tom="warn" class="mt-4">
                        <ul class="list-disc space-y-1 pl-4"><li v-for="m in impedimentosDeExclusao" :key="m">{{ m }}</li></ul>
                        <template v-if="empresa.editavel" #acao><AppButton variante="secondary" tamanho="sm" as="Link" :href="route('configuracoes.equipe')">Ir para Equipe</AppButton></template>
                    </AppAlert>
                    <div class="mt-4 flex justify-end">
                        <AppButton variante="danger" :disabled="impedimentosDeExclusao.length > 0" @click="excluindo = true">Excluir minha conta</AppButton>
                    </div>
                </AppCard>
            </div>
        </PageContainer>

        <AppModal :aberto="excluindo" titulo="Excluir sua conta?" descricao="Não dá para desfazer. Confirme com a sua senha." tamanho="sm" @fechar="excluindo = false">
            <form id="form-excluir" @submit.prevent="excluir">
                <FormField rotulo="Senha" :erro="formExcluir.errors.password || formExcluir.errors.conta"><AppInput v-model="formExcluir.password" type="password" autocomplete="current-password" autofocus /></FormField>
            </form>
            <template #rodape>
                <AppButton variante="secondary" @click="excluindo = false">Cancelar</AppButton>
                <AppButton type="submit" form="form-excluir" variante="danger" :carregando="formExcluir.processing" :disabled="!formExcluir.password">Excluir conta</AppButton>
            </template>
        </AppModal>
    </AuthenticatedLayout>
</template>
