import { computed, ref } from 'vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import { PASSOS } from '@/Components/tour/passos';

/**
 * Passo a passo do primeiro acesso. Estado de módulo: o layout (TourHost,
 * BoasVindasModal) e as telas que avisam ações (LeadFormModal,
 * TarefaFormModal) compartilham o mesmo andamento. Nasce de auth.user
 * (tour_estado, tour_versao, tour_passo) e cada transição é gravada no
 * servidor sem esperar resposta, para recarregar a página retomar do mesmo
 * passo. Fora de andamento, `avancar` não faz nada: as telas podem avisar
 * sempre.
 */
const estado = ref(null); // pendente | em_andamento | concluido | cancelado
const versao = ref('completa');
const chave = ref(null);
let carregado = false;

function carregar(user) {
    if (carregado || !user) return;
    carregado = true;
    estado.value = user.tour_estado ?? 'concluido';
    versao.value = user.tour_versao ?? 'completa';
    chave.value = user.tour_passo ?? null;
}

function contexto() {
    const props = usePage().props;
    return {
        pode: (permissao) => props.auth?.permissions?.[permissao] === true,
        equipe: Boolean(props.empresa?.equipe_maior_que_um),
    };
}

const passos = computed(() => {
    const ctx = contexto();
    return (PASSOS[versao.value] ?? PASSOS.completa).filter((p) => !p.quando || p.quando(ctx));
});
// Passo gravado que não existe mais nesta lista (permissão mudou): recomeça do primeiro.
const indice = computed(() => Math.max(0, passos.value.findIndex((p) => p.chave === chave.value)));
const passo = computed(() => (estado.value === 'em_andamento' ? (passos.value[indice.value] ?? null) : null));
const ativo = computed(() => passo.value !== null);
const pendente = computed(() => estado.value === 'pendente');
const total = computed(() => passos.value.length);

function gravar(acao, chaveDoPasso) {
    const dados = chaveDoPasso === undefined ? { acao } : { acao, passo: chaveDoPasso };
    axios.patch(route('api.tour.update'), dados, { silencioso: true }).catch(() => {
        /* sem rede: o andamento segue nesta aba; o próximo passo grava de novo */
    });
}

function irParaTela(p) {
    if (p?.rota && typeof route === 'function' && !route().current(p.rota)) router.visit(route(p.rota));
}

const estaEm = (rota) => typeof route === 'function' && route().current(rota);

function irPara(i) {
    // Passo que avança ao chegar numa rota em que já se está ("Salvar e abrir" levou ao perfil): pula.
    let j = i;
    while (passos.value[j]?.avanca?.rota && estaEm(passos.value[j].avanca.rota)) j += 1;
    const proximoPasso = passos.value[j];
    if (!proximoPasso) return concluir();
    chave.value = proximoPasso.chave;
    gravar('avancar', proximoPasso.chave);
    irParaTela(proximoPasso);
}

function iniciar() {
    const primeiro = passos.value[0];
    estado.value = 'em_andamento';
    chave.value = primeiro.chave;
    gravar('iniciar', primeiro.chave);
    irParaTela(primeiro);
}

function proximo() {
    if (ativo.value) irPara(indice.value + 1);
}

/** A tela avisa uma ação; só conta se é a que o passo atual espera. */
function avancar(evento) {
    if (ativo.value && passo.value.avanca?.evento === evento) proximo();
}

/**
 * Ao voltar (página recarregada, outro dia) ou quando o alvo some: o passo que
 * dependia de algo que não existe mais recomeça de onde leva até lá, e a tela
 * do passo abre. Devolve se o passo mudou.
 */
function retomar() {
    if (!ativo.value) return false;
    const outra = passo.value.retomar?.(estaEm) ?? null;
    const mudou = outra !== null && outra !== chave.value && passos.value.some((p) => p.chave === outra);
    if (mudou) {
        chave.value = outra;
        gravar('avancar', outra);
    }
    irParaTela(passo.value);
    return mudou;
}

/** Depois de cada navegação do Inertia: passos que avançam ao chegar numa rota. */
function aoNavegar() {
    const rota = passo.value?.avanca?.rota;
    if (rota && typeof route === 'function' && route().current(rota)) proximo();
}

function concluir() {
    estado.value = 'concluido';
    chave.value = null;
    gravar('concluir');
}

function cancelar() {
    estado.value = 'cancelado';
    chave.value = null;
    gravar('cancelar');
}

/** Pelo menu da conta: a versão curta, que só aponta os lugares. */
function refazer() {
    versao.value = 'curta';
    estado.value = 'em_andamento';
    chave.value = passos.value[0].chave;
    gravar('refazer', chave.value);
    irParaTela(passos.value[0]);
}

export function useTour() {
    carregar(usePage().props.auth?.user);
    return { ativo, pendente, passo, indice, total, iniciar, proximo, avancar, aoNavegar, retomar, concluir, cancelar, refazer };
}
