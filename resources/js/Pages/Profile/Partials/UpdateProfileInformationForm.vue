<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section class="pf-card">
        <h2 class="pf-card-titulo">Dados do perfil</h2>
        <p class="pf-card-desc">Seu nome e o e-mail que você usa para entrar no sistema.</p>

        <form class="pf-form" @submit.prevent="form.patch(route('profile.update'))">
            <div class="pf-campo">
                <label class="pf-label" for="name">Nome</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="pf-input"
                    required
                    autofocus
                    autocomplete="name"
                />
                <span v-if="form.errors.name" class="pf-erro">{{ form.errors.name }}</span>
            </div>

            <div class="pf-campo">
                <label class="pf-label" for="email">E-mail</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="pf-input"
                    required
                    autocomplete="username"
                />
                <span v-if="form.errors.email" class="pf-erro">{{ form.errors.email }}</span>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="pf-aviso">
                Seu e-mail ainda não foi verificado.
                <Link
                    :href="route('verification.send')"
                    method="post"
                    as="button"
                    class="pf-link"
                >
                    Reenviar o e-mail de verificação.
                </Link>
                <span v-if="status === 'verification-link-sent'" class="pf-ok">
                    Um novo link de verificação foi enviado.
                </span>
            </div>

            <div class="pf-acoes">
                <button type="submit" class="pf-btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Salvando…' : 'Salvar' }}
                </button>

                <Transition name="pf-fade">
                    <span v-if="form.recentlySuccessful" class="pf-ok">Salvo.</span>
                </Transition>
            </div>
        </form>
    </section>
</template>
