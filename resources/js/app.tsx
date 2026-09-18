import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { Toaster } from 'sonner';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <>
                <App {...props} />
                <Toaster
                    position="top-center"
                    toastOptions={{
                        unstyled: true,
                        classNames: {
                            toast: 'flex w-full items-center gap-3 rounded-lg border border-border bg-background p-4 text-sm text-foreground shadow-lg',
                            title: 'font-medium',
                            description: 'text-muted-foreground',
                            actionButton: 'rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground',
                            cancelButton: 'rounded-md bg-muted px-3 py-1.5 text-xs font-medium text-muted-foreground',
                            closeButton: 'border-border bg-background text-foreground',
                            success: 'border-l-4 border-l-green-600 dark:border-l-green-500',
                            error: 'border-l-4 border-l-destructive',
                        },
                    }}
                />
            </>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
