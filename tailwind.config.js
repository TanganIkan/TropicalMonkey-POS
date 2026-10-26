/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class",
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/**/*.js",
    ],
    theme: {
        extend: {
            // Warna berbasis variabel CSS agar bisa dibalik di mode gelap (lihat app.css)
            colors: {
                primary: 'rgb(var(--ink) / <alpha-value>)',
                white: 'rgb(var(--white) / <alpha-value>)',
                snow: "#ffffff", // putih tetap (tidak ikut dibalik), mis. teks di tombol merah
                ink: {
                    DEFAULT: 'rgb(var(--ink) / <alpha-value>)',
                    soft: 'rgb(var(--ink-soft) / <alpha-value>)',
                },
                canvas: 'rgb(var(--canvas) / <alpha-value>)',
                zinc: { 50: 'rgb(var(--zinc-50) / <alpha-value>)', 100: 'rgb(var(--zinc-100) / <alpha-value>)', 200: 'rgb(var(--zinc-200) / <alpha-value>)', 300: 'rgb(var(--zinc-300) / <alpha-value>)', 400: 'rgb(var(--zinc-400) / <alpha-value>)', 500: 'rgb(var(--zinc-500) / <alpha-value>)', 600: 'rgb(var(--zinc-600) / <alpha-value>)', 700: 'rgb(var(--zinc-700) / <alpha-value>)', 800: 'rgb(var(--zinc-800) / <alpha-value>)', 900: 'rgb(var(--zinc-900) / <alpha-value>)', 950: 'rgb(var(--zinc-950) / <alpha-value>)' },
                red: { 50: 'rgb(var(--red-50) / <alpha-value>)', 100: 'rgb(var(--red-100) / <alpha-value>)', 200: 'rgb(var(--red-200) / <alpha-value>)', 300: 'rgb(var(--red-300) / <alpha-value>)', 400: 'rgb(var(--red-400) / <alpha-value>)', 500: 'rgb(var(--red-500) / <alpha-value>)', 600: 'rgb(var(--red-600) / <alpha-value>)', 700: 'rgb(var(--red-700) / <alpha-value>)' },
                emerald: { 50: 'rgb(var(--emerald-50) / <alpha-value>)', 100: 'rgb(var(--emerald-100) / <alpha-value>)', 500: 'rgb(var(--emerald-500) / <alpha-value>)', 600: 'rgb(var(--emerald-600) / <alpha-value>)', 700: 'rgb(var(--emerald-700) / <alpha-value>)' },
                amber: { 50: 'rgb(var(--amber-50) / <alpha-value>)', 100: 'rgb(var(--amber-100) / <alpha-value>)', 400: 'rgb(var(--amber-400) / <alpha-value>)', 600: 'rgb(var(--amber-600) / <alpha-value>)', 700: 'rgb(var(--amber-700) / <alpha-value>)', 800: 'rgb(var(--amber-800) / <alpha-value>)' },
            },
            fontFamily: {
                sans: ["Poppins", "ui-sans-serif", "system-ui", "-apple-system", "Segoe UI", "Roboto", "Helvetica Neue", "Arial", "sans-serif"],
                mono: ["ui-monospace", "SFMono-Regular", "Menlo", "Monaco", "Consolas", "monospace"],
            },
            borderRadius: {
                "4xl": "2rem",
            },
            boxShadow: {
                tile: "0 1px 2px rgba(10,10,10,0.04), 0 1px 1px rgba(10,10,10,0.02)",
                lift: "0 12px 32px -12px rgba(10,10,10,0.18)",
            },
        },
    },
    plugins: [],
};
