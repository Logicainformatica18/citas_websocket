import { useState, useEffect, useRef } from "react";
import axios from "axios";
import ReactMarkdown from "react-markdown";

import { Card, CardContent } from "@/components/ui/card";
import { useDashboard } from "../DashboardContext";
import { Send, X } from "lucide-react";
import remarkGfm from "remark-gfm";
import rehypeRaw from "rehype-raw";
import rehypeHighlight from "rehype-highlight";


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
    const { updateDashboard } = useDashboard();
    const chatEndRef = useRef<HTMLDivElement | null>(null);
    const debounceRef = useRef<NodeJS.Timeout | null>(null);
    const [forceNew, setForceNew] = useState(false);

    // 📩 Estado de mensajes
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

    // 🪟 Tamaño persistente
    const [chatSize, setChatSize] = useState(() => {
        const saved = localStorage.getItem("veraChatSize");
        return saved ? JSON.parse(saved) : { width: 400, height: 600 };
    });

    const chatRef = useRef<HTMLDivElement | null>(null);


    // === NUEVOS ESTADOS ===
    const [recording, setRecording] = useState(false);
    const [recorder, setRecorder] = useState<MediaRecorder | null>(null);
    const [file, setFile] = useState<File | null>(null);
    const [processingAudio, setProcessingAudio] = useState(false);

    // 🎙️ Iniciar grabación de voz
    const startRecording = async () => {
  if (!navigator.mediaDevices?.getUserMedia) {
    setMessages((prev) => [
      ...prev,
      { from: "error", text: "🎙️ Tu navegador no permite grabar audio." },
    ]);
    return;
  }

  const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
  const mediaRecorder = new MediaRecorder(stream);
  const chunks: BlobPart[] = [];

  mediaRecorder.ondataavailable = (e) => chunks.push(e.data);
  mediaRecorder.onstop = async () => {
  if (processingAudio) return; // 🛑 Evita duplicados
  setProcessingAudio(true);

  const blob = new Blob(chunks, { type: "audio/webm" });
  const formData = new FormData();
  formData.append("audio", blob, "voz.webm");

  setMessages((prev) => [
    ...prev,
    { from: "ai", text: "🎙️ Transcribiendo tu audio..." },
  ]);

  try {
    const res = await axios.post("/api/ai/voice/transcribe", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    const text = res.data.text || "";
    if (text) {
      handleSend(text);
    } else {
      setMessages((prev) => [
        ...prev,
        { from: "error", text: "⚠️ No se detectó voz." },
      ]);
    }
  } catch (err) {
    setMessages((prev) => [
      ...prev,
      { from: "error", text: "💥 Error al transcribir audio." },
    ]);
  } finally {
    setProcessingAudio(false);
  }
};


  mediaRecorder.start();
  setRecorder(mediaRecorder);
  setRecording(true);
};


    // 🛑 Detener grabación
    const stopRecording = () => {
        recorder?.stop();
        setRecording(false);
    };
    const handleFileUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const selected = e.target.files?.[0];
        if (!selected) return;
        setFile(selected);

        const formData = new FormData();
        formData.append("file", selected);
        formData.append("prompt", "Analiza el contenido de este archivo");

        const res = await axios.post("/api/ai/file/analyze", formData);
        const data = res.data;
        setMessages((prev) => [
            ...prev,
            { from: "user", text: `📎 Archivo enviado: ${selected.name}` },
            { from: "ai", text: data.analysis },
        ]);
    };
    const speak = async (text: string) => {
        const res = await axios.post("/api/ai/voice/speak", { text });
        new Audio(res.data.url).play();
    };

    useEffect(() => {
        const saved = localStorage.getItem("veraForceNew");
        if (saved) setForceNew(JSON.parse(saved));
    }, []);

    useEffect(() => {
        localStorage.setItem("veraForceNew", JSON.stringify(forceNew));
    }, [forceNew]);

    // 💾 Guardar tamaño cuando cambie
    useEffect(() => {
        localStorage.setItem("veraChatSize", JSON.stringify(chatSize));
    }, [chatSize]);

    // 🖱️ Redimensionar
    const handleResize = (e: React.MouseEvent<HTMLDivElement>) => {
        e.preventDefault();
        const startX = e.clientX;
        const startY = e.clientY;
        const startWidth = chatSize.width;
        const startHeight = chatSize.height;

        const onMouseMove = (ev: MouseEvent) => {
            const newWidth = Math.min(window.innerWidth - 40, Math.max(320, startWidth + (ev.clientX - startX)));
            const newHeight = Math.min(window.innerHeight - 80, Math.max(420, startHeight + (ev.clientY - startY)));
            setChatSize({ width: newWidth, height: newHeight });
        };

        const onMouseUp = () => {
            document.removeEventListener("mousemove", onMouseMove);
            document.removeEventListener("mouseup", onMouseUp);
        };

        document.addEventListener("mousemove", onMouseMove);
        document.addEventListener("mouseup", onMouseUp);
    };

    // 🎨 Colores según tipo de mensaje
    const colorByRole: Record<Message["from"], string> = {
        user: "bg-gray-600 text-white self-end ml-auto",
        ai: "bg-blue-600 text-white self-start",
        error: "bg-red-600 text-white self-start",
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

    // 🧠 Session ID persistente
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

    // 📜 Cargar historial
    useEffect(() => {
        axios
            .get("/api/ai/chat/history", { headers: { "X-Session-ID": sessionId } })
            .then((res) => {
                const serverMessages = res.data?.messages || [];
                if (serverMessages.length > 0) {
                    setMessages([
                        { from: "ai", text: "🧠 Historial cargado desde el Observatorio IA (últimos mensajes)." },
                        ...serverMessages,
                    ]);
                }
            })
            .catch(() => {
                setMessages([
                    {
                        from: "ai",
                        text: "👋 Hola, soy **VERA**, tu analista del Observatorio de Empleabilidad ISIL. ¿Qué información deseas analizar hoy?",
                    },
                ]);
            });
    }, [sessionId]);

    // 🚀 Enviar mensaje
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
                ? { training_id: trainingId, force_new: forceNew }
                : { message: textToSend, force_new: forceNew };

            const res = await axios.post("/api/ai/chat", payload, { headers: { "X-Session-ID": sessionId } });
            const data = res.data;

            if (data.topic && data.result) {
                sessionStorage.setItem("veraLastResult", JSON.stringify(data.result));
                setMessages((prev) => [...prev, { from: "ai", text: `📘 **${data.prompt}**` }]);
                setMessages((prev) => [...prev, { from: "ai", text: data.explanation ?? "✅ Consulta procesada correctamente." }]);
                updateDashboard(data.result, data.topic, data.component ?? null);
            } else if (data.suggestion) {
                setMessages((prev) => [...prev, { from: "ai", text: data.suggestion }]);
            } else if (data.message) {
                setMessages((prev) => [...prev, { from: "ai", text: data.message }]);
            } else {
                setMessages((prev) => [...prev, { from: "error", text: "⚠️ No se encontró un entrenamiento asociado." }]);
            }
        } catch (error: any) {
            const msg =
                error.response?.status === 401
                    ? "🔒 Sesión no autorizada."
                    : error.response?.status === 403
                        ? "🚫 Acceso prohibido."
                        : error.response?.status === 500
                            ? "💥 Error interno en el servidor."
                            : "⚠️ Error al conectar con la IA.";
            setMessages((prev) => [...prev, { from: "error", text: msg }]);
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
    // 💬 Render del componente
    // =====================================================
    const [isVisible, setIsVisible] = useState(true);
    if (!isVisible) return null;

    return (
        <Card
            ref={chatRef}
            className="bg-gray-800 flex flex-col border border-gray-700 rounded-lg shadow-2xl fixed bottom-4 right-4 z-50 overflow-hidden backdrop-blur-md"
            style={{
                width: chatSize.width,
                height: chatSize.height,
                transition: "width 0.1s ease, height 0.1s ease",
            }}
        >
            {/* ============================== */}
            {/* 🧭 ENCABEZADO SUPERIOR */}
            {/* ============================== */}
            <div className="p-2 bg-gray-700/90 text-gray-100 text-sm font-semibold border-b border-gray-600 flex justify-between items-center">
                <span>💬 Chat con VERA</span>
                <button
                    onClick={() => setIsVisible(false)}
                    className="text-gray-400 hover:text-white transition"
                    title="Cerrar chat"
                >
                    <X className="w-4 h-4" />
                </button>
            </div>

            {/* ============================== */}
            {/* 🧠 HISTORIAL DEL CHAT */}
            {/* ============================== */}
            <CardContent className="p-4 flex-1 overflow-y-auto space-y-3 relative">
                {messages.map((m, i) => (
                    <div
                        key={i}
                        className={`p-3 rounded-lg max-w-[85%] shadow-sm ${colorByRole[m.from]
                            }`}
                    >
                        <div className="prose prose-invert max-w-none bg-gray-900/40 rounded-lg p-3 border border-gray-700">
                            <ReactMarkdown
                                remarkPlugins={[remarkGfm]}
                                rehypePlugins={[rehypeRaw, rehypeHighlight]}
                                components={{
                                    table: ({ node, ...props }) => (
                                        <table
                                            className="w-full border-collapse border border-gray-700 text-sm"
                                            {...props}
                                        />
                                    ),
                                    th: ({ node, ...props }) => (
                                        <th className="border border-gray-700 bg-gray-800 text-gray-100 px-3 py-2 text-left font-semibold" {...props} />
                                    ),
                                    td: ({ node, ...props }) => (
                                        <td className="border border-gray-700 px-3 py-2 text-gray-300" {...props} />
                                    ),
                                    code: ({ node, inline, ...props }) =>
                                        inline ? (
                                            <code className="bg-gray-800 text-green-400 px-2 py-0.5 rounded" {...props} />
                                        ) : (
                                            <pre className="bg-gray-900 text-gray-100 p-3 rounded-lg overflow-x-auto text-sm">
                                                <code {...props} />
                                            </pre>
                                        ),
                                }}
                            >
                                {m.text}
                            </ReactMarkdown>
                            {m.from === "ai" && (
                                <button
                                    onClick={() => speak(m.text)}
                                    className="mt-1 text-xs text-blue-300 hover:text-blue-100 transition"
                                >
                                    🔊 Escuchar
                                </button>
                            )}

                        </div>
                    </div>

                ))}

                {/* 💬 Animación "escribiendo" */}
                {(typingText || loading) && (
                    <div className="p-3 rounded-lg max-w-[70%] bg-blue-600 text-white self-start animate-pulse">
                        {typingText || "Analizando..."}
                    </div>
                )}

                <div ref={chatEndRef} />
            </CardContent>

            {/* ============================== */}
            {/* ⌨️ INPUT Y BOTÓN DE ENVÍO */}
            {/* ============================== */}
            <div className="relative p-3 border-t border-gray-700 flex flex-col gap-2 bg-gray-800/95">
                <div className="relative w-full">
                    <input
                        value={input}
                        onChange={(e) => handleInputChange(e)}

                        className="flex-1 p-2 rounded bg-gray-700 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 w-full"
                        placeholder="Escribe tu pregunta sobre métricas..."
                        aria-label="Escribir mensaje"
                        onKeyDown={(e) => e.key === "Enter" && handleSend()}
                    />

                    {/* 🔽 Lista de sugerencias (autocompletado) */}
                    {showSuggestions && (
                        <div className="absolute bottom-12 left-0 bg-gray-900 border border-gray-700 rounded-lg shadow-xl w-full max-h-[220px] overflow-y-auto z-50">
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
                                            <div className="text-gray-400 text-xs">{sug.description}</div>
                                        )}
                                    </div>
                                ))
                            ) : (
                                <div className="text-gray-400 text-sm p-3">No hay coincidencias</div>
                            )}
                        </div>
                    )}
                </div>
                <div
                    onClick={() => setForceNew(!forceNew)}
                    className={`relative w-10 h-5 rounded-full cursor-pointer transition ${forceNew ? "bg-blue-600" : "bg-gray-500"
                        }`}
                >
                    <div
                        className={`absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full transition-transform ${forceNew ? "translate-x-5" : "translate-x-0"
                            }`}
                    >


                    </div>
                </div>
                <span className="text-sm text-gray-300 ml-2">🔄 Forzar nueva respuesta</span>
  {/* 🎙️ Botón de voz */}
  <button
    onClick={recording ? stopRecording : startRecording}
    className={`p-2 rounded ${recording ? "bg-red-600" : "bg-blue-600"} hover:bg-blue-700 text-white`}
    title={recording ? "Detener grabación" : "Hablar con VERA"}
  >
    {recording ? "⏹️" : "🎤"}
  </button>

                <button
                    onClick={() => handleSend()}
                    disabled={loading}
                    className="px-4 py-2 bg-blue-600 rounded text-white flex items-center justify-center gap-2 hover:bg-blue-700 transition disabled:opacity-50"
                >
                    <Send className="w-4 h-4" />
                    Enviar
                </button>

            </div>

            {/* 🪟 Handle de redimensionamiento */}
            <div
                onMouseDown={handleResize}
                className="absolute bottom-1 right-1 w-5 h-5 cursor-se-resize bg-gray-500/40 rounded-sm hover:bg-gray-400/70 active:bg-gray-300/80"
                title="Arrastra para cambiar tamaño"
            />
        </Card>
    );
}

export default AiChat;
