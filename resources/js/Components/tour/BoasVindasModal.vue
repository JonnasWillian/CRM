<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ArrowRight, Sparkles } from 'lucide-vue-next';
import AppButton from '@/Components/ui/AppButton.vue';
import AppModal from '@/Components/ui/AppModal.vue';
import { useTour } from '@/Composables/useTour';

/**
 * Boas-vindas do primeiro acesso: abre enquanto o passo a passo do usuário
 * está pendente (logo depois do questionário, ou no primeiro acesso de quem
 * entrou por convite). "Vamos lá!" começa; recusar, X ou Esc cancelam e o
 * passo a passo só volta pelo menu da conta. Clicar fora não cancela
 * (persistente): um clique perdido não pode custar o passo a passo.
 */
const tour = useTour();
const pagina = usePage();
const produto = computed(() => pagina.props.app?.nome ?? 'UserFlow');
const primeiroNome = computed(() => String(pagina.props.auth?.user?.name ?? '').trim().split(/\s+/)[0] ?? '');
const titulo = computed(() => `Bem-vindo ao ${produto.value}${primeiroNome.value ? `, ${primeiroNome.value}` : ''}!`);
</script>

<template>
    <AppModal :aberto="tour.pendente.value" :titulo="titulo" tamanho="sm" persistente @fechar="tour.cancelar()">
        <div class="flex flex-col items-center py-2 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-subtle text-primary" aria-hidden="true">
                <Sparkles :size="26" />
            </span>
            <p class="mt-4 text-md leading-relaxed text-fg-1">Vamos montar sua rotina de vendas juntos. Leva uns dois minutos.</p>
            <p class="mt-1 text-sm text-fg-2">Você cadastra o primeiro lead, agenda uma tarefa e vê onde cada coisa fica.</p>
        </div>
        <template #rodape>
            <AppButton variante="ghost" @click="tour.cancelar()">Prefiro explorar sozinho</AppButton>
            <AppButton @click="tour.iniciar()">
                Vamos lá!
                <template #iconeDireita><ArrowRight /></template>
            </AppButton>
        </template>
    </AppModal>
</template>
