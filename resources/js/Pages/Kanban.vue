<script setup>
    import { ref, computed, onMounted } from 'vue';
    import { Head, Link, router, usePage } from '@inertiajs/vue3';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import axios from 'axios';
    import { Settings, X, Save, Plus, LayoutList, ArrowRightLeft, Clock } from 'lucide-vue-next';
    import ModalMotivoPerda from '@/Components/ModalMotivoPerda.vue';
    import { useContador } from '@/Composables/useContador';
    import { vMaska } from 'maska/vue';
    import { MASCARA_TELEFONE } from '@/utils/telefone';

    const user = computed(() => usePage().props.auth.user);

    // ── Board state ──
    const estagios = ref([]);
    const leads   = ref([]);
    const isLoading = ref(false);

    // ── Funis ──
    // O quadro mostra um funil por vez. `funilId` null significa "o padrão do
    // tenant", que é o backend quem resolve — o cliente não deve adivinhar.
    const funis      = ref([]);
    const funilAtual = ref(null);
    const funilId    = ref(null);

    // ── Perda ──
    // Um estado só para os três gatilhos que podem virar perda no quadro:
    // arrastar para uma coluna perdida, cadastrar direto nela e mover de funil
    // para um estágio perdido. Cada um informa como enviar e como desfazer; o
    // modal não conhece nenhum deles.
    const perdaPendente = ref(null);   // { titulo, enviar(perda), desfazer() }
    const perdaErro     = ref('');
    const perdaSalvando = ref(false);

    const ehPerdido = (estagio) => estagio?.tipo === 'perdido';

    const abrirPerda = (acao) => {
        perdaPendente.value = acao;
        perdaErro.value = '';
    };

    const cancelarPerda = () => {
        // Desfaz o movimento otimista: o card volta para a coluna de origem.
        perdaPendente.value?.desfazer?.();
        perdaPendente.value = null;
        perdaErro.value = '';
    };

    const confirmarPerda = async (perda) => {
        perdaSalvando.value = true;
        perdaErro.value = '';
        try {
            await perdaPendente.value.enviar(perda);
            perdaPendente.value = null;
            await buscarKanban();
        } catch (e) {
            perdaErro.value = e?.response?.data?.erros?.motivo_perda_id?.[0]
                ?? e?.response?.data?.error
                ?? 'Não foi possível registrar a perda.';
        } finally {
            perdaSalvando.value = false;
        }
    };

    // ── Mover de funil ──
    const moverAlvo       = ref(null);   // lead escolhido
    const moverFunilId    = ref(null);
    const moverEstagioId  = ref(null);
    const moverEstagios   = ref([]);
    const movendo         = ref(false);
    const moverErro       = ref('');

    // ── DnD state ──
    const draggingLead  = ref(null);
    const dragOverEstagioId = ref(null);

    // ── Quick-add ──
    const quickAddEstagioId = ref(null);
    const quickAddForm  = ref({ nome: '', email: '', telefone: '' });
    const quickAdding   = ref(false);

    // ── Settings ──
    const showSettings   = ref(false);
    const defaultEstagioId   = ref(null);
    const savingSettings = ref(false);

    // ── Paleta ──
    // Antes era um mapa fixo de id 1..6, o que só funcionava enquanto o conjunto
    // de estágios era o mesmo para todos os tenants. Com estágios criados por
    // cada empresa, a cor é um atributo do próprio estágio; ids não significam
    // nada entre tenants. O cinza é o fallback de quem ainda não escolheu cor.
    const hexParaRgba = (hex, alpha) => {
        const n = parseInt((hex || '').replace('#', ''), 16);
        if (Number.isNaN(n)) return `rgba(148,163,184,${alpha})`;
        return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
    };

    const getPalette = (estagio) => {
        const cor = estagio?.cor || '#94a3b8';
        return { color: cor, bg: hexParaRgba(cor, 0.15), border: hexParaRgba(cor, 0.3) };
    };

    const columnLeads = (estagioId) => leads.value.filter(l => l.estagio_id === estagioId);

    // Estágios arquivados ainda aparecem no quadro enquanto seguram leads, mas
    // não são destino válido: não recebem lead novo nem servem de coluna padrão.
    const estagiosAtivos = computed(() => estagios.value.filter(t => !t.arquivada));

    // ── Fetch ──
    const buscarKanban = async () => {
        isLoading.value = true;
        try {
            const params = funilId.value ? { funil_id: funilId.value } : {};

            const res = await axios.get('/api/kanban', { params });
            estagios.value = res.data.estagios;
            leads.value = res.data.leads;
            funis.value = res.data.funis ?? [];
            funilAtual.value = res.data.funil;
            funilId.value = res.data.funil?.id ?? null;

            // A coluna padrão do usuário é de um funil específico. Ao trocar de
            // funil ela não existe mais aqui, e cair no primeiro estágio ativo
            // evita um select apontando para uma opção inexistente.
            const daPreferencia = estagios.value.find(e => e.id === user.value.kanban_default_estagio_id);
            defaultEstagioId.value = daPreferencia?.id
                ?? (estagios.value.find(e => !e.arquivada)?.id ?? null);
        } catch {
            estagios.value = [];
            leads.value = [];
        } finally {
            isLoading.value = false;
        }
    };

    const trocarFunil = async (id) => {
        funilId.value = id;
        await buscarKanban();
    };

    // ── Drag-and-drop ──
    const onDragStart = (evt, lead) => {
        draggingLead.value = lead;
        evt.dataTransfer.effectAllowed = 'move';
        evt.dataTransfer.setData('text/plain', lead.id);
    };

    const onDragEnd = () => {
        draggingLead.value  = null;
        dragOverEstagioId.value = null;
    };

    const onDragOver = (estagio) => {
        if (estagio.arquivada) return;
        dragOverEstagioId.value = estagio.id;
    };

    const onDrop = async (evt, estagio) => {
        evt.preventDefault();
        dragOverEstagioId.value = null;
        const lead = draggingLead.value;
        draggingLead.value = null;
        // Estágio arquivado é somente-saída: aceita perder leads, nunca recebê-los.
        if (!lead || estagio.arquivada || lead.estagio_id === estagio.id) return;

        const oldEstagioId = lead.estagio_id;
        lead.estagio_id = estagio.id;

        const enviar = (perda) => axios.patch(`/api/usuarios/${lead.id}/estagio`, {
            estagio_id: estagio.id,
            perda,
        });
        const desfazer = () => { lead.estagio_id = oldEstagioId; };

        // O card move na hora e o modal pergunta o motivo depois. Bloquear o
        // drop e exigir um menu seria mais fácil de implementar e trocaria o
        // gesto natural do quadro por um caminho escondido; cancelar desfaz,
        // que é o mesmo rollback já usado quando a API falha.
        if (ehPerdido(estagio)) {
            abrirPerda({ titulo: `Perder "${lead.nome}"`, enviar, desfazer });
            return;
        }

        try {
            await enviar(null);
        } catch {
            desfazer();
        }
    };

    // ── Quick-add ──
    const abrirQuickAdd = (estagioId) => {
        quickAddEstagioId.value = estagioId;
        quickAddForm.value  = { nome: '', email: '', telefone: '' };
    };

    const cancelarQuickAdd = () => { quickAddEstagioId.value = null; };

    const salvarQuickAdd = async () => {
        if (!quickAddForm.value.nome.trim() || !quickAddForm.value.email.trim()) return;

        const estagio = estagios.value.find(e => e.id === quickAddEstagioId.value);
        const payload = {
            nome:     quickAddForm.value.nome.trim(),
            email:    quickAddForm.value.email.trim(),
            telefone: quickAddForm.value.telefone.trim(),
            estagio_id: quickAddEstagioId.value,
            funil_id: funilAtual.value?.id ?? null,
            user_id:  user.value.id,
        };
        const enviar = (perda) => axios.post('/api/usuarios', { ...payload, perda });

        // Cadastrar um lead direto numa coluna perdida é perder: o quick-add
        // usa a coluna clicada como estágio, e ela pode ser uma delas.
        if (ehPerdido(estagio)) {
            const nome = payload.nome;
            quickAddEstagioId.value = null;
            abrirPerda({ titulo: `Cadastrar "${nome}" como perdido`, enviar, desfazer: () => {} });
            return;
        }

        quickAdding.value = true;
        try {
            await enviar(null);
            quickAddEstagioId.value = null;
            await buscarKanban();
        } catch { /* silencioso */ }
        finally { quickAdding.value = false; }
    };

    // ── Mover de funil ──
    // Transição manual e explícita: escolher o funil de destino e o estágio de
    // entrada. Arrastar o card continua movendo só dentro do quadro atual — o
    // backend recusa um estagio_id de outro funil no endpoint de arrastar.
    const abrirMover = (lead) => {
        moverAlvo.value = lead;
        moverErro.value = '';
        moverFunilId.value = funis.value.find(f => f.id !== funilAtual.value?.id)?.id ?? null;
        moverEstagioId.value = null;
        moverEstagios.value = [];
        if (moverFunilId.value) carregarEstagiosDestino();
    };

    const fecharMover = () => { moverAlvo.value = null; };

    const carregarEstagiosDestino = async () => {
        moverEstagioId.value = null;
        moverEstagios.value = [];
        if (!moverFunilId.value) return;
        try {
            const res = await axios.get('/api/estagios', { params: { funil_id: moverFunilId.value } });
            moverEstagios.value = res.data;
            moverEstagioId.value = res.data.find(e => e.tipo === 'aberto')?.id ?? res.data[0]?.id ?? null;
        } catch {
            moverErro.value = 'Não foi possível carregar os estágios do funil de destino.';
        }
    };

    const confirmarMover = async () => {
        if (!moverFunilId.value || !moverEstagioId.value) return;

        const destino = moverEstagios.value.find(e => e.id === moverEstagioId.value);
        const enviar = (perda) => axios.patch(`/api/usuarios/${moverAlvo.value.id}/funil`, {
            funil_id: moverFunilId.value,
            estagio_id: moverEstagioId.value,
            perda,
        });

        // Mover para a coluna "Perdido" de outro funil é uma perda como
        // qualquer outra — sem isto, trocar de funil seria o desvio que
        // esvazia a obrigatoriedade.
        if (ehPerdido(destino)) {
            const nome = moverAlvo.value.nome;
            fecharMover();
            abrirPerda({ titulo: `Perder "${nome}"`, enviar, desfazer: () => {} });
            return;
        }

        movendo.value = true;
        moverErro.value = '';
        try {
            await enviar(null);
            fecharMover();
            await buscarKanban();
        } catch (e) {
            moverErro.value = e?.response?.data?.error
                ?? Object.values(e?.response?.data?.erros ?? {}).flat()[0]
                ?? 'Não foi possível mover o lead.';
        } finally {
            movendo.value = false;
        }
    };

    // ── Navegação para perfil ──
    const irParaLead = (id) => {
        router.visit(route('leads.show', id));
    };

    // ── Settings: coluna padrão ──
    const salvarSettings = async () => {
        savingSettings.value = true;
        try {
            await axios.patch('/api/kanban/settings', {
                default_estagio_id: defaultEstagioId.value,
            });
            showSettings.value = false;
        } catch { /* silencioso */ }
        finally { savingSettings.value = false; }
    };

    // ── Formatação ──
    const formatBRL = (v) =>
        new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

    const formatRelativo = (ts) => {
        if (!ts) return null;
        const diff = Math.floor((Date.now() - new Date(ts).getTime()) / 86400000);
        if (diff === 0) return 'hoje';
        if (diff === 1) return 'ontem';
        if (diff < 30)  return `há ${diff} dias`;
        if (diff < 365) return `há ${Math.floor(diff / 30)} meses`;
        return `há ${Math.floor(diff / 365)} ano(s)`;
    };

    // ── Números do quadro ──────────────────────────────────────
    // Somados no cliente a partir dos leads que já vieram: pedir ao servidor
    // um total que está na mão seria uma consulta a mais para o mesmo número.
    const totalLeads = computed(() => leads.value.length);
    const valorAberto = computed(() =>
        leads.value.reduce((soma, l) => soma + (l.valor_projetos || 0), 0)
    );

    // Contam até o novo valor quando ele muda — nunca na primeira carga.
    const leadsExibidos = useContador(() => totalLeads.value);
    const valorExibido  = useContador(() => valorAberto.value);

    const valorDaColuna = (estagioId) =>
        columnLeads(estagioId).reduce((soma, l) => soma + (l.valor_projetos || 0), 0);

    // ── Idade do lead ──────────────────────────────────────────
    // Quantos dias desde o último contato. Vira sinal âmbar depois de duas
    // semanas: é como o olho encontra o lead esquecido sem ler card por card.
    const DIAS_PARADO = 14;

    const diasDesde = (ts) => {
        if (!ts) return null;
        return Math.floor((Date.now() - new Date(ts).getTime()) / 86400000);
    };
    const idadeDe = (lead) => diasDesde(lead.ultimo_contato ?? lead.updated_at);
    const idadeRotulo = (lead) => {
        const d = idadeDe(lead);
        if (d === null) return null;
        return d === 0 ? 'hoje' : `${d}d`;
    };
    const estaParado = (lead) => (idadeDe(lead) ?? 0) >= DIAS_PARADO;

    // Iniciais de nome e sobrenome. Cortar as duas primeiras letras do nome
    // dava "DI" para Diego e "MA" para Marina — parece ruído, não identidade.
    const iniciais = (nome) =>
        (nome || '').trim().split(/\s+/).slice(0, 2).map(p => p[0]).join('').toUpperCase() || '?';

    // Entrada escalonada: teto de 8 para a última coluna não esperar a fila
    // inteira. Depois disso tudo entra junto.
    const atraso = (i) => Math.min(i, 8);

    onMounted(buscarKanban);
