import i18n from "i18next";
import { initReactI18next } from "react-i18next";
import LanguageDetector from "i18next-browser-languagedetector";

// Traducciones iniciales (solo español)
const resources = {
  es: {
    translation: {
      "Modalidad laboral": "Modalidad laboral",
      "Filtros": "Filtros",
      "Año": "Año",
      "Países": "Países",
      "Industria": "Industria",
      "Buscar país...": "Buscar país...",
      "Cargando...": "Cargando...",
      "No hay datos disponibles": "No hay datos disponibles",
      "Respuestas": "Respuestas",

      // Traducciones de valores
      "Remote": "Remoto",
      "In-person": "Presencial",
      "Hybrid (some remote, some in-person)": "Híbrido (parte remoto, parte presencial)"
    },
  },
};

i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    resources,
    fallbackLng: "es",
    interpolation: { escapeValue: false },
  });

export default i18n;
