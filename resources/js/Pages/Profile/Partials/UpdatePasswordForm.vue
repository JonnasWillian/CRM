<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <section class="pf-card">
        <h2 class="pf-card-titulo">Senha</h2>
        <p class="pf-card-desc">
            Use uma senha longa e que você não use em outro lugar — ela é o que protege a
            carteira de leads da empresa.
        </p>

        <form class="pf-form" @submit.prevent="updatePassword">
            <div class="pf-campo">
                <label class="pf-label" for="current_password">Senha atual</label>
                <input
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="pf-input"
                    autocomplete="current-password"
                />
                <span v-if="form.errors.current_password" class="pf-erro">
                    {{ form.errors.current_password }}
                </span>
            </div>

            <div class="pf-campo">
                <label class="pf-label" for="password">Nova senha</label>
                <input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="pf-input"
                    autocomplete="new-password"
                />
                <span v-if="form.errors.password" class="pf-erro">{{ form.errors.password }}</span>
            </div>

            <div class="pf-campo">
                <label class="pf-label" for="password_confirmation">Confirmar nova senha</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="pf-input"
                    autocomplete="new-password"
                />
                <span v-if="form.errors.password_confirmation" class="pf-erro">
                    {{ form.errors.password_confirmation }}
                </span>
            </div>

            <div class="pf-acoes">
                <button type="submit" class="pf-btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Salvando…' : 'Salvar' }}
                </button>

                <Transition name="pf-fade">
                    <span v-if="form.recentlySuccessful" class="pf-ok">Senha atualizada.</span>
                </Transition>
            </div>
        </form>
    </section>
</template>