</script>

<template>
    <Head title="Pipeline — UserFlow" />
    <AuthenticatedLayout>
        <div class="kb">

            <header class="kb-topo">
                <h1 class="kb-h1">Pipeline</h1>

                <div v-if="funis.length > 1" class="kb-funil">
                    <select
                        class="kb-funil-sel"
                        :value="funilId"
                        aria-label="Funil exibido no quadro"
                        @change="trocarFunil(Number($event.target.value))"
                    >
                        <option v-for="f in funis" :key="f.id" :value="f.id">{{ f.nome }}</option>
                    </select>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 9l-7 7-7-7"/></svg>
                </div>
                <span v-else-if="funilAtual" class="kb-funil-fixo">{{ funilAtual.nome }}</span>

                <!-- Números como tipografia, não como quatro cartões: no quadro,
                     altura economizada aqui é card de lead visível sem rolar. -->
                <div class="kb-nums">
                    <div class="kb-num">
                        <b class="num">{{ Math.round(leadsExibidos) }}</b>
                        <span>{{ totalLeads === 1 ? 'lead' : 'leads' }}</span>
                    </div>
                    <div class="kb-num">
                        <b class="num">{{ formatBRL(valorExibido) }}</b>
                        <span>em aberto</span>
                    </div>
                </div>

                <Link :href="route('dashboard')" class="kb-btn-ghost">
                    <LayoutList :size="14" /> Lista
                </Link>
            </header>

            <ModalMotivoPerda
                :aberto="!!perdaPendente"
                :titulo="perdaPendente?.titulo ?? 'Registrar perda'"
                :erro="perdaErro"
                :salvando="perdaSalvando"
                @confirmar="confirmarPerda"
                @cancelar="cancelarPerda"
            />

            <Transition name="kb-fade">
                <div v-if="showSettings" class="kb-overlay" @click.self="showSettings = false">
                    <div class="kb-dialogo">
                        <header class="kb-dialogo-h">
                            <span>Configurações do quadro</span>
                            <button class="kb-x" aria-label="Fechar" @click="showSettings = false"><X :size="15" /></button>
                        </header>
                        <div class="kb-dialogo-b">
                            <label class="kb-label">Coluna padrão para novos leads</label>
                            <select v-model="defaultEstagioId" class="kb-input">
                                <option v-for="e in estagiosAtivos" :key="e.id" :value="e.id">{{ e.descricao }}</option>
                            </select>
                        </div>
                        <footer class="kb-dialogo-f">
                            <button class="kb-btn-ghost" @click="showSettings = false"><X :size="12" /> Cancelar</button>
                            <button class="kb-btn" :disabled="savingSettings" @click="salvarSettings">
                                <Save :size="12" /> {{ savingSettings ? 'Salvando…' : 'Salvar' }}
                            </button>
                        </footer>
                    </div>
                </div>
            </Transition>

            <Transition name="kb-fade">
                <div v-if="moverAlvo" class="kb-overlay" @click.self="fecharMover">
                    <div class="kb-dialogo">
                        <header class="kb-dialogo-h">
                            <span>Mover “{{ moverAlvo.nome }}”</span>
                            <button class="kb-x" aria-label="Fechar" @click="fecharMover"><X :size="15" /></button>
                        </header>
                        <div class="kb-dialogo-b">
                            <label class="kb-label">Funil de destino</label>
                            <select v-model.number="moverFunilId" class="kb-input" @change="carregarEstagiosDestino">
                                <option v-for="f in funis.filter(f => f.id !== funilAtual?.id)" :key="f.id" :value="f.id">{{ f.nome }}</option>
                            </select>

                            <label class="kb-label kb-label--mt">Estágio de entrada</label>
                            <select v-model.number="moverEstagioId" class="kb-input" :disabled="!moverEstagios.length">
                                <option v-for="e in moverEstagios" :key="e.id" :value="e.id">{{ e.descricao }}</option>
                            </select>

                            <p v-if="moverErro" class="kb-erro">{{ moverErro }}</p>
                        </div>
                        <footer class="kb-dialogo-f">
                            <button class="kb-btn-ghost" @click="fecharMover"><X :size="12" /> Cancelar</button>
                            <button class="kb-btn" :disabled="movendo || !moverEstagioId" @click="confirmarMover">
                                <ArrowRightLeft :size="12" /> {{ movendo ? 'Movendo…' : 'Mover' }}
                            </button>
                        </footer>
                    </div>
                </div>
            </Transition>

            <div v-if="isLoading" class="kb-esqueleto">
                <div v-for="n in 4" :key="n" class="kb-col kb-col--fantasma">
                    <div class="kb-sk kb-sk--h"></div>
                    <div class="kb-sk" v-for="m in 3" :key="m"></div>
                </div>
            </div>

            <div v-else-if="!estagios.length" class="kb-vazio">
                Este funil ainda não tem estágios.
                <Link v-if="$page.props.auth.permissions?.['configuracoes.manage']" :href="route('configuracoes.funis')" class="kb-link">Configurar funis</Link>
            </div>

            <div v-else class="kb-quadro">
                <section
                    v-for="estagio in estagios"
                    :key="estagio.id"
                    class="kb-col"
                    :class="{ 'is-alvo': dragOverEstagioId === estagio.id, 'is-arquivada': estagio.arquivada }"
                    :style="{ '--cor': estagio.cor || 'var(--fg-2)' }"
                    @dragover.prevent="onDragOver(estagio)"
                    @dragleave.self="dragOverEstagioId = null"
                    @drop="onDrop($event, estagio)"
                >
                    <header class="kb-col-h">
                        <div class="kb-col-t">
                            <span class="kb-regua" aria-hidden="true"></span>
                            <span class="kb-col-n">{{ estagio.descricao }}</span>
                            <span v-if="estagio.arquivada" class="kb-tag" title="Estágio arquivado: só saída. Some do quadro quando esvaziar.">arquivado</span>
                            <span class="kb-col-c num">{{ columnLeads(estagio.id).length }}</span>
                        </div>
                        <div v-if="valorDaColuna(estagio.id) > 0" class="kb-col-v num">{{ formatBRL(valorDaColuna(estagio.id)) }}</div>
                    </header>

                    <div class="kb-cards">
                        <Transition name="kb-ghost">
                            <div v-if="dragOverEstagioId === estagio.id && !estagio.arquivada" class="kb-alvo">Soltar aqui</div>
                        </Transition>

                        <Transition name="kb-slide">
                            <div v-if="quickAddEstagioId === estagio.id" class="kb-rapido">
                                <input v-model="quickAddForm.nome" class="kb-input" placeholder="Nome *" autofocus @keydown.escape="cancelarQuickAdd" />
                                <input v-model="quickAddForm.email" type="email" class="kb-input" placeholder="E-mail *" @keydown.escape="cancelarQuickAdd" />
                                <input v-model="quickAddForm.telefone" class="kb-input" v-maska :data-maska="MASCARA_TELEFONE" placeholder="Telefone" @keydown.escape="cancelarQuickAdd" @keydown.enter="salvarQuickAdd" />
                                <div class="kb-rapido-f">
                                    <button class="kb-btn-ghost kb-btn--sm" @click="cancelarQuickAdd">Cancelar</button>
                                    <button class="kb-btn kb-btn--sm" :disabled="quickAdding" @click="salvarQuickAdd">
                                        {{ quickAdding ? 'Salvando…' : 'Adicionar' }}
                                    </button>
                                </div>
                            </div>
                        </Transition>

                        <article
                            v-for="(lead, i) in columnLeads(estagio.id)"
                            :key="lead.id"
                            class="kb-card"
                            :class="{ 'is-arrastando': draggingLead?.id === lead.id }"
                            :style="{ '--i': atraso(i) }"
                            draggable="true"
                            tabindex="0"
                            @dragstart="onDragStart($event, lead)"
                            @dragend="onDragEnd"
                            @click="irParaLead(lead.id)"
                            @keydown.enter="irParaLead(lead.id)"
                        >
                            <div class="kb-card-t">
                                <span class="kb-ini" aria-hidden="true">{{ iniciais(lead.nome) }}</span>
                                <div class="kb-card-id">
                                    <p class="kb-card-n">{{ lead.nome }}</p>
                                    <p class="kb-card-e">{{ lead.email }}</p>
                                </div>
                                <button
                                    v-if="funis.length > 1"
                                    class="kb-mover"
                                    title="Mover para outro funil"
                                    aria-label="Mover para outro funil"
                                    @click.stop="abrirMover(lead)"
                                >
                                    <ArrowRightLeft :size="12" />
                                </button>
                            </div>
                            <div class="kb-card-b">
                                <span v-if="idadeRotulo(lead)" class="kb-idade" :class="{ 'is-parado': estaParado(lead) }"
                                      :title="estaParado(lead) ? 'Sem contato há mais de duas semanas' : 'Último contato'">
                                    <Clock :size="11" /> {{ idadeRotulo(lead) }}
                                </span>
                                <span v-if="lead.valor_projetos > 0" class="kb-valor num">{{ formatBRL(lead.valor_projetos) }}</span>
                            </div>
                        </article>

                        <p v-if="!columnLeads(estagio.id).length && dragOverEstagioId !== estagio.id" class="kb-col-vazia">
                            Nenhum lead
                        </p>
                    </div>

                    <button v-if="!estagio.arquivada" class="kb-add" @click="abrirQuickAdd(estagio.id)">
                        <Plus :size="12" /> Adicionar
                    </button>
                </section>

                <button class="kb-cfg" title="Configurações do quadro" aria-label="Configurações do quadro" @click="showSettings = true">
                    <Settings :size="15" />
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
    .kb { display: flex; flex-direction: column; min-height: 100vh; }

    /* ── Topo ──────────────────────────────────────── */
    .kb-topo {
        display: flex; align-items: center; gap: var(--s-3);
        padding: var(--s-3) var(--s-5); border-bottom: 1px solid var(--line);
        flex-wrap: wrap;
    }
    .kb-h1 { font-family: var(--font-display); font-size: var(--fs-xl); font-weight: 700; letter-spacing: -.3px; }

    .kb-funil { position: relative; display: flex; align-items: center; }
    .kb-funil svg { width: 13px; height: 13px; color: var(--fg-2); position: absolute; right: 9px; pointer-events: none; }
    .kb-funil-sel {
        appearance: none; background: var(--bg-1); border: 1px solid var(--line);
        border-radius: var(--r-2); padding: 5px 28px 5px 10px; color: var(--fg-0);
        font: inherit; font-size: var(--fs-md); cursor: pointer;
        transition: border-color var(--d-1) var(--e);
    }
    .kb-funil-sel:hover { border-color: var(--line-2); }
    .kb-funil-fixo { font-size: var(--fs-md); color: var(--fg-1); }

    .kb-nums { margin-left: auto; display: flex; align-items: center; gap: var(--s-5); }
    .kb-num { text-align: right; line-height: 1.15; }
    .kb-num b { display: block; font-size: var(--fs-lg); font-weight: 600; }
    .kb-num span { display: block; font-size: var(--fs-xs); color: var(--fg-2); }

    /* ── Quadro ────────────────────────────────────── */
    .kb-quadro, .kb-esqueleto {
        flex: 1; display: flex; gap: 11px; padding: var(--s-4) var(--s-5);
        overflow-x: auto; align-items: flex-start;
    }

    .kb-col {
        width: 258px; flex-shrink: 0; display: flex; flex-direction: column;
        background: var(--bg-1); border: 1px solid var(--line); border-radius: var(--r-3);
        max-height: calc(100vh - 118px);
        transition: border-color var(--d-2) var(--e), background var(--d-2) var(--e);
    }
    .kb-col.is-arquivada { opacity: .72; border-style: dashed; }

    /* A coluna de destino se anuncia: é como você vê para onde o card vai. */
    .kb-col.is-alvo {
        border-color: var(--accent);
        background: linear-gradient(var(--accent-dim), var(--accent-dim)), var(--bg-1);
    }

    .kb-col-h { padding: 11px 12px 9px; border-bottom: 1px solid var(--line); }
    .kb-col-t { display: flex; align-items: center; gap: 7px; }

    /* Identidade do estágio é um filete na cor dele, não a coluna tingida:
       assim a cor marca sem roubar contraste do que é clicável. */
    .kb-regua { width: 3px; height: 13px; border-radius: 2px; background: var(--cor); flex-shrink: 0; }

    .kb-col-n { font-size: 12.5px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .kb-col-c { margin-left: auto; font-size: var(--fs-sm); color: var(--fg-2); }
    .kb-col-v { font-size: var(--fs-sm); color: var(--fg-2); margin-top: 5px; padding-left: 10px; }
    .kb-tag {
        font-size: 9.5px; color: var(--fg-2); border: 1px solid var(--line-2);
        border-radius: var(--r-full); padding: 1px 6px; flex-shrink: 0;
    }

    .kb-cards { padding: var(--s-2); display: flex; flex-direction: column; gap: 7px; overflow-y: auto; }

    /* ── Card ──────────────────────────────────────── */
    .kb-card {
        position: relative; background: var(--bg-2); border: 1px solid var(--line);
        border-radius: var(--r-2); padding: 9px 10px; cursor: grab;
        transition: border-color var(--d-1) var(--e), transform var(--d-1) var(--e);
        animation: kb-entra var(--d-2) var(--e) backwards;
        animation-delay: calc(var(--i, 0) * 20ms);
    }
    /* Fio de luz no topo: o detalhe que o olho registra sem saber por quê. */
    .kb-card::before {
        content: ""; position: absolute; inset: 0 0 auto; height: 1px;
        border-radius: var(--r-2) var(--r-2) 0 0;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.07), transparent);
    }
    .kb-card:hover { border-color: var(--line-2); transform: translateY(-1px); }
    .kb-card.is-arrastando { opacity: .45; transform: rotate(-1.2deg) scale(.99); cursor: grabbing; }

    @keyframes kb-entra { from { opacity: 0; transform: translateY(4px); } }

    .kb-card-t { display: flex; align-items: center; gap: var(--s-2); }
    .kb-ini {
        width: 24px; height: 24px; border-radius: var(--r-1); flex-shrink: 0;
        background: var(--bg-0); border: 1px solid var(--line);
        display: grid; place-items: center; font-size: 10.5px; font-weight: 600; color: var(--fg-1);
    }
    .kb-card-id { min-width: 0; flex: 1; }
    .kb-card-n { font-size: var(--fs-md); font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .kb-card-e { font-size: var(--fs-sm); color: var(--fg-2); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .kb-mover {
        width: 22px; height: 22px; flex-shrink: 0; border-radius: var(--r-1);
        border: 1px solid var(--line); background: transparent; color: var(--fg-2);
        display: grid; place-items: center; cursor: pointer; opacity: 0;
        transition: opacity var(--d-1) var(--e), color var(--d-1) var(--e), border-color var(--d-1) var(--e);
    }
    /* Ação secundária só aparece quando o card é o foco da atenção. */
    .kb-card:hover .kb-mover, .kb-card:focus-within .kb-mover { opacity: 1; }
    .kb-mover:hover { color: var(--fg-0); border-color: var(--accent); }

    .kb-card-b {
        display: flex; align-items: center; gap: var(--s-2);
        margin-top: var(--s-2); padding-top: 7px; border-top: 1px solid var(--line);
    }
    .kb-idade { display: flex; align-items: center; gap: 4px; font-size: var(--fs-xs); color: var(--fg-2); }
    /* Sinal, não enfeite: encontra o lead esquecido sem ler card por card. */
    .kb-idade.is-parado { color: var(--warn); }
    .kb-valor { margin-left: auto; font-size: var(--fs-sm); font-weight: 600; color: var(--fg-1); }

    .kb-col-vazia { font-size: var(--fs-sm); color: var(--fg-2); text-align: center; padding: var(--s-4) 0; }

    .kb-alvo {
        border: 1px dashed var(--accent); border-radius: var(--r-2); height: 58px;
        display: grid; place-items: center; font-size: var(--fs-sm); color: var(--accent-hi);
        background: color-mix(in srgb, var(--accent) 6%, transparent);
    }

    .kb-add {
        margin: 0 var(--s-2) var(--s-2); display: flex; align-items: center; justify-content: center; gap: 5px;
        background: transparent; border: 1px dashed var(--line-2); border-radius: var(--r-2);
        color: var(--fg-2); font: inherit; font-size: var(--fs-sm); padding: 6px; cursor: pointer;
        transition: color var(--d-1) var(--e), border-color var(--d-1) var(--e);
    }
    .kb-add:hover { color: var(--accent-hi); border-color: var(--accent); }

    .kb-rapido { display: flex; flex-direction: column; gap: 6px; padding: var(--s-2); background: var(--bg-0); border: 1px solid var(--line); border-radius: var(--r-2); }
    .kb-rapido-f { display: flex; gap: 5px; justify-content: flex-end; }

    .kb-cfg {
        width: 32px; height: 32px; flex-shrink: 0; border-radius: var(--r-2);
        border: 1px solid var(--line); background: var(--bg-1); color: var(--fg-2);
        display: grid; place-items: center; cursor: pointer; margin-top: 2px;
        transition: color var(--d-1) var(--e), border-color var(--d-1) var(--e);
    }
    .kb-cfg:hover { color: var(--fg-0); border-color: var(--line-2); }

    /* ── Controles ─────────────────────────────────── */
    .kb-input {
        width: 100%; background: var(--bg-0); border: 1px solid var(--line);
        border-radius: var(--r-2); padding: 7px 9px; color: var(--fg-0);
        font: inherit; font-size: var(--fs-md); outline: none;
        transition: border-color var(--d-1) var(--e);
    }
    .kb-input:focus { border-color: var(--accent); }
    .kb-label { display: block; font-size: 12px; color: var(--fg-1); margin-bottom: 5px; }
    .kb-label--mt { margin-top: var(--s-3); }

    .kb-btn, .kb-btn-ghost {
        display: inline-flex; align-items: center; gap: 5px; border-radius: var(--r-2);
        padding: 7px 12px; font: inherit; font-size: var(--fs-md); cursor: pointer;
        text-decoration: none; transition: background var(--d-1) var(--e), color var(--d-1) var(--e), border-color var(--d-1) var(--e);
    }
    .kb-btn { background: var(--accent); color: #fff; border: 0; }
    .kb-btn:hover { background: var(--accent-hi); }
    .kb-btn:disabled { opacity: .5; cursor: not-allowed; }
    .kb-btn-ghost { background: transparent; color: var(--fg-1); border: 1px solid var(--line); }
    .kb-btn-ghost:hover { color: var(--fg-0); border-color: var(--line-2); }
    .kb-btn--sm { padding: 5px 9px; font-size: var(--fs-sm); }

    .kb-erro {
        margin-top: var(--s-3); font-size: var(--fs-md); color: var(--danger);
        background: var(--danger-dim); border: 1px solid color-mix(in srgb, var(--danger) 35%, transparent);
        border-radius: var(--r-2); padding: 7px 9px;
    }

    /* ── Diálogos ──────────────────────────────────── */
    .kb-overlay {
        position: fixed; inset: 0; z-index: 80; background: rgba(0,0,0,.6);
        backdrop-filter: blur(4px); display: grid; place-items: center; padding: var(--s-4);
    }
    .kb-dialogo { width: 100%; max-width: 400px; background: var(--bg-1); border: 1px solid var(--line); border-radius: var(--r-3); overflow: hidden; }
    .kb-dialogo-h { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-bottom: 1px solid var(--line); font-family: var(--font-display); font-weight: 700; font-size: 14px; }
    .kb-dialogo-b { padding: 14px; }
    .kb-dialogo-f { display: flex; justify-content: flex-end; gap: 6px; padding: 12px 14px; border-top: 1px solid var(--line); }
    .kb-x { background: 0; border: 0; color: var(--fg-2); cursor: pointer; display: flex; }
    .kb-x:hover { color: var(--fg-0); }

    /* ── Esqueleto ─────────────────────────────────── */
    .kb-col--fantasma { padding: var(--s-2); gap: 7px; }
    .kb-sk { height: 58px; border-radius: var(--r-2); background: var(--bg-2); animation: kb-pulsa 1.4s var(--e) infinite; }
    .kb-sk--h { height: 34px; }
    @keyframes kb-pulsa { 50% { opacity: .5; } }

    .kb-vazio { flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; color: var(--fg-1); font-size: var(--fs-md); padding: var(--s-10) 0; }
    .kb-link { color: var(--accent-hi); }

    .kb-fade-enter-active, .kb-fade-leave-active { transition: opacity var(--d-2) var(--e); }
    .kb-fade-enter-from, .kb-fade-leave-to { opacity: 0; }
    .kb-ghost-enter-active, .kb-ghost-leave-active { transition: opacity var(--d-1) var(--e), transform var(--d-1) var(--e); }
    .kb-ghost-enter-from, .kb-ghost-leave-to { opacity: 0; transform: scaleY(.8); }
    .kb-slide-enter-active, .kb-slide-leave-active { transition: opacity var(--d-2) var(--e), transform var(--d-2) var(--e); }
    .kb-slide-enter-from, .kb-slide-leave-to { opacity: 0; transform: translateY(-6px); }

    @media (max-width: 860px) {
        .kb-topo { padding: var(--s-3) var(--s-4); }
        .kb-quadro, .kb-esqueleto { padding: var(--s-3) var(--s-4); }
        .kb-nums { width: 100%; justify-content: flex-start; margin-left: 0; order: 9; }
    }
</style>
