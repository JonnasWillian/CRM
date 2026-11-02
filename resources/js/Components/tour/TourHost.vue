<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import AppButton from '@/Components/ui/AppButton.vue';
import { useTour } from '@/Composables/useTour';
import { posicionar } from '@/utils/posicionar';

/**
 * Desenha o passo atual do passo a passo: escurece a tela com um recorte em
 * volta do alvo (`[data-tour="…"]`), que continua clicável, e mostra o balão
 * com título, texto, "N de T", X e, nos passos de mostrar, Entendi.
 *
 * O alvo pode demorar (tela carregando, props diferidas): procura a cada
 * 200 ms. Sem alvo depois de 3 s (menu escondido no celular, sem permissão),
 * o balão vai para o centro com Entendi, em vez de travar. Alvo dentro de
 * um <dialog> aberto (modal de novo lead): o balão é desenhado dentro dele,
 * porque o resto da página fica inerte enquanto o modal está aberto. Lá,
 * tudo é `absolute` e medido a partir do modal: a animação de entrada dele
 * (transform com fill-mode both) o torna a referência dos filhos `fixed`,
 * e o overflow dele cortaria o balão.
 * X e Esc cancelam o passo a passo inteiro (Esc que um modal ou menu já
 * tratou só fecha o modal ou o menu). Ao montar, e quando o alvo não aparece,
 * o tour retoma a tela e o passo certos (useTour().retomar). Nos passos de
 * mostrar, o foco vai para o balão; nos de ação, fica no alvo.
 */
const tour = useTour();

const INTERVALO_MS = 200;
const PROCURA_MS = 3000;
const FOLGA = 8;

const alvo = ref(null);
const retangulo = ref(null);
const esgotou = ref(false);
const balao = ref(null);
const posicao = ref({ top: 0, left: 0 });
let procurado = 0;
let relogio = null;
let pararNavegacao = null;

const visivel = (el) => (typeof el.checkVisibility === 'function' ? el.checkVisibility() : true);
const movimentoReduzido = () => Boolean(window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches);
const destino = computed(() => alvo.value?.closest?.('dialog[open]') ?? 'body');
const dentroDeDialog = computed(() => destino.value !== 'body');
const mostrar = computed(() => tour.ativo.value && (alvo.value !== null || esgotou.value));
const precisaEntendi = computed(() => tour.passo.value?.avanca === 'entendi' || (alvo.value === null && tour.passo.value?.avanca !== 'concluir'));

function procurar() {
    const p = tour.passo.value;
    if (!p) return;
    if (!p.alvo) {
        alvo.value = null;
        esgotou.value = true;
        return;
    }
    const el = [...document.querySelectorAll(`[data-tour="${p.alvo}"]`)].find(visivel) ?? null;
    if (el && el !== alvo.value) el.scrollIntoView?.({ block: 'center', behavior: movimentoReduzido() ? 'auto' : 'smooth' });
    alvo.value = el;
    if (!el) {
        procurado += INTERVALO_MS;
        // Sem alvo: primeiro tenta voltar ao passo e à tela certos; se não houver para onde, balão no centro.
        if (procurado >= PROCURA_MS && !esgotou.value && !tour.retomar()) esgotou.value = true;
    }
    medir();
}

async function medir() {
    if (!alvo.value) {
        retangulo.value = null;
        return;
    }
    // Dentro de um modal, as coordenadas são relativas a ele, e o espaço disponível é o dele.
    const caixa = dentroDeDialog.value ? destino.value.getBoundingClientRect() : null;
    const dx = caixa?.left ?? 0;
    const dy = caixa?.top ?? 0;
    const a = alvo.value.getBoundingClientRect();
    const r = { top: a.top - dy, left: a.left - dx, width: a.width, height: a.height, right: a.right - dx, bottom: a.bottom - dy };
    retangulo.value = r;
    await nextTick();
    const painel = balao.value?.getBoundingClientRect();
    const folgado = { top: r.top - FOLGA, left: r.left - FOLGA, right: r.right + FOLGA, bottom: r.bottom + FOLGA, width: r.width + 2 * FOLGA, height: r.height + 2 * FOLGA };
    const tamanho = { width: painel?.width || (caixa ? 300 : 340), height: painel?.height || 180 };
    if (!caixa) {
        posicao.value = posicionar(folgado, tamanho, { lado: 'bottom', alinhar: 'start', margem: 12 });
        return;
    }
    // No modal: ao lado do campo, alinhado ao topo dele (embaixo cobriria o próximo campo que a
    // pessoa vai preencher); se não couber ao lado, embaixo. Sempre dentro das bordas do modal.
    const MARGEM = 12;
    if (folgado.right + MARGEM + tamanho.width <= caixa.width - MARGEM) {
        const topo = Math.min(Math.max(folgado.top, MARGEM), Math.max(MARGEM, caixa.height - tamanho.height - MARGEM));
        posicao.value = { top: topo, left: folgado.right + MARGEM };
    } else {
        posicao.value = posicionar(folgado, tamanho, { lado: 'bottom', alinhar: 'start', margem: MARGEM, viewport: { width: caixa.width, height: caixa.height } });
    }
}

