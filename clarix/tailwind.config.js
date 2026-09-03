import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        // Livewire components hold class strings of their own (the category and
        // model tints), and those never reach a Blade file as literals.
        './app/Livewire/**/*.php',
        // The marketing nav config carries the dropdown panel widths, which are
        // arbitrary values and so exist nowhere else for Tailwind to find.
        './config/marketing.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
