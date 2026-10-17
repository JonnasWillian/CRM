import axios from 'axios';
import { useToast } from './Composables/useToast';
import { instalarInterceptor } from './utils/http';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Toda falha de API vira feedback ao usuário (ver utils/http.js). Sessão
// expirada manda para o login; o `route()` do Ziggy é global, injetado pelo
// @routes do blade.
instalarInterceptor(window.axios, {
    toast: useToast(),
    aoDeslogar: () => {
        window.location.href = typeof route === 'function' ? route('login') : '/login';
    },
});
