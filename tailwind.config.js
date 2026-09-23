import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                // Foundation & Surfaces (Dark Charcoal Cálido — modo oscuro sistema completo)
                background: '#120d0b',
                surface: '#120d0b',
                'surface-dim': '#0a0806',
                'surface-bright': '#261a15',
                'surface-container-lowest': '#1e1410',
                'surface-container-low': '#261a15',
                'surface-container': '#2e2018',
                'surface-container-high': '#38271f',
                'surface-container-highest': '#432f26',
                'surface-variant': '#432f26',
                'inverse-surface': '#f5e8e2',
                'inverse-on-surface': '#2e2018',
                scrim: '#000000',

                // Content & Typography (texto claro sobre fondos oscuros)
                'on-background': '#f5e8e2',
                'on-surface': '#f5e8e2',
                'on-surface-variant': '#c4a89e',
                outline: '#7a5a52',
                'outline-variant': '#432f26',

                // Primary: Terracota Brasa / Flame Crimson
                primary: '#e0442e',
                'primary-container': '#6b1a0e',
                'on-primary': '#ffffff',
                'on-primary-container': '#ffb4a6',
                'primary-fixed': '#5c1409',
                'primary-fixed-dim': '#8d1607',
                'on-primary-fixed': '#ffdad4',
                'on-primary-fixed-variant': '#ffb4a6',
                'inverse-primary': '#ab2d1b',
                'surface-tint': '#e0442e',

                // Secondary: Verde Palma / Deep Botanical Teal
                secondary: '#2eb8b4',
                'secondary-container': '#004d4a',
                'on-secondary': '#ffffff',
                'on-secondary-container': '#86d4d0',
                'secondary-fixed': '#005552',
                'secondary-fixed-dim': '#00736f',
                'on-secondary-fixed': '#a2f0ec',
                'on-secondary-fixed-variant': '#86d4d0',

                // Tertiary: Panela Dorada / Warm Amber
                tertiary: '#e8a020',
                'tertiary-container': '#4a2e00',
                'on-tertiary': '#2a1800',
                'on-tertiary-container': '#ffb959',
                'tertiary-fixed': '#4a2e00',
                'tertiary-fixed-dim': '#643f00',
                'on-tertiary-fixed': '#ffddb6',
                'on-tertiary-fixed-variant': '#ffb959',

                // Semantic Statuses
                error: '#ff6b6b',
                'error-container': '#5c0a0a',
                'on-error': '#ffffff',
                'on-error-container': '#ffb4b4',
                'status-free': '#2eb8b4',
                'status-occupied': '#e0442e',
                'status-warning': '#e8a020',
                'status-urgent': '#ff6b6b',
                'status-reserved': '#2eb8b4',
                'status-cleaning': '#c4a89e',

                // Compatibility aliases
                'border-subtle': '#432f26',
                'border-focus': '#ab2d1b',
                'surface-base': '#120d0b',
                'surface-card': '#1e1410',
                'surface-elevated': '#261a15',
            },
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"Plus Jakarta Sans"', 'sans-serif'],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            spacing: {
                'space-2xs': '0.25rem',
                'space-xs': '0.5rem',
                'space-sm': '0.75rem',
                'space-md': '1rem',
                'space-lg': '1.5rem',
                'space-xl': '2rem',
                'space-2xl': '2.5rem',
                'space-3xl': '3rem',
                'margin-screen': '1.5rem',
                'gutter-grid': '1rem',
                'touch-min': '48px',
                'touch-standard': '56px',
                'touch-comfortable': '64px',
                'touch-lg': '56px',
                'touch-xl': '64px',
            },
            keyframes: {
                'fade-in': {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
            },
            animation: {
                'fade-in': 'fade-in 0.2s ease-out forwards',
            },
        },
    },

    plugins: [forms],
};
