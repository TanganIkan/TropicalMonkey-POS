/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php", // Pastikan baris ini ada
        "./resources/**/*.js",
        "./resources/**/*.vue",
    ],
    theme: {
        extend: {
            colors: {
                primary: "#4F46E5",
                secondary: "#0EA5E9",
                tertiary: "#A54100",
                neutral: "#64748B",
            },
            fontFamily: {
                // Mengubah font default menjadi Poppins
                sans: [
                    "Poppins",
                    "ui-sans-serif",
                    "system-ui",
                    "-apple-system",
                    "BlinkMacSystemFont",
                    "Segoe UI",
                    "Roboto",
                    "Helvetica Neue",
                    "Arial",
                    "sans-serif",
                ],
            },
        },
    },
    plugins: [],
};
