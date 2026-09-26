<script setup>
    import { ref, watch, computed } from 'vue';
    import axios from 'axios';
    import { X, Check, Loader2 } from 'lucide-vue-next';

    /**
     * Pergunta o motivo antes de concluir uma perda.
     *
     * Três telas usam este modal — Kanban, Perfil e Projetos — porque há seis
     * caminhos que levam ao estado de perda e o backend recusa todos eles sem
     * motivo. Um modal por tela divergiria com o tempo, e a tela que
     * divergisse produziria um 422 que o usuário não conseguiria resolver.
     *
     * O componente não escreve nada: ele devolve `{ motivo_perda_id,
     * observacao }` em `confirmar` e quem chamou decide o que fazer. Isso
     * mantém o rollback (desfazer o card arrastado, por exemplo) com quem
     * conhece o estado que precisa ser desfeito.
     */
    const props = defineProps({
        aberto: { type: Boolean, default: false },
        titulo: { type: String, default: 'Registrar perda' },
        // Mensagem de erro vinda do backend, quando a confirmação falha.
        erro: { type: String, default: '' },
        salvando: { type: Boolean, default: false },
    });

    const emit = defineEmits(['confirmar', 'cancelar']);

    const motivos = ref([]);
    const carregando = ref(false);
    const motivoId = ref(null);
    const observacao = ref('');
    const erroLocal = ref('');

    const MAX_OBSERVACAO = 500;

    const restantes = computed(() => MAX_OBSERVACAO - (observacao.value?.length ?? 0));

    const buscarMotivos = async () => {
        carregando.value = true;
        erroLocal.value = '';
        try {
            const res = await axios.get('/api/motivos-perda');
            motivos.value = res.data;
            motivoId.value = res.data[0]?.id ?? null;
        } catch {
            erroLocal.value = 'Não foi possível carregar os motivos de perda.';
        } finally {
            carregando.value = false;
        }
    };

    // Recarrega a cada abertura: o catálogo pode ter mudado noutra aba, e um
    // motivo arquivado nesse meio-tempo seria recusado pelo backend com um erro
    // que a tela não sabe explicar.
    watch(() => props.aberto, (aberto) => {
        if (!aberto) return;
        observacao.value = '';
        erroLocal.value = '';
        buscarMotivos();
    });

    const confirmar = () => {
        if (!motivoId.value) {
            erroLocal.value = 'Escolha um motivo.';
            return;
        }
        emit('confirmar', {
            motivo_perda_id: motivoId.value,
            observacao: observacao.value.trim() || null,
        });
    };
</script>

<template>
    <Transition name="mp-fade">
        <div v-if="aberto" class="mp-overlay" @click.self="emit('cancelar')">
            <div class="mp-panel">
                <header class="mp-header">
                    <span class="mp-title">{{ titulo }}</span>
                    <button class="mp-close" @click="emit('cancelar')"><X :size="15" /></button>
                </header>

                <div class="mp-body">
                    <p class="mp-lead">
                        Registrar o motivo é o que permite responder depois por que a empresa perde.
                    </p>

                    <div v-if="carregando" class="mp-loading">
                        <Loader2 :size="16" class="mp-spin" /> Carregando motivos…
                    </div>

                    <template v-else>
                        <label class="mp-label">Motivo <span class="mp-req">*</span></label>
                        <select v-model="motivoId" class="mp-select">
                            <option v-for="m in motivos" :key="m.id" :value="m.id">{{ m.descricao }}</option>
                        </select>

                        <label class="mp-label mp-label--mt">Detalhe (opcional)</label>
                        <textarea
                            v-model="observacao"
                            class="mp-textarea"
                            rows="3"
                            :maxlength="MAX_OBSERVACAO"
                            placeholder="O que aconteceu, em uma frase"
                        />
                        <span class="mp-contador" :class="{ 'mp-contador--baixo': restantes < 50 }">
                            {{ restantes }} caracteres
                        </span>
                    </template>

                    <p v-if="erro || erroLocal" class="mp-erro">{{ erro || erroLocal }}</p>
                </div>

                <footer class="mp-footer">
                    <button class="mp-btn-ghost" @click="emit('cancelar')">
                        <X :size="12" /> Cancelar
                    </button>
                    <button class="mp-btn-primary" :disabled="salvando || carregando || !motivoId" @click="confirmar">
                        <Check :size="12" /> {{ salvando ? 'Registrando…' : 'Registrar perda' }}
                    </button>
                </footer>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
    .mp-overlay {
        position: fixed; inset: 0; z-index: 90;
        background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
        display: flex; align-items: center; justify-content: center; padding: 1rem;
        font-family: 'DM Sans', sans-serif;
    }

    .mp-panel {
        width: 100%; max-width: 420px;
        background: #13192a; border: 1px solid #1e2840; border-radius: 14px;
        color: #eaedf5; overflow: hidden;
    }

    .mp-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0.9rem 1.1rem; border-bottom: 1px solid #1e2840;
    }
    .mp-title { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 0.95rem; }
    .mp-close { background: none; border: none; color: #8892ab; cursor: pointer; display: flex; }
    .mp-close:hover { color: #eaedf5; }

    .mp-body { padding: 1.1rem; }
    .mp-lead { font-size: 0.78rem; color: #8892ab; margin-bottom: 0.9rem; line-height: 1.5; }

    .mp-label { display: block; font-size: 0.76rem; color: #8892ab; margin-bottom: 0.35rem; }
    .mp-label--mt { margin-top: 0.9rem; }
    .mp-req { color: #f06292; }

    .mp-select, .mp-textarea {
        width: 100%; background: rgba(13,17,23,0.7); border: 1px solid #1e2840;
        border-radius: 9px; padding: 0.55rem 0.7rem; color: #eaedf5;
        font-size: 0.85rem; font-family: inherit; outline: none; resize: vertical;
    }
    .mp-select:focus, .mp-textarea:focus { border-color: #6d5dfc; }

    .mp-contador { display: block; text-align: right; font-size: 0.7rem; color: #4a5470; margin-top: 0.25rem; }
    .mp-contador--baixo { color: #f59e0b; }

    .mp-erro {
        margin-top: 0.8rem; font-size: 0.78rem; color: #fca5a5;
        background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3);
        border-radius: 8px; padding: 0.5rem 0.65rem;
    }

    .mp-loading { display: flex; align-items: center; gap: 0.5rem; color: #8892ab; font-size: 0.85rem; padding: 1rem 0; }
    .mp-spin { animation: mp-rot 1s linear infinite; }
    @keyframes mp-rot { to { transform: rotate(360deg); } }

    .mp-footer {
        display: flex; justify-content: flex-end; gap: 0.5rem;
        padding: 0.85rem 1.1rem; border-top: 1px solid #1e2840;
    }

    .mp-btn-primary {
        display: inline-flex; align-items: center; gap: 0.4rem;
        background: #6d5dfc; color: #fff; border: none; border-radius: 9px;
        padding: 0.5rem 0.9rem; font-size: 0.82rem; font-family: inherit; cursor: pointer;
    }
    .mp-btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
    .mp-btn-ghost {
        display: inline-flex; align-items: center; gap: 0.4rem;
        background: transparent; color: #8892ab; border: 1px solid #1e2840;
        border-radius: 9px; padding: 0.5rem 0.9rem; font-size: 0.82rem; font-family: inherit; cursor: pointer;
    }

    .mp-fade-enter-active, .mp-fade-leave-active { transition: opacity .18s; }
    .mp-fade-enter-from, .mp-fade-leave-to { opacity: 0; }
</style>
