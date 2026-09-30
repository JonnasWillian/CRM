<script setup>
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const props = defineProps({
    impedimentos: {
        type: Array,
        default: () => [],
    },
});

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="pf-card pf-card--perigo">
        <h2 class="pf-card-titulo">Excluir conta</h2>
        <p class="pf-card-desc">
            Excluir a conta encerra o seu acesso. Os leads são da empresa e não saem com você:
            enquanto você for dono de algum, ou for o único admin, a conta não pode ser excluída.
        </p>

        <ul v-if="props.impedimentos.length" class="pf-aviso" style="margin-top: 0.9rem">
            <li v-for="motivo in props.impedimentos" :key="motivo">{{ motivo }}</li>
        </ul>

        <div class="pf-acoes" style="margin-top: 1.1rem">
            <button
                class="pf-btn-perigo"
                :disabled="props.impedimentos.length > 0"
                @click="confirmUserDeletion"
            >
                Excluir conta
            </button>
        </div>

        <!--
            Diálogo próprio em vez de Components/Modal.vue: aquele componente é
            o do Breeze, de tema claro, e também serve as telas de Auth — mudá-lo
            mexeria nelas. O overlay aqui segue o mesmo desenho de
            ModalMotivoPerda, que é o padrão de diálogo do app.
        -->
        <Transition name="pf-fade">
            <div v-if="confirmingUserDeletion" class="pf-overlay" @click.self="closeModal">
                <div class="pf-dialogo">
                    <h2 class="pf-card-titulo">Excluir sua conta?</h2>
                    <p class="pf-card-desc">
                        Seu acesso será encerrado. Digite sua senha para confirmar.
                    </p>

                    <div class="pf-campo" style="margin-top: 1.1rem">
                        <label class="pf-label" for="delete_password">Senha</label>
                        <input
                            id="delete_password"
                            ref="passwordInput"
                            v-model="form.password"
                            type="password"
                            class="pf-input"
                            placeholder="Sua senha atual"
                            @keyup.enter="deleteUser"
                        />
                        <span v-if="form.errors.password" class="pf-erro">
                            {{ form.errors.password }}
                        </span>
                        <span v-if="form.errors.conta" class="pf-erro">
                            {{ form.errors.conta }}
                        </span>
                    </div>

                    <div class="pf-dialogo-acoes">
                        <button class="pf-btn-ghost" @click="closeModal">Cancelar</button>
                        <button
                            class="pf-btn-perigo pf-btn-perigo--solido"
                            :disabled="form.processing"
                            @click="deleteUser"
                        >
                            {{ form.processing ? 'Excluindo…' : 'Excluir conta' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </section>
</template>
