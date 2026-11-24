<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { ArrowLeft, Bot, ChevronRight, CircleCheck, MessageCircleQuestion, X } from 'lucide-vue-next';
import AppAlert from '@/Components/ui/AppAlert.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppCheckbox from '@/Components/ui/AppCheckbox.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import AppTextarea from '@/Components/ui/AppTextarea.vue';
import FilterChips from '@/Components/ui/FilterChips.vue';
import { useSuporte } from '@/Composables/useSuporte';
import { errosDeValidacao, mensagemDeErro } from '@/utils/erros';
import { AREAS, ASSUNTOS, DICAS, IMPACTOS, PERGUNTA_DO_RELATO, areaDaTela } from './roteiro';

/**
 * Assistente de ajuda e feedback: botão flutuante que abre uma triagem de
 * uma pergunta por tela.
 *
 *   dados → assunto → área → (dicas, só em dúvida) → relato → fim
 *
 * Nome e e-mail vêm da conta e só são mostrados para a pessoa conferir; o
 * servidor usa os da sessão, não os da tela. Dúvida passa pelas dicas antes
 * de virar chamado. Problema, cobrança e dúvida não resolvida vão ao
 * suporte; sugestão e elogio só se a pessoa pedir resposta.
 * Fechar não apaga o que foi escrito; enviar, sim.
 */
const pagina = usePage();
const { aberto, abrir, fechar } = useSuporte();

const usuario = computed(() => pagina.props.auth?.user ?? {});
const produto = computed(() => pagina.props.app?.nome ?? 'UserFlow');
const primeiroNome = computed(() => String(usuario.value.name ?? '').trim().split(/\s+/)[0] ?? '');

const inicial = () => ({
    outroEmail: false, emailContato: '', tipo: null, area: 'outro', impacto: null, mensagem: '', nota: null, querResposta: false,
});
const f = reactive(inicial());
const passo = ref('dados'); // dados | assunto | area | dicas | relato | fim
const historico = ref([]);
const erros = ref({});
const erroGeral = ref(null);
const enviando = ref(false);
const resultado = ref(null); // { protocolo, suporte_acionado, email_contato } ou { resolvido: true }

const painel = ref(null);
const lancador = ref(null);

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
const vaiAoSuporte = computed(() => ['problema', 'cobranca', 'duvida'].includes(f.tipo) || f.querResposta);
const pergunta = computed(() => PERGUNTA_DO_RELATO[f.tipo] ?? PERGUNTA_DO_RELATO.problema);
const dicas = computed(() => DICAS[f.area] ?? DICAS.outro);
const emailDeResposta = computed(() => (f.outroEmail && f.emailContato ? f.emailContato : usuario.value.email));

// Progresso: quantos passos este caminho tem e em qual a pessoa está.
const caminho = computed(() => ['dados', 'assunto', ...(f.tipo === 'cobranca' ? [] : ['area']), ...(f.tipo === 'duvida' ? ['dicas'] : []), 'relato']);
const posicao = computed(() => caminho.value.indexOf(passo.value) + 1);

function ir(proximo) {
    historico.value.push(passo.value);
    erros.value = {};
    erroGeral.value = null;
    passo.value = proximo;
}

function voltar() {
    erros.value = {};
    erroGeral.value = null;
    passo.value = historico.value.pop() ?? 'dados';
}

function confirmarDados(usarOutro) {
    if (!usarOutro) {
        f.outroEmail = false;
        return ir('assunto');
    }
    if (!f.outroEmail) {
        f.outroEmail = true;
        return;
    }
    if (!EMAIL.test(f.emailContato.trim())) {
        erros.value = { email_contato: 'Informe um e-mail válido.' };
        return;
    }
    ir('assunto');
}

function escolherAssunto(tipo) {
    f.tipo = tipo;
    if (tipo === 'cobranca') {
        f.area = 'assinatura';
        return ir('relato');
    }
    f.area = areaDaTela();
    ir('area');
}