// Passo novo: recomeça a procura.
watch(() => tour.passo.value?.chave, () => {
    alvo.value = null;
    esgotou.value = false;
    procurado = 0;
    procurar();
}, { immediate: true });

const px = (n) => `${Math.round(n)}px`;
const paineis = computed(() => {
    const r = retangulo.value;
    if (!r) return [];
    const topo = r.top - FOLGA;
    const base = r.bottom + FOLGA;
    const altura = r.height + 2 * FOLGA;
    return [
        { top: 0, left: 0, right: 0, height: px(Math.max(0, topo)) },
        { top: px(base), left: 0, right: 0, bottom: 0 },
        { top: px(topo), left: 0, width: px(Math.max(0, r.left - FOLGA)), height: px(altura) },
        { top: px(topo), left: px(r.right + FOLGA), right: 0, height: px(altura) },
    ];
});
const anel = computed(() => {
    const r = retangulo.value;
    return r ? { top: px(r.top - 4), left: px(r.left - 4), width: px(r.width + 8), height: px(r.height + 8) } : null;
});

function aoTeclar(e) {
    // Esc que um modal ou menu já usou para fechar não encerra o passo a passo.
    if (e.key === 'Escape' && !e.defaultPrevented && tour.ativo.value) tour.cancelar();
}

// Passos de mostrar: o foco vai para o balão (teclado e leitor de tela). Nos de ação, fica no alvo.
watch([mostrar, () => tour.passo.value?.chave], async ([visivelAgora]) => {
    if (!visivelAgora || !(precisaEntendi.value || tour.passo.value?.avanca === 'concluir')) return;
    await nextTick();
    balao.value?.focus({ preventScroll: true });
}, { immediate: true });

onMounted(() => {
    // Página recarregada ou volta outro dia: se o alvo do passo não está nesta tela, retoma a tela e o passo certos.
    const p = tour.passo.value;
    if (p && !(p.alvo && document.querySelector(`[data-tour="${p.alvo}"]`))) tour.retomar();
    relogio = setInterval(() => tour.ativo.value && procurar(), INTERVALO_MS);
    window.addEventListener('keydown', aoTeclar);
    window.addEventListener('resize', medir);
    window.addEventListener('scroll', medir, true);
    pararNavegacao = router.on('navigate', () => tour.aoNavegar());
});

onBeforeUnmount(() => {
    clearInterval(relogio);
    window.removeEventListener('keydown', aoTeclar);
    window.removeEventListener('resize', medir);
    window.removeEventListener('scroll', medir, true);
    pararNavegacao?.();
});
</script>

<template>
    <Teleport v-if="mostrar" :to="destino">
        <!-- Escurece tudo menos o alvo (dentro de um modal, o próprio modal já escurece) -->
        <template v-if="!dentroDeDialog">
            <template v-if="alvo && retangulo">
                <div v-for="(p, i) in paineis" :key="i" class="fixed z-[70] bg-fg-0/40" :style="p" aria-hidden="true" />
            </template>
            <div v-else class="fixed inset-0 z-[70] bg-fg-0/40" aria-hidden="true" />
        </template>
        <div v-if="anel" data-tour-anel class="pointer-events-none z-[71] rounded-lg ring-2 ring-primary ring-offset-2 ring-offset-bg-1" :class="dentroDeDialog ? 'absolute' : 'fixed'" :style="anel" aria-hidden="true" />

        <div
            ref="balao"
            data-tour-balao
            tabindex="-1"
            role="dialog"
            aria-labelledby="tour-titulo"
            aria-describedby="tour-texto"
            :data-centralizado="alvo ? undefined : 'true'"
            class="z-[72] rounded-lg border border-line bg-bg-1 p-5 text-fg-0 shadow-3"
            :class="[dentroDeDialog ? 'absolute w-[min(300px,calc(100%-24px))]' : 'fixed w-[min(340px,calc(100vw-32px))]', alvo ? '' : 'left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2']"
            :style="alvo ? { top: px(posicao.top), left: px(posicao.left) } : undefined"
        >
            <div class="flex items-start justify-between gap-3">
                <p id="tour-titulo" class="font-display text-lg font-semibold leading-snug">{{ tour.passo.value.titulo }}</p>
                <button
                    type="button"
                    aria-label="Fechar o passo a passo"
                    class="-mr-1.5 -mt-1 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-fg-2 transition-colors duration-1 ease-ui hover:bg-bg-2 hover:text-fg-0"
                    @click="tour.cancelar()"
                >
                    <X :size="16" aria-hidden="true" />
                </button>
            </div>
            <p id="tour-texto" class="mt-1.5 text-md leading-relaxed text-fg-1">{{ tour.passo.value.texto }}</p>
            <div class="mt-4 flex items-center justify-between gap-3">
                <span class="num text-sm text-fg-2">{{ tour.indice.value + 1 }} de {{ tour.total.value }}</span>
                <AppButton v-if="tour.passo.value.avanca === 'concluir'" tamanho="sm" @click="tour.concluir()">Concluir</AppButton>
                <AppButton v-else-if="precisaEntendi" tamanho="sm" @click="tour.proximo()">Entendi</AppButton>
            </div>
        </div>
    </Teleport>
</template>
