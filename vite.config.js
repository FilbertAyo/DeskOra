import path from 'path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) {
                        return;
                    }

                    if (id.includes('recharts') || id.includes('victory-vendor') || id.includes('d3-')) {
                        return 'charts';
                    }

                    if (
                        id.includes(`${path.sep}react${path.sep}`) ||
                        id.includes(`${path.sep}react-dom${path.sep}`) ||
                        id.includes('@inertiajs')
                    ) {
                        return 'react-vendor';
                    }

                    if (id.includes('lucide-react')) {
                        return 'icons';
                    }

                    if (
                        id.includes('@base-ui') ||
                        id.includes('@tanstack') ||
                        id.includes('cmdk') ||
                        id.includes('sonner') ||
                        id.includes('next-themes')
                    ) {
                        return 'ui-vendor';
                    }

                    return 'vendor';
                },
            },
        },
    },
});
