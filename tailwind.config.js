import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // Tema escuro por classe `dark` no <html> (ver lib/theme.ts e o script do app.blade.php).
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // A escala de cinzas vem de variáveis CSS (resources/css/app.css): no tema escuro a escala se inverte,
            // então todas as telas existentes (text-gray-*, bg-gray-*, border-gray-*, ring-gray-*...) já acompanham.
            colors: {
                gray: Object.fromEntries(
                    [50, 100, 200, 300, 400, 500, 600, 700, 800, 900].map((shade) => [shade, `rgb(var(--gray-${shade}) / <alpha-value>)`]),
                ),
            },
        },
    },

    plugins: [forms],
};
