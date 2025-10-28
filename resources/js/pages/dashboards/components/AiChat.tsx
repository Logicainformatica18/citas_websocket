import { useState, useEffect, useRef } from "react";
import axios from "axios";
import ReactMarkdown from "react-markdown";
import { Card, CardContent } from "@/components/ui/card";
import { useDashboard } from "../DashboardContext";
import { Send } from "lucide-react";

type Message = {
  from: "user" | "ai" | "error";
  text: string;
};

type Suggestion = {
  id: number;
  prompt: string;
  description?: string;
  interpreter?: string;
  component?: string;
};

function AiChat() {
  const [messages, setMessages] = useState<Message[]>([
    {
      from: "ai",
      text: "👋 Hola, soy **VERA**, tu analista del Observatorio de Empleabilidad ISIL. ¿Qué información deseas analizar hoy?",
    },
  ]);
  const [input, setInput] = useState("");
  const [loading, setLoading] = useState(false);
  const [typingText, setTypingText] = useState("");
  const [suggestions, setSuggestions] = useState<Suggestion[]>([]);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const chatEndRef = useRef<HTMLDivElement | null>(null);
  const debounceRef = useRef<NodeJS.Timeout | null>(null);

  const { updateDashboard } = useDashboard();

  // 🎨 Colores según tipo de mensaje
  const colorByRole: Record<Message["from"], string> = {
    user: "bg-gray-600 text-white self-end ml-auto",
    ai: "bg-blue-600 text-white self-start",
    error: "bg-red-600 text-white self-start",
  };

  // 🧠 Generar o recuperar session_id persistente por navegador
  const [sessionId] = useState(() => {
    let id = sessionStorage.getItem("veraSessionId");
    if (!id) {
      const generateUUID = () =>
        typeof crypto !== "undefined" && crypto.randomUUID
          ? crypto.randomUUID()
          : "xxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
              const r = (Math.random() * 16) | 0;
              const v = c === "x" ? r : (r & 0x3) | 0x8;
              return v.toString(16);
            });
      id = generateUUID();
      sessionStorage.setItem("veraSessionId", id);
    }
    return id;
  });

