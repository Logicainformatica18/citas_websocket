import { useState, useEffect, useRef } from "react";
import axios from "axios";
import ReactMarkdown from "react-markdown";
import { Card, CardContent } from "@/components/ui/card";
import { useDashboard } from "../DashboardContext"; // ✅ usamos el contexto

type Message = {
  from: "user" | "ai";
  text: string;
};

export default function AiChat() {
  const [messages, setMessages] = useState<Message[]>([
    { from: "ai", text: "Hola, ¿qué información te gustaría saber hoy?" },
  ]);
  const [input, setInput] = useState("");
  const [loading, setLoading] = useState(false);
  const [typingText, setTypingText] = useState(""); 
  const chatEndRef = useRef<HTMLDivElement | null>(null);

  // ✅ traemos updateDashboard desde el contexto
  const { updateDashboard } = useDashboard();

  const handleSend = async () => {
    if (!input.trim()) return;

    const userMessage = { from: "user" as const, text: input };
    setMessages((prev) => [...prev, userMessage]);
    setInput("");
    setLoading(true);
    setTypingText("");

    try {
      const res = await axios.post("/ai/chat", { message: input });

      const message = res.data.message || "⚠️ No se entendió la respuesta.";
      const suggestion = res.data.suggestion;

      console.log("📊 updateDashboard con:", res.data.aggregations);
      updateDashboard(res.data.results, res.data.aggregations); // ✅ ahora con context

      // typing animado solo para `message`
      let i = 0;
      const interval = setInterval(() => {
        setTypingText((prev) => prev + message[i]);
        i++;
        if (i >= message.length) {
          clearInterval(interval);
          setMessages((prev) => [...prev, { from: "ai", text: message }]);
          setTypingText("");
          setLoading(false);

          if (suggestion) {
            setMessages((prev) => [...prev, { from: "ai", text: "💡 " + suggestion }]);
          }
        }
      }, 20);

    } catch (error) {
      console.error(error);
      setMessages((prev) => [
        ...prev,
        { from: "ai", text: "⚠️ Error al conectar con el servidor." },
      ]);
      setLoading(false);
    }
  };

  // Auto-scroll al último mensaje
  useEffect(() => {
    chatEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, typingText, loading]);

  return (
    <Card className="bg-gray-800 h-full flex flex-col">
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

        {typingText && (
          <div className="p-3 rounded-lg max-w-[80%] bg-blue-600 text-white self-start prose prose-invert">
            <ReactMarkdown>{typingText}</ReactMarkdown>
          </div>
        )}

        {loading && !typingText && (
          <div className="p-3 rounded-lg bg-blue-600 text-white self-start flex space-x-1">
            <span className="w-2 h-2 bg-white rounded-full animate-bounce"></span>
            <span className="w-2 h-2 bg-white rounded-full animate-bounce delay-150"></span>
            <span className="w-2 h-2 bg-white rounded-full animate-bounce delay-300"></span>
          </div>
        )}

        <div ref={chatEndRef} />
      </CardContent>

      <div className="p-3 border-t border-gray-700 flex gap-2">
        <input
          value={input}
          onChange={(e) => setInput(e.target.value)}
          className="flex-1 p-2 rounded bg-gray-700 text-white"
          placeholder="Type a message"
          onKeyDown={(e) => e.key === "Enter" && handleSend()}
        />
        <button
          onClick={handleSend}
          className="px-4 py-2 bg-blue-600 rounded text-white"
        >
          Send
        </button>
      </div>
    </Card>
  );
}
