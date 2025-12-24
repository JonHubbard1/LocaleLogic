import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
        './vendor/livewire/flux/stubs/resources/views/**/*.blade.php',
        './vendor/livewire/flux-pro/stubs/resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {},
    },

    plugins: [forms, typography],
};