async function enviar() {
    const faltando = {};
    if (f.mensagem.trim().length < 10) faltando.mensagem = 'Conte um pouco mais: pelo menos 10 caracteres.';
    if (f.tipo === 'problema' && !f.impacto) faltando.impacto = 'Diga o quanto isso atrapalha.';
    erros.value = faltando;
    erroGeral.value = null;
    if (Object.keys(faltando).length) return;

    enviando.value = true;
    try {
        const { data } = await axios.post(route('api.suporte.store'), {
            tipo: f.tipo,
            area: f.area,
            impacto: f.tipo === 'problema' ? f.impacto : null,
            mensagem: f.mensagem.trim(),
            nota: f.nota,
            email_contato: f.outroEmail ? f.emailContato.trim() : null,
            acionar_suporte: vaiAoSuporte.value,
            pagina: window.location.pathname,
        }, { silencioso: true }); // o erro aparece aqui dentro, com o texto preservado
        resultado.value = data;
        historico.value = [];
        passo.value = 'fim';
        Object.assign(f, inicial());
    } catch (e) {
        const campos = errosDeValidacao(e);
        erros.value = Object.fromEntries(Object.entries(campos).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
        if (!erros.value.mensagem && !erros.value.impacto) erroGeral.value = mensagemDeErro(e, 'Não foi possível enviar agora. Tente de novo em instantes.');
    } finally {
        enviando.value = false;
    }
}

function resolvido() {
    resultado.value = { resolvido: true };
    historico.value = [];
    passo.value = 'fim';
    Object.assign(f, inicial());
}

function recomecar() {
    resultado.value = null;
    historico.value = [];
    passo.value = 'dados';
}

function aoTeclar(e) {
    if (e.key !== 'Escape') return;
    e.preventDefault(); // não deixa o Esc encerrar o passo a passo, se estiver ativo
    fechar();
}

watch(aberto, async (agora) => {
    await nextTick();
    if (agora) painel.value?.focus({ preventScroll: true });
    else lancador.value?.focus?.({ preventScroll: true });
});
</script>

<template>
    <!-- Botão flutuante -->
    <button
        v-if="!aberto"
        ref="lancador"
        type="button"
        data-suporte-abrir
        aria-label="Ajuda e feedback"
        title="Ajuda e feedback"
        class="fixed bottom-4 right-4 z-40 inline-flex h-12 w-12 items-center justify-center rounded-full bg-primary text-primary-fg shadow-3 transition-colors duration-1 ease-ui hover:bg-primary-hover print:hidden"
        @click="abrir"
    >
        <MessageCircleQuestion :size="22" aria-hidden="true" />
    </button>

    <!-- Painel: canto inferior direito; no celular, folha na base da tela -->
    <section
        v-else
        ref="painel"
        role="dialog"
        aria-labelledby="suporte-titulo"
        tabindex="-1"
        class="fixed inset-x-0 bottom-0 z-[60] flex max-h-[92dvh] flex-col overflow-y-auto rounded-t-xl border border-line bg-bg-1 text-fg-0 shadow-3 focus:outline-none sm:inset-x-auto sm:bottom-4 sm:right-4 sm:max-h-[calc(100dvh-2rem)] sm:w-[400px] sm:rounded-xl"
        @keydown="aoTeclar"
    >
        <header class="flex items-center gap-3 border-b border-line px-4 py-3">
            <button v-if="historico.length && passo !== 'fim'" type="button" aria-label="Voltar" class="-ml-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-fg-1 hover:bg-bg-2 hover:text-fg-0" @click="voltar">
                <ArrowLeft :size="18" aria-hidden="true" />
            </button>
            <span v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-subtle text-primary" aria-hidden="true"><Bot :size="20" /></span>
            <div class="min-w-0 flex-1 leading-tight">
                <p id="suporte-titulo" class="truncate font-display text-md font-semibold">Assistente {{ produto }}</p>
                <p class="text-sm text-fg-2">
                    <template v-if="passo === 'fim'">Ajuda e feedback</template>
                    <template v-else>Passo <span class="num">{{ posicao }}</span> de <span class="num">{{ caminho.length }}</span></template>
                </p>
            </div>
            <button type="button" aria-label="Fechar" class="-mr-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-fg-1 hover:bg-bg-2 hover:text-fg-0" @click="fechar">
                <X :size="18" aria-hidden="true" />
            </button>
        </header>
        <div v-if="passo !== 'fim'" class="h-1 bg-bg-2" aria-hidden="true">
            <div class="h-1 rounded-r-full bg-primary transition-[width] duration-2 ease-ui" :style="{ width: `${(posicao / caminho.length) * 100}%` }" />
        </div>

        <div class="space-y-4 px-4 py-5" :data-passo="passo">
            <!-- 1. Confirmar os dados da conta -->
            <template v-if="passo === 'dados'">
                <div>
                    <p class="font-display text-lg font-semibold">Olá{{ primeiroNome ? `, ${primeiroNome}` : '' }}! Vamos conferir seus dados.</p>
                    <p class="mt-1 text-md text-fg-1">São os da sua conta. É com eles que o suporte fala com você.</p>
                </div>
                <dl class="rounded-lg bg-bg-2 px-4 py-3 text-md">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="shrink-0 text-sm text-fg-2">Nome</dt>
                        <dd class="truncate font-medium">{{ usuario.name }}</dd>
                    </div>
                    <div class="mt-2 flex items-baseline justify-between gap-3">
                        <dt class="shrink-0 text-sm text-fg-2">E-mail</dt>
                        <dd class="truncate font-medium">{{ usuario.email }}</dd>
                    </div>
                </dl>
                <p class="text-md font-medium">Podemos responder neste e-mail?</p>
                <div v-if="f.outroEmail">
                    <label for="suporte-email" class="mb-1.5 block text-sm font-medium text-fg-1">E-mail para contato</label>
                    <AppInput id="suporte-email" v-model="f.emailContato" type="email" inputmode="email" autocomplete="email" placeholder="voce@suaempresa.com.br" autofocus />
                    <p v-if="erros.email_contato" role="alert" class="mt-1.5 text-sm text-danger">{{ erros.email_contato }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <AppButton v-if="f.outroEmail" bloco @click="confirmarDados(true)">Continuar</AppButton>
                    <AppButton :variante="f.outroEmail ? 'ghost' : 'primary'" bloco @click="confirmarDados(false)">Sim, pode usar {{ f.outroEmail ? 'o da conta' : 'este' }}</AppButton>
                    <AppButton v-if="!f.outroEmail" variante="secondary" bloco @click="confirmarDados(true)">Usar outro e-mail</AppButton>
                </div>
                <p class="text-sm text-fg-2">Nome errado? Corrija em Perfil e conta, no menu da sua conta.</p>
            </template>

            <!-- 2. Assunto -->
            <template v-else-if="passo === 'assunto'">
                <p class="font-display text-lg font-semibold">Sobre o que você quer falar?</p>
                <div class="overflow-hidden rounded-lg border border-line">
                    <button
                        v-for="a in ASSUNTOS"
                        :key="a.valor"
                        type="button"
                        class="flex w-full items-center gap-3 border-b border-line px-3.5 py-3 text-left transition-colors duration-1 ease-ui last:border-b-0 hover:bg-bg-2"
                        @click="escolherAssunto(a.valor)"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-subtle text-primary" aria-hidden="true"><component :is="a.icone" :size="18" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-md font-medium">{{ a.rotulo }}</span>
                            <span class="block text-sm text-fg-2">{{ a.detalhe }}</span>
                        </span>
                        <ChevronRight :size="16" class="shrink-0 text-fg-2" aria-hidden="true" />
                    </button>
                </div>
            </template>

            <!-- 3. Área -->
            <template v-else-if="passo === 'area'">
                <div>
                    <p class="font-display text-lg font-semibold">Em que parte do sistema?</p>
                    <p class="mt-1 text-md text-fg-1">Já marquei a tela em que você está. Troque se for outra.</p>
                </div>
                <FilterChips v-model="f.area" :opcoes="AREAS" />
                <AppButton bloco @click="ir(f.tipo === 'duvida' ? 'dicas' : 'relato')">Continuar</AppButton>
            </template>

            <!-- 3b. Dicas antes de abrir chamado de dúvida -->
            <template v-else-if="passo === 'dicas'">
                <div>
                    <p class="font-display text-lg font-semibold">Veja se isto responde.</p>
                    <p class="mt-1 text-md text-fg-1">As dúvidas mais comuns sobre {{ AREAS.find((a) => a.valor === f.area)?.rotulo.toLowerCase() }}.</p>
                </div>
                <ul class="space-y-2">
                    <li v-for="d in dicas" :key="d.titulo" data-dica class="rounded-lg bg-bg-2 px-4 py-3">
                        <p class="text-md font-medium">{{ d.titulo }}</p>
                        <p class="mt-0.5 text-md text-fg-1">{{ d.texto }}</p>
                    </li>
                </ul>
                <div class="flex flex-col gap-2">
                    <AppButton bloco @click="resolvido">Resolveu, obrigado</AppButton>
                    <AppButton variante="secondary" bloco @click="ir('relato')">Ainda preciso de ajuda</AppButton>
                </div>
            </template>

            <!-- 4. Relato -->
            <template v-else-if="passo === 'relato'">
                <div>
                    <label for="suporte-mensagem" class="block font-display text-lg font-semibold">{{ pergunta.titulo }}</label>
                    <p class="mt-1 text-md text-fg-1">{{ pergunta.exemplo }}</p>
                </div>
                <div>
                    <AppTextarea id="suporte-mensagem" v-model="f.mensagem" :rows="5" :maxlength="3000" />
                    <p v-if="erros.mensagem" role="alert" class="mt-1.5 text-sm text-danger">{{ erros.mensagem }}</p>
                </div>

                <fieldset v-if="f.tipo === 'problema'">
                    <legend class="mb-2 text-md font-medium">O quanto isso atrapalha?</legend>
                    <div class="flex flex-col gap-2">
                        <button
                            v-for="i in IMPACTOS"
                            :key="i.valor"
                            type="button"
                            role="radio"
                            :aria-checked="f.impacto === i.valor ? 'true' : 'false'"
                            class="flex min-h-11 items-center gap-3 rounded border px-3.5 py-2.5 text-left text-md transition-colors duration-1 ease-ui"
                            :class="f.impacto === i.valor ? 'border-primary bg-primary-subtle font-medium text-primary' : 'border-line text-fg-1 hover:border-line-2 hover:text-fg-0'"
                            @click="f.impacto = i.valor"
                        >
                            <span class="flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full border-2" :class="f.impacto === i.valor ? 'border-primary' : 'border-line-2'" aria-hidden="true">
                                <span v-if="f.impacto === i.valor" class="h-2 w-2 rounded-full bg-primary" />
                            </span>
                            {{ i.rotulo }}
                        </button>
                    </div>
                    <p v-if="erros.impacto" role="alert" class="mt-1.5 text-sm text-danger">{{ erros.impacto }}</p>
                </fieldset>

                <template v-if="f.tipo === 'sugestao' || f.tipo === 'elogio'">
                    <fieldset>
                        <legend class="text-md font-medium">De 0 a 10, quanto você recomendaria o {{ produto }}?</legend>
                        <p class="mb-2 text-sm text-fg-2">Opcional.</p>
                        <div class="grid grid-cols-11 gap-1">
                            <button
                                v-for="n in 11"
                                :key="n"
                                type="button"
                                :data-nota="n - 1"
                                :aria-pressed="f.nota === n - 1 ? 'true' : 'false'"
                                :aria-label="`Nota ${n - 1}`"
                                class="num h-10 rounded text-sm font-medium transition-colors duration-1 ease-ui"
                                :class="f.nota === n - 1 ? 'bg-primary text-primary-fg' : 'bg-bg-2 text-fg-1 hover:text-fg-0'"
                                @click="f.nota = f.nota === n - 1 ? null : n - 1"
                            >
                                {{ n - 1 }}
                            </button>
                        </div>
                    </fieldset>
                    <AppCheckbox v-model="f.querResposta" rotulo="Quero que o suporte me responda" />
                </template>

                <AppAlert v-if="erroGeral" tom="danger">{{ erroGeral }}</AppAlert>
                <AppButton bloco :carregando="enviando" @click="enviar">{{ vaiAoSuporte ? 'Enviar ao suporte' : 'Enviar feedback' }}</AppButton>
                <p class="text-sm text-fg-2">
                    <template v-if="vaiAoSuporte">A resposta vai para <span class="font-medium text-fg-1">{{ emailDeResposta }}</span> e pode levar até 5 dias úteis. </template>
                    Junto vão seu nome, e-mail, empresa, a página em que você está e o navegador.
                </p>
            </template>

            <!-- 5. Fim -->
            <div v-else class="flex flex-col items-center py-4 text-center" role="status">
                <CircleCheck :size="44" class="text-ok" aria-hidden="true" />
                <template v-if="resultado?.resolvido">
                    <p class="mt-3 font-display text-lg font-semibold">Que bom que resolveu.</p>
                    <p class="mt-1 text-md text-fg-1">Se precisar de novo, é só abrir este botão.</p>
                </template>
                <template v-else-if="resultado?.suporte_acionado">
                    <p class="mt-3 font-display text-lg font-semibold">Recebemos seu pedido.</p>
                    <p class="mt-1 text-md text-fg-1">Protocolo <span class="num font-semibold text-fg-0">#{{ resultado.protocolo }}</span>. O suporte responde em <span class="font-medium text-fg-0">{{ resultado.email_contato }}</span>.</p>
                    <p class="mt-1 text-sm text-fg-2">A resposta pode levar até 5 dias úteis.</p>
                </template>
                <template v-else>
                    <p class="mt-3 font-display text-lg font-semibold">Obrigado pelo feedback.</p>
                    <p class="mt-1 text-md text-fg-1">Ele foi registrado e ajuda a decidir o que melhorar.</p>
                </template>
                <div class="mt-5 flex w-full flex-col gap-2">
                    <AppButton bloco @click="fechar(); recomecar()">Fechar</AppButton>
                    <AppButton variante="ghost" bloco @click="recomecar">Enviar outra mensagem</AppButton>
                </div>
            </div>
        </div>
    </section>
</template>
