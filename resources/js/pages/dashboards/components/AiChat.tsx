import { useState, useEffect, useRef } from "react";
import axios from "axios";
import ReactMarkdown from "react-markdown";
import { Card, CardContent } from "@/components/ui/card";
import { useDashboard } from "../DashboardContext";
import { Send } from "lucide-react";

type Message = {
  from: "user" | "ai";
  text: string;
};

type Suggestion = {
  id: number;
  prompt: string;
  description?: string;
  interpreter?: string;
  component?: string;
};

export default function AiChat() {
  const [messages, setMessages] = useState<Message[]>([
    { from: "ai", text: "👋 Hola, soy VERA. ¿Qué información deseas analizar hoy?" },
  ]);
  const [input, setInput] = useState("");
  const [loading, setLoading] = useState(false);
  const [typingText, setTypingText] = useState("");
  const [suggestions, setSuggestions] = useState<Suggestion[]>([]);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const chatEndRef = useRef<HTMLDivElement | null>(null);
  const debounceRef = useRef<NodeJS.Timeout | null>(null);

  const { updateDashboard } = useDashboard();

  // 🎨 Colores por tipo de tópico
  const colorByTopic: Record<string, string> = {
    "Métricas y monitoreo": "bg-blue-600",
    "Obsolescencia": "bg-red-600",
    "Alineación": "bg-indigo-600",
    "Crecimiento": "bg-green-600",
  };

  // 💾 Mantener historial del chat entre sesiones
  useEffect(() => {
    const saved = localStorage.getItem("veraChatHistory");
    if (saved) setMessages(JSON.parse(saved));
  }, []);

  useEffect(() => {
    localStorage.setItem("veraChatHistory", JSON.stringify(messages));
  }, [messages]);

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
    setTypingText("");
    setSuggestions([]);
    setShowSuggestions(false);

    try {
      const payload = trainingId
        ? { training_id: trainingId }
        : { message: textToSend };

      console.log("📤 Enviando payload al backend:", payload);

      const res = await axios.post("/api/ai/chat", payload);
      const data = res.data;

      if (data.topic && data.result) {
        let explanationText = `📊 **${data.prompt}**\n\n`;
        explanationText += data.explanation
          ? `${data.explanation}`
          : "✅ Consulta procesada correctamente.";

        setMessages((prev) => [...prev, { from: "ai", text: explanationText }]);

        // Mostrar explicación IA adicional si existe
        if (data.explanation) {
          setMessages((prev) => [
            ...prev,
            { from: "ai", text: `💬 **Explicación IA:**\n\n${data.explanation}` },
          ]);
        }

        // Actualizar dashboard
        updateDashboard(data.result, data.topic, data.component ?? null);
      } else if (data.message || data.suggestion) {
        const text = data.message ?? `💡 ${data.suggestion}`;
        setMessages((prev) => [...prev, { from: "ai", text }]);
      } else {
        setMessages((prev) => [
          ...prev,
          { from: "ai", text: "⚠️ No se encontró un entrenamiento asociado a tu pregunta." },
        ]);
      }
    } catch (error) {
      console.error("❌ Error al conectar con IA:", error);
      setMessages((prev) => [
        ...prev,
        { from: "ai", text: "⚠️ Error al conectar con el servidor o la IA." },
      ]);
    } finally {
      setLoading(false);
      setTypingText("");
    }
  };

  // =====================================================
  // 🧠 Autocompletado: buscar sugerencias en tiempo real
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
      <CardContent className="p-4 flex-1 overflow-y-auto space-y-3 relative">
        {/* Mensajes del chat */}
        {messages.map((m, i) => (
          <div
            key={i}
            className={`p-3 rounded-lg max-w-[80%] whitespace-pre-wrap prose prose-invert ${
              m.from === "ai"
                ? `${colorByTopic["Métricas y monitoreo"] || "bg-blue-600"} text-white self-start`
                : "bg-gray-600 text-white self-end ml-auto"
            }`}
          >
            <ReactMarkdown>{m.text}</ReactMarkdown>
          </div>
        ))}

        {/* Animación typing */}
        {(typingText || loading) && (
          <div className="p-3 rounded-lg max-w-[80%] bg-blue-600 text-white self-start prose prose-invert">
            {typingText || (
              <div className="flex space-x-1">
                <span className="w-2 h-2 bg-white rounded-full animate-bounce" />
                <span className="w-2 h-2 bg-white rounded-full animate-bounce delay-150" />
                <span className="w-2 h-2 bg-white rounded-full animate-bounce delay-300" />
              </div>
            )}
          </div>
        )}

        <div ref={chatEndRef} />
      </CardContent>

      {/* Input + botón */}
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
          {showSuggestions && suggestions.length > 0 && (
            <div className="absolute bottom-12 left-0 bg-gray-800 border border-gray-700 rounded-lg shadow-xl w-full max-h-[220px] overflow-y-auto z-50">
              {suggestions.map((sug) => (
                <div
                  key={sug.id}
                  onClick={() => {
                    setInput(sug.prompt);
                    setShowSuggestions(false);
                    handleSend(sug.prompt, sug.id); // ✅ envía con ID
                  }}
                  className="px-3 py-2 text-sm hover:bg-blue-600 cursor-pointer text-white transition"
                >
                  {sug.prompt}
                </div>
              ))}
            </div>
          )}

          {showSuggestions && suggestions.length === 0 && (
            <div className="absolute bottom-12 left-0 bg-gray-800 border border-gray-700 rounded-lg shadow-xl w-full text-gray-400 text-sm p-3">
              No hay coincidencias
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
