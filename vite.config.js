import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// Optional Breeze/Vite toolchain. Production UI loads Bootstrap assets from
// public/assets via Blade layouts — not @vite. Keep this for local experiments only.
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
});