// 🧠 Cargar historial real desde el backend (solo 5 últimos)
useEffect(() => {
  axios
    .get("/api/ai/chat/history", {
      headers: { "X-Session-ID": sessionId },
    })
    .then((res) => {
      const serverMessages = res.data?.messages || [];
      if (serverMessages.length > 0) {
        setMessages([
          {
            from: "ai",
            text: "🧠 Historial cargado desde el Observatorio IA (últimos mensajes).",
          },
          ...serverMessages,
        ]);
      }
    })
    .catch((err) => {
      console.error("❌ Error cargando historial del chat:", err);
      setMessages([
        {
          from: "ai",
          text: "👋 Hola, soy **VERA**, tu analista del Observatorio de Empleabilidad ISIL. ¿Qué información deseas analizar hoy?",
        },
      ]);
    });
}, [sessionId]);


  // =====================================================
  // 🚀 Enviar mensaje o ejecutar entrenamiento seleccionado
  // =====================================================
  const handleSend = async (customText?: string, trainingId?: number) => {
    const textToSend = customText ?? input.trim();
    if (!textToSend) return;

    const userMessage = { from: "user" as const, text: textToSend };
    setMessages((prev) => [...prev, userMessage]);
    setInput("");
    setLoading(true);
    setTypingText("Pensando...");

    try {
      const payload = trainingId
        ? { training_id: trainingId }
        : { message: textToSend };

      const res = await axios.post("/api/ai/chat", payload, {
        headers: { "X-Session-ID": sessionId },
      });

      const data = res.data;

      // ✅ Caso 1: entrenamiento detectado
      if (data.topic && data.result) {
        // Guardar resultado para contexto
        sessionStorage.setItem("veraLastResult", JSON.stringify(data.result));

        // Primer mensaje (confirmación de ejecución)
        setMessages((prev) => [
          ...prev,
          { from: "ai", text: `📘 **${data.prompt}**` },
        ]);

        // Segundo mensaje (explicación IA)
        if (data.explanation) {
          setMessages((prev) => [
            ...prev,
            { from: "ai", text: data.explanation },
          ]);
        } else {
          setMessages((prev) => [
            ...prev,
            { from: "ai", text: "✅ Consulta procesada correctamente, pero no se generó explicación adicional." },
          ]);
        }

        // Actualiza dashboard visual
        updateDashboard(data.result, data.topic, data.component ?? null);
      }
      // 💬 Caso 2: GPT contextual (sin entrenamiento)
      else if (data.suggestion) {
        setMessages((prev) => [
          ...prev,
          { from: "ai", text: data.suggestion },
        ]);
      }
      // ⚠️ Caso 3: Mensaje simple o fallback
      else if (data.message) {
        setMessages((prev) => [
          ...prev,
          { from: "ai", text: data.message },
        ]);
      } else {
        setMessages((prev) => [
          ...prev,
          { from: "error", text: "⚠️ No se encontró un entrenamiento asociado a tu pregunta." },
        ]);
      }
    } catch (error: any) {
      console.error("❌ Error al conectar con IA:", error);
      let msg = "⚠️ Error al conectar con el servidor o la IA.";
      if (error.response?.status === 401)
        msg = "🔒 Sesión no autorizada. Inicia sesión nuevamente.";
      if (error.response?.status === 403)
        msg = "🚫 Acceso prohibido. Verifica permisos o CSRF.";
      if (error.response?.status === 500)
        msg = "💥 Error interno en el servidor.";

      setMessages((prev) => [...prev, { from: "error", text: msg }]);
    } finally {
      setLoading(false);
      setTypingText("");
    }
  };

  // =====================================================
  // 🧠 Autocompletado: sugerencias en tiempo real
  // =====================================================
  const fetchSuggestions = async (query: string) => {
    try {
      const res = await axios.get(`/api/ai/suggestions?q=${encodeURIComponent(query)}`);
      const data = res.data.suggestions || [];
      setSuggestions(Array.isArray(data) ? data.slice(0, 6) : []);
      setShowSuggestions(true);
    } catch (err) {
      console.error("❌ Error cargando sugerencias:", err);
      setShowSuggestions(false);
    }
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setInput(value);

    if (debounceRef.current) clearTimeout(debounceRef.current);

    if (value.trim().length >= 2) {
      debounceRef.current = setTimeout(() => fetchSuggestions(value), 400);
    } else {
      setShowSuggestions(false);
    }
  };

  // =====================================================
  // 🔄 Auto scroll al final del chat
  // =====================================================
  useEffect(() => {
    chatEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, typingText, loading]);

  // =====================================================
  // 💬 Render del componente
  // =====================================================
  return (
    <Card className="bg-gray-800 h-full flex flex-col border border-gray-700 rounded-lg shadow-lg">
      {/* ============================== */}
      {/* 🧠 HISTORIAL DEL CHAT */}
      {/* ============================== */}
      <CardContent className="p-4 flex-1 overflow-y-auto space-y-3 relative">
        {messages.map((m, i) => (
          <div
            key={i}
            className={`p-3 rounded-lg max-w-[80%] whitespace-pre-wrap prose prose-invert break-words ${
              colorByRole[m.from]
            }`}
          >
            <ReactMarkdown>{m.text}</ReactMarkdown>
          </div>
        ))}

        {/* 💬 Animación "escribiendo" */}
        {(typingText || loading) && (
          <div className="p-3 rounded-lg max-w-[80%] bg-blue-600 text-white self-start prose prose-invert animate-pulse">
            {typingText || "Analizando..."}
          </div>
        )}

        <div ref={chatEndRef} />
      </CardContent>

      {/* ============================== */}
      {/* ⌨️ INPUT Y BOTÓN DE ENVÍO */}
      {/* ============================== */}
      <div className="relative p-3 border-t border-gray-700 flex flex-col gap-2">
        <div className="relative w-full">
          <input
            value={input}
            onChange={handleInputChange}
            className="flex-1 p-2 rounded bg-gray-700 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 w-full"
            placeholder="Escribe tu pregunta sobre métricas..."
            aria-label="Escribir mensaje"
            onKeyDown={(e) => e.key === "Enter" && handleSend()}
          />

          {/* 🔽 Lista de sugerencias (autocompletado) */}
          {showSuggestions && (
            <div className="absolute bottom-12 left-0 bg-gray-800 border border-gray-700 rounded-lg shadow-xl w-full max-h-[220px] overflow-y-auto z-50">
              {suggestions.length > 0 ? (
                suggestions.map((sug) => (
                  <div
                    key={sug.id}
                    onClick={() => {
                      setInput(sug.prompt);
                      setShowSuggestions(false);
                      handleSend(sug.prompt, sug.id);
                    }}
                    className="px-3 py-2 text-sm hover:bg-blue-600 cursor-pointer text-white transition"
                  >
                    {sug.prompt}
                    {sug.description && (
                      <div className="text-gray-400 text-xs">
                        {sug.description}
                      </div>
                    )}
                  </div>
                ))
              ) : (
                <div className="text-gray-400 text-sm p-3">
                  No hay coincidencias
                </div>
              )}
            </div>
          )}
        </div>

        <button
          onClick={() => handleSend()}
          disabled={loading}
          className="px-4 py-2 bg-blue-600 rounded text-white flex items-center justify-center gap-2 hover:bg-blue-700 transition disabled:opacity-50"
        >
          <Send className="w-4 h-4" />
          Enviar
        </button>
      </div>
    </Card>
  );
}

export default AiChat;
