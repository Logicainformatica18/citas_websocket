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
      // 🎨 Override manual de colores "seguros" para html2canvas
      colors: {
        gray: {
          50: "#f9fafb",
          100: "#f3f4f6",
          200: "#e5e7eb",
          300: "#d1d5db",
          400: "#9ca3af",
          500: "#6b7280",
          600: "#4b5563",
          700: "#374151",
          800: "#1f2937", // ✅ reemplaza oklch() por rgb
          900: "#111827", // ✅ reemplaza oklch() por rgb
        },
        blue: {
          400: "#60a5fa",
          600: "#2563eb",
          700: "#1d4ed8",
        },
      },
    },
  },
  corePlugins: {
    preflight: true,
  },
  experimental: {
    optimizeUniversalDefaults: false,
  },
  plugins: [forms, typography],
};
