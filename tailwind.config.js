import forms from '@tailwindcss/forms';

/**
 * Utilitários mapeados para os tokens de resources/css/theme.css: o
 * utilitário serve layout e nunca inventa cor. `forms` em modo `class` só
 * reseta onde o AppInput pede (.form-input, .form-select, .form-checkbox).
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],

    theme: {
        screens: { sm: '640px', md: '768px', lg: '1024px', xl: '1280px' },
        extend: {
            colors: {
                bg: { 0: 'var(--bg-0)', 1: 'var(--bg-1)', 2: 'var(--bg-2)' },
                fg: { 0: 'var(--fg-0)', 1: 'var(--fg-1)', 2: 'var(--fg-2)', disabled: 'var(--fg-disabled)' },
                line: { DEFAULT: 'var(--line)', 2: 'var(--line-2)' },
                primary: {
                    DEFAULT: 'var(--primary)',
                    hover: 'var(--primary-hover)',
                    active: 'var(--primary-active)',
                    subtle: 'var(--primary-subtle)',
                    fg: 'var(--primary-fg)',
                },
                focus: 'var(--focus-ring)',
                ok: { DEFAULT: 'var(--ok)', subtle: 'var(--ok-subtle)', line: 'var(--ok-line)' },
                warn: { DEFAULT: 'var(--warn)', subtle: 'var(--warn-subtle)', accent: 'var(--warn-accent)', line: 'var(--warn-line)' },
                danger: { DEFAULT: 'var(--danger)', hover: 'var(--danger-hover)', subtle: 'var(--danger-subtle)', line: 'var(--danger-line)' },
                info: { DEFAULT: 'var(--info)', subtle: 'var(--info-subtle)', line: 'var(--info-line)' },
                cat: { 1: 'var(--cat-1)', 2: 'var(--cat-2)', 3: 'var(--cat-3)', 4: 'var(--cat-4)', neutral: 'var(--cat-neutral)' },
            },
            fontFamily: {
                sans: 'var(--font-body)',
                display: 'var(--font-display)',
                mono: 'var(--font-mono)',
            },
            fontSize: {
                xs: ['var(--fs-xs)', 'var(--lh-base)'],
                sm: ['var(--fs-sm)', 'var(--lh-base)'],
                md: ['var(--fs-md)', 'var(--lh-base)'],
                base: ['var(--fs-md)', 'var(--lh-base)'],
                lg: ['var(--fs-lg)', 'var(--lh-tight)'],
                xl: ['var(--fs-xl)', 'var(--lh-tight)'],
                '2xl': ['var(--fs-2xl)', 'var(--lh-tight)'],
                '3xl': ['var(--fs-3xl)', 'var(--lh-tight)'],
            },
            borderRadius: {
                sm: 'var(--r-1)',
                DEFAULT: 'var(--r-2)',
                md: 'var(--r-2)',
                lg: 'var(--r-3)',
                full: 'var(--r-full)',
            },
            boxShadow: {
                1: 'var(--sh-1)',
                2: 'var(--sh-2)',
                3: 'var(--sh-3)',
            },
            transitionTimingFunction: { ui: 'var(--e)' },
            transitionDuration: { 1: '120ms', 2: '180ms', 3: '260ms' },
            spacing: { page: 'var(--page-x)', sidebar: 'var(--sidebar-w)' },
        },
    },

    plugins: [forms({ strategy: 'class' })],
};
