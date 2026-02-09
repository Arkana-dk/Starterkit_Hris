import { spawnSync } from 'node:child_process';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig, type PluginOption } from 'vite';

const hasPhpBinary = () => {
    const probe = spawnSync('php', ['-v'], { stdio: 'ignore' });

    return !probe.error && probe.status === 0;
};

const shouldGenerateWayfinderTypes =
    process.env.WAYFINDER_GENERATE !== 'false' && hasPhpBinary();

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        shouldGenerateWayfinderTypes
            ? wayfinder({
                  formVariants: true,
              })
            : null,
    ].filter((plugin): plugin is PluginOption => plugin !== null),
    esbuild: {
        jsx: 'automatic',
    },
});
