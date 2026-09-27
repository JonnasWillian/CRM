import { ref, watch } from 'vue';

/**
 * Número que conta até o novo valor quando ele muda.
 *
 * A regra que importa: **anima só na mudança, nunca na primeira carga.** Um
 * número que sobe de zero toda vez que a tela abre é enfeite — atrasa a
 * leitura de quem só queria ver o total. Quando o valor muda com a tela já
 * aberta, a contagem diz "isto acabou de atualizar", que é informação.
 *
 * Respeita `prefers-reduced-motion`: para quem pediu menos movimento, o valor
 * simplesmente troca.
 *
 * @param {() => number} fonte  função que devolve o valor atual
 * @param {number} duracao      ms
 */
export function useContador(fonte, duracao = 400) {
    const exibido = ref(fonte() ?? 0);
    let anim = null;

    const semMovimento = () =>
        typeof window !== 'undefined' &&
        window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    watch(fonte, (novo, antigo) => {
        novo = novo ?? 0;

        // Primeira carga (antigo indefinido) ou movimento reduzido: sem animação.
        if (antigo === undefined || semMovimento() || novo === antigo) {
            exibido.value = novo;
            return;
        }

        cancelAnimationFrame(anim);
        const de = exibido.value;
        const delta = novo - de;
        const inicio = performance.now();

        const passo = (agora) => {
            const t = Math.min(1, (agora - inicio) / duracao);
            // easeOutCubic: chega rápido e desacelera, como a curva do resto do app
            exibido.value = de + delta * (1 - Math.pow(1 - t, 3));
            if (t < 1) anim = requestAnimationFrame(passo);
            else exibido.value = novo;
        };
        anim = requestAnimationFrame(passo);
    });

    return exibido;
}
