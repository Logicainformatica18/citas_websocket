/** @type {import('tailwindcss').Config} */
const forms = require("@tailwindcss/forms");
const typography = require("@tailwindcss/typography");

module.exports = {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
    "./resources/**/*.jsx",
    "./resources/**/*.tsx",
  ],

  theme: {
    extend: {
      /* ======================================================
         🎨 PALETA BASE (SAFE RGB – html2canvas friendly)
      ====================================================== */
      colors: {
        /* === Grises seguros === */
        gray: {
          50: "#f9fafb",
          100: "#f3f4f6",
          200: "#e5e7eb",
          300: "#d1d5db",
          400: "#9ca3af",
          500: "#6b7280",
          600: "#4b5563",
          700: "#374151",
          800: "#1f2937",
          900: "#111827",
        },

        /* === Azules seguros === */
        blue: {
          400: "#60a5fa",
          600: "#2563eb",
          700: "#1d4ed8",
        },

        /* ======================================================
           🌐 ISIL / OBSERVATORIO THEME
        ====================================================== */

        /* Brand principal ISIL */
        primary: {
          DEFAULT: "#1CBCE8",   // Azul ISIL
          dark: "#0A4E61",      // Azul profundo ISIL
          soft: "#ECFAFD",      // Fondo suave cards / badges
        },

        /* Fondos generales */
        background: "#F8FCFE", // Fondo general dashboard
        card: "#FFFFFF",       // Cards

        /* Texto */
        foreground: "#0F172A", // Texto principal
        muted: "#64748B",      // Texto secundario
        subtle: "#94A3B8",     // Ayuda / labels

        /* Bordes */
        border: "rgba(28, 188, 232, 0.25)",

        /* Estados */
        success: "#22C55E",
        warning: "#F59E0B",
        danger: "#EF4444",

        /* Badges */
        badge: {
          demand: "#E6F7FB",       // Alta demanda
          projection: "#EEF9F1",   // Alta proyección
        },
      },

      /* ======================================================
         📐 RADIOS (idénticos al diseño)
      ====================================================== */
      borderRadius: {
        lg: "12px",
        xl: "16px",
        "2xl": "20px",
      },

      /* ======================================================
         🌫 SOMBRAS ISIL
      ====================================================== */
      boxShadow: {
        card: "0 6px 24px rgba(28, 188, 232, 0.12)",
        soft: "0 2px 12px rgba(15, 23, 42, 0.08)",
        hover: "0 10px 30px rgba(28, 188, 232, 0.18)",
      },

      /* ======================================================
         🧾 TIPOGRAFÍA (mejor legibilidad dashboard)
      ====================================================== */
      fontSize: {
        xxs: "0.65rem",
      },

      lineHeight: {
        relaxed: "1.75",
      },

      /* ======================================================
         ⏱ TRANSICIONES SUAVES
      ====================================================== */
      transitionTimingFunction: {
        soft: "cubic-bezier(0.4, 0, 0.2, 1)",
      },
    },
  },

  corePlugins: {
    preflight: true,
  },

  experimental: {
    optimizeUniversalDefaults: false,
  },

  plugins: [
    forms,
    typography,
  ],
};
