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
    { from: "ai", text: "👋 Hola, ¿qué información te gustaría saber hoy?" },
  ]);
  const [input, setInput] = useState("");
  const [loading, setLoading] = useState(false);
  const [typingText, setTypingText] = useState("");
  const chatEndRef = useRef<HTMLDivElement | null>(null);

  const { updateDashboard } = useDashboard();

 const handleSend = async () => {
  if (!input.trim()) return;

  const userMessage = { from: "user" as const, text: input };
  setMessages((prev) => [...prev, userMessage]);
  setInput("");
  setLoading(true);
  setTypingText("");

  try {
    const res = await axios.post("dashboard/ai/chat", { message: userMessage.text });

    const { message, suggestion, instruction, results, aggregations } = res.data;

    // 🔹 Actualizar dashboard si hay datos
    if (results || aggregations || instruction) {
      updateDashboard(results, aggregations, instruction);
    }

    // 🔹 Caso 1: Hay message
    if (message) {
      setMessages((prev) => [...prev, { from: "ai", text: message }]);
    }

    // 🔹 Caso 2: Hay suggestion
    if (suggestion) {
      setMessages((prev) => [...prev, { from: "ai", text: "💡 " + suggestion }]);
    }

    // 🔹 Caso 3: No hay message ni suggestion → feedback genérico
    if (!message && !suggestion && (results || instruction)) {
      setMessages((prev) => [
        ...prev,
        { from: "ai", text: "✅ Consulta procesada. Revisa el dashboard." },
      ]);
    }
  } catch (error) {
    console.error(error);
    setMessages((prev) => [
      ...prev,
      { from: "ai", text: "⚠️ Error al conectar con el servidor." },
    ]);
  } finally {
    setLoading(false);
    setTypingText("");
  }
};


  // 🔹 Auto scroll
  useEffect(() => {
    chatEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, typingText, loading]);

  return (
    <Card className="bg-gray-800 h-full flex flex-col">
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

      </div>
        <button
          onClick={handleSend}
          disabled={loading}
          className="px-4 py-2 bg-blue-600 rounded text-white flex items-center gap-2 hover:bg-blue-700 transition disabled:opacity-50"
        >
          <Send className="w-4 h-4" />
          Enviar
        </button>
    </Card>
  );
}
