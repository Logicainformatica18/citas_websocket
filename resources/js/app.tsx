import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import axios from 'axios'; // ✅ Import axios
import "./i18n";

// ✅ Configura el CSRF token global para Axios
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'; // <-- ESTA LÍNEA ES CRÍTICA

const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content')!;
} else {
   // console.warn('⚠️ CSRF token no encontrado en el <head>');
}

const appName = import.meta.env.VITE_APP_NAME || 'Isil';
const colors = {
  isil: {
    primary: "#1CBCE8",
    primaryDark: "#17A8D0",
    soft: "#E7F9FD",
    badge: "#C9F3FF",
    text: "#0C647A"
  }
};

createInertiaApp({
    title: (title) => `${title}  ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx')
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// Inicializa tema oscuro/claro
initializeTheme();
