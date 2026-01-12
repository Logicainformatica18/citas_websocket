import "../css/app.css";
import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createRoot } from "react-dom/client";
import { initializeTheme } from "./hooks/use-appearance";
import axios from "axios";
import "./i18n";

import { SidebarProvider } from "@/components/ui/sidebar"; // ✅ Sidebar

/* ======================================================
   AXIOS – CSRF
====================================================== */
axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

const token = document.head.querySelector(
    'meta[name="csrf-token"]'
) as HTMLMetaElement | null;

if (token) {
    axios.defaults.headers.common["X-CSRF-TOKEN"] =
        token.content;
}

/* ======================================================
   APP
====================================================== */
const appName = import.meta.env.VITE_APP_NAME || "Isil";

createInertiaApp({
    title: (title) => `${title} ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob("./pages/**/*.tsx")
        ),

    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            // 🔑 CLAVE ABSOLUTA
            <SidebarProvider defaultState="collapsed">
                <App {...props} />
            </SidebarProvider>
        );
    },

    progress: {
        color: "#4B5563",
    },
});

/* ======================================================
   TEMA (dark / light)
====================================================== */
initializeTheme();
