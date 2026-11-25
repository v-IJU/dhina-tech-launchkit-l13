import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Admin panel: Bootstrap 5
                'resources/admin/admin.css',
                'resources/admin/admin.js',
                // Public website: Tailwind CSS
                'resources/web/web.css',
                'resources/web/web.js',
            ],
            refresh: ['resources/views/**', 'app/Modules/**/Views/**'],
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173, // inside the container; the browser uses VITE_PORT (compose.yaml)
        origin: `http://localhost:${process.env.VITE_PORT || 5173}`,
        hmr: { host: 'localhost', clientPort: Number(process.env.VITE_PORT || 5173) },
        watch: { ignored: ['**/storage/framework/views/**', '**/vendor/**'] },
    },
});
