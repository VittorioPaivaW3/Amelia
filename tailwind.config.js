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
                sans: ['"Josefin Sans"', ...defaultTheme.fontFamily.sans],
            },

    
            colors: {
                'verdes': {
                    verde_claro: '#8DC63F',
                    verde_folha: '#63BE15',
                    verde_escuro: '#00381B',
                    verde_bandeira: '#00843D',
                },

               'coloridos': {
                    vermelho: '#DC2626',
                    amarelo: '#FBBF24',
                    azul: '#3B82F6',

                },
            },

      
            keyframes: {
                camaleao: {
                    '0%, 30%': { backgroundColor: '#DC2626' }, 
                    '33%, 63%': { backgroundColor: '#FBBF24' }, 
                    '66%, 96%': { backgroundColor: '#3B82F6' },
                    '100%': { backgroundColor: '#DC2626' },
                }
            },
            animation: {
                camaleao: 'camaleao 10s ease-in-out infinite',
            }
        },
    },

    plugins: [forms],
};