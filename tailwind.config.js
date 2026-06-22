import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Poppins', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                teal: { DEFAULT: '#00B8A9', 700: '#00968A' },
                pine: { DEFAULT: '#003D3A', 600: '#0d5550', 800: '#004D49' },
                paper: '#FAFAF7',
                aiglow: { DEFAULT: '#A78BFA', 500: '#8B5CF6' },
                flag: { DEFAULT: '#FBBF24', 500: '#F59E0B' },
            },
        },
    },

    plugins: [forms],
};
