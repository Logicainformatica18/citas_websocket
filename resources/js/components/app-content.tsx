import { SidebarInset } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import * as React from 'react';

interface AppContentProps extends React.ComponentProps<'main'> {
    variant?: 'header' | 'sidebar';
}

export function AppContent({ variant = 'header', children, className, ...props }: AppContentProps) {
    if (variant === 'sidebar') {
        return (
            // min-w-0: SidebarInset es un flex item con flex-1 y sin min-width.
            // Sin esto no puede encogerse por debajo del ancho de su contenido,
            // así que una tabla ancha empuja el layout entero y la barra
            // horizontal aparece en la página en vez de quedar en la tabla.
            <SidebarInset className={cn('min-w-0', className)} {...props}>
                {children}
            </SidebarInset>
        );
    }

    return (
        <main className={cn('mx-auto flex h-full w-full min-w-0 max-w-7xl flex-1 flex-col gap-4 rounded-xl', className)} {...props}>
            {children}
        </main>
    );
}