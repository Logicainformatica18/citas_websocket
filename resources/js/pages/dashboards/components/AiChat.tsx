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

export default function AiChat() {
  const [messages, setMessages] = useState<Message[]>([
    { from: "ai", text: "👋 Hola, soy VERA. ¿Qué información deseas analizar hoy?" },
  ]);
  const [input, setInput] = useState("");
  const [loading, setLoading] = useState(false);
  const [typingText, setTypingText] = useState("");
  const chatEndRef = useRef<HTMLDivElement | null>(null);

  // Contexto del dashboard (actualiza visualizaciones)
  const { updateDashboard } = useDashboard();

  // =====================================================
  // 🚀 Enviar mensaje al backend
  // =====================================================
  const handleSend = async () => {
    if (!input.trim()) return;

    const userMessage = { from: "user" as const, text: input };
    setMessages((prev) => [...prev, userMessage]);
    setInput("");
    setLoading(true);
    setTypingText("");

    try {
      const res = await axios.post("/ai/chat", { message: userMessage.text });
      const data = res.data;

      console.log("🧠 Respuesta IA:", data);

      // =====================================================
      // 🧩 Caso 1: Respuesta tipo entrenamiento AITraining
      // =====================================================
      if (data.topic && data.result) {
        // Mostrar respuesta natural
        let explanationText = `📊 **${data.prompt}**\n\n`;
        explanationText += data.explanation
          ? `${data.explanation}`
          : "✅ Consulta procesada correctamente.";

        setMessages((prev) => [...prev, { from: "ai", text: explanationText }]);

        // Actualiza dashboard con componente dinámico si aplica
        updateDashboard(data.result, data.topic, data.component ?? null);
      }

      // =====================================================
      // 💬 Caso 2: Mensaje directo o sugerencia textual
      // =====================================================
      else if (data.message || data.suggestion) {
        const text = data.message ?? `💡 ${data.suggestion}`;
        setMessages((prev) => [...prev, { from: "ai", text }]);
      }

      // =====================================================
      // ⚠️ Caso 3: Sin resultados ni texto (fallback)
      // =====================================================
      else {
        setMessages((prev) => [
          ...prev,
          {
            from: "ai",
            text: "⚠️ No se encontró un entrenamiento asociado a tu pregunta.",
          },
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
  // 🔄 Auto scroll al final del chat
  // =====================================================
  useEffect(() => {
    chatEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, typingText, loading]);

  // =====================================================
  // 💬 Render
  // =====================================================
  return (
    <Card className="bg-gray-800 h-full flex flex-col border border-gray-700 rounded-lg shadow-lg">
      {/* Chat content */}
      <CardContent className="p-4 flex-1 overflow-y-auto space-y-3">
        {messages.map((m, i) => (
          <div
            key={i}
            className={`p-3 rounded-lg max-w-[80%] whitespace-pre-wrap prose prose-invert ${
              m.from === "ai"
                ? "bg-blue-600 text-white self-start"
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
      <div className="p-3 border-t border-gray-700 flex gap-2">
        <input
          value={input}
          onChange={(e) => setInput(e.target.value)}
          className="flex-1 p-2 rounded bg-gray-700 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="Escribe tu mensaje..."
          aria-label="Escribir mensaje"
          onKeyDown={(e) => e.key === "Enter" && handleSend()}
        />

        <button
          onClick={handleSend}
          disabled={loading}
          className="px-4 py-2 bg-blue-600 rounded text-white flex items-center gap-2 hover:bg-blue-700 transition disabled:opacity-50"
        >
          <Send className="w-4 h-4" />
          Enviar
        </button>
      </div>
    </Card>
  );
}
