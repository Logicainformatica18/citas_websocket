import { router } from "@inertiajs/react";

/**
 * Utilidades de medición de rendimiento en el navegador.
 * Solo escriben en consola cuando import.meta.env.DEV es verdadero;
 * en la compilación de producción no generan salida.
 */

export const perfEnabled: boolean = Boolean(import.meta.env.DEV);

/** Inicio de la última navegación Inertia (null = carga completa de página). */
let navigationStart: number | null = null;

if (perfEnabled && typeof window !== "undefined") {
    router.on("start", () => {
        navigationStart = performance.now();
    });
}

/**
 * Registra el tiempo hasta que un bloque es visible.
 * En una carga completa se mide desde el inicio de la navegación del navegador;
 * en una navegación Inertia, desde el evento "start" del router.
 */
export function logVisible(label: string): void {
    if (!perfEnabled) return;

    // Doble requestAnimationFrame: se mide después de que el navegador pinta.
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            const origin = navigationStart ?? 0;
            const type = navigationStart === null ? "carga completa" : "navegación Inertia";

            console.info(
                `[rendimiento] ${label} visibles en ${Math.round(performance.now() - origin)} ms (${type})`
            );

            navigationStart = null;
        });
    });
}

/**
 * Registra la duración de una petición.
 */
export function logRequest(label: string, startedAt: number, extra: Record<string, unknown> = {}): void {
    if (!perfEnabled) return;

    console.info(
        `[rendimiento] ${label}: ${Math.round(performance.now() - startedAt)} ms`,
        extra
    );
}
