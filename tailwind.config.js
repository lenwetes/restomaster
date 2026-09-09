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
                // Foundation & Surfaces (Luminous Warm Porcelain)
                background: '#faf8ff',
                surface: '#faf8ff',
                'surface-dim': '#d9d9e4',
                'surface-bright': '#faf8ff',
                'surface-container-lowest': '#ffffff',
                'surface-container-low': '#f3f3fe',
                'surface-container': '#ededf8',
                'surface-container-high': '#e7e7f3',
                'surface-container-highest': '#e1e1ed',
                'surface-variant': '#e1e1ed',
                'inverse-surface': '#2e3039',
                'inverse-on-surface': '#f0f0fb',

                // Content & Typography
                'on-background': '#191b23',
                'on-surface': '#191b23',
                'on-surface-variant': '#59413d',
                outline: '#8d706b',
                'outline-variant': '#e1bfb9',

                // Primary: Terracota Brasa / Flame Crimson
                primary: '#ab2d1b',
                'primary-container': '#cd4630',
                'on-primary': '#ffffff',
                'on-primary-container': '#fffbff',
                'primary-fixed': '#ffdad4',
                'primary-fixed-dim': '#ffb4a6',
                'on-primary-fixed': '#3f0300',
                'on-primary-fixed-variant': '#8d1607',
                'inverse-primary': '#ffb4a6',
                'surface-tint': '#af301d',

                // Secondary: Verde Palma / Deep Botanical Teal
                secondary: '#086a67',
                'secondary-container': '#9feee9',
                'on-secondary': '#ffffff',
                'on-secondary-container': '#136e6b',
                'secondary-fixed': '#a2f0ec',
                'secondary-fixed-dim': '#86d4d0',
                'on-secondary-fixed': '#00201f',
                'on-secondary-fixed-variant': '#00504d',

                // Tertiary: Panela Dorada / Warm Amber
                tertiary: '#815200',
                'tertiary-container': '#a26800',
                'on-tertiary': '#ffffff',
                'on-tertiary-container': '#fffbff',
                'tertiary-fixed': '#ffddb6',
                'tertiary-fixed-dim': '#ffb959',
                'on-tertiary-fixed': '#2a1800',
                'on-tertiary-fixed-variant': '#643f00',

                // Semantic Statuses
                error: '#ba1a1a',
                'error-container': '#ffdad6',
                'on-error': '#ffffff',
                'on-error-container': '#93000a',
                'status-free': '#086a67',
                'status-occupied': '#ab2d1b',
                'status-warning': '#815200',
                'status-urgent': '#ba1a1a',
                'status-reserved': '#086a67',
                'status-cleaning': '#59413d',

                // Compatibility aliases
                'border-subtle': '#e1e1ed',
                'border-focus': '#ab2d1b',
                'surface-base': '#faf8ff',
                'surface-card': '#ffffff',
                'surface-elevated': '#f3f3fe',
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
        },
    },

    plugins: [forms],
};
