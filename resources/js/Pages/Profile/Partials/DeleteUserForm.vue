<script setup>
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

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
            Apagar a conta remove permanentemente todos os seus dados. Baixe antes o que
            quiser guardar — não há como desfazer.
        </p>

        <div class="pf-acoes" style="margin-top: 1.1rem">
            <button class="pf-btn-perigo" @click="confirmUserDeletion">Excluir conta</button>
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
                        Todos os seus dados são apagados permanentemente. Digite sua senha
                        para confirmar.
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
