// useAiChatLogic.ts
import { useState, useEffect, useRef } from "react";
import axios from "axios";
import { useDashboard } from "../../DashboardContext";

export type Message = {
  from: "user" | "ai" | "error";
  text: string;
};

export type Suggestion = {
  id: number;
  prompt: string;
  description?: string;
  interpreter?: string;
  component?: string;
};

export function useAiChatLogic() {
  const { updateDashboard } = useDashboard();
  const chatEndRef = useRef<HTMLDivElement | null>(null);
  const debounceRef = useRef<NodeJS.Timeout | null>(null);

  // Estados persistentes
  const [forceNew, setForceNew] = useState(() => JSON.parse(localStorage.getItem("veraForceNew") || "false"));
  const [voiceEnabled, setVoiceEnabled] = useState(() => JSON.parse(localStorage.getItem("veraVoiceEnabled") || "true"));

  // Chat
  const [messages, setMessages] = useState<Message[]>([
    { from: "ai", text: "👋 Hola, soy **VERA**, tu analista del Observatorio ISIL. ¿Qué información deseas analizar hoy?"},
  ]);
  const [input, setInput] = useState("");
  const [loading, setLoading] = useState(false);
  const [typingText, setTypingText] = useState("");
  const [suggestions, setSuggestions] = useState<Suggestion[]>([]);
  const [showSuggestions, setShowSuggestions] = useState(false);

  // Dimensiones
  const [chatSize, setChatSize] = useState(() => {
    const saved = localStorage.getItem("veraChatSize");
    return saved ? JSON.parse(saved) : { width: 420, height: 580 };
  });

  // Grabación / archivos
  const [recording, setRecording] = useState(false);
  const [recorder, setRecorder] = useState<MediaRecorder | null>(null);
  const [processingAudio, setProcessingAudio] = useState(false);

  // Persistencia
  useEffect(() => localStorage.setItem("veraForceNew", JSON.stringify(forceNew)), [forceNew]);
  useEffect(() => localStorage.setItem("veraVoiceEnabled", JSON.stringify(voiceEnabled)), [voiceEnabled]);
  useEffect(() => localStorage.setItem("veraChatSize", JSON.stringify(chatSize)), [chatSize]);

  // Scroll automático
  useEffect(() => chatEndRef.current?.scrollIntoView({ behavior: "smooth" }), [messages, typingText, loading]);

  // Session
  const [sessionId] = useState(() => {
    let id = sessionStorage.getItem("veraSessionId");
    if (!id) {
      id = crypto.randomUUID();
      sessionStorage.setItem("veraSessionId", id);
    }
    return id;
  });

  // Historial inicial
  useEffect(() => {
    axios.get("/api/ai/chat/history", { headers: { "X-Session-ID": sessionId } })
      .then(res => {
        const hist = res.data?.messages || [];
        if (hist.length > 0) {
          setMessages([{ from: "ai", text: "🧠 Historial cargado desde el Observatorio IA." }, ...hist]);
        }
      })
      .catch(() => {});
  }, [sessionId]);

const fetchSuggestions = async (query: string) => {
  try {
    const res = await axios.get(`/api/ai/suggestions?q=${encodeURIComponent(query)}`);
    setSuggestions(res.data?.suggestions || []);
    setShowSuggestions(true); // ✅ importante, siempre mostrar si hay respuesta
  } catch {
    setShowSuggestions(false);
  }
};

// 💡 Cuando el usuario selecciona una sugerencia del autocompletado
const handleSuggestionClick = (s: Suggestion) => {
  setShowSuggestions(false);
  setSuggestions([]);
  setInput(""); // limpia el input

  // 📤 Envía el prompt directamente al chat
  handleSend(s.prompt, s.id);
};

const handleInputChange = (value: string) => {
  setInput(value);
  if (debounceRef.current) clearTimeout(debounceRef.current);

  if (value.trim().length >= 2) {
    debounceRef.current = setTimeout(() => {
      fetchSuggestions(value);
    }, 300);
  } else {
    setShowSuggestions(false);
  }
};


  // 🔊 Reproducir voz
  const speak = async (text: string) => {
    if (!voiceEnabled || !text) return;
    try {
      const res = await axios.post("/api/ai/voice/speak", { text });
      new Audio(res.data.url).play();
    } catch { /* silencioso */ }
  };

  // 🚀 Enviar mensaje
  const handleSend = async (customText?: string, trainingId?: number) => {
  const textToSend = customText ?? input.trim();
  if (!textToSend) return;

  setMessages(prev => [...prev, { from: "user", text: textToSend }]);
  setInput("");
  setLoading(true);
  setTypingText("Pensando...");

  try {
    let res;

    if (mode === "train") {
      res = await axios.post("/api/ai/training/start", { prompt: textToSend }, {
        headers: { "X-Session-ID": sessionId }
      });
      // ...
    } else {
      const payload = trainingId
        ? { training_id: trainingId, force_new: forceNew }
        : { message: textToSend, force_new: forceNew };

      res = await axios.post("/api/ai/chat", payload, {
        headers: { "X-Session-ID": sessionId }
      });

      const data = res.data;

      if (data.topic && data.result) {
        updateDashboard(data.result, data.topic, data.component ?? null);
        setMessages(prev => [
          ...prev,
          { from: "ai", text: `📘 **${data.prompt}**` },
          { from: "ai", text: data.explanation ?? "✅ Consulta procesada correctamente." },
        ]);
      } else if (data.message) {
        setMessages(prev => [...prev, { from: "ai", text: data.message }]);
      } else {
        setMessages(prev => [...prev, { from: "error", text: "⚠️ No se encontró un entrenamiento asociado." }]);
      }
    }
  } catch (e: any) {
    console.error("💥 Error en handleSend:", e);
    setMessages(prev => [...prev, { from: "error", text: "💥 Error al conectar con la IA." }]);
  } finally {
    setLoading(false);
    setTypingText("");
  }
};


  // 🎙️ Grabación
  const startRecording = async () => {
    if (recording || processingAudio) return;
    if (!navigator.mediaDevices?.getUserMedia) {
      setMessages(prev => [...prev, { from: "error", text: "🎙️ Tu navegador no permite grabar audio." }]);
      return;
    }

    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    const mediaRecorder = new MediaRecorder(stream);
    const chunks: BlobPart[] = [];

    mediaRecorder.ondataavailable = (e) => chunks.push(e.data);
    mediaRecorder.onstop = async () => {
      if (processingAudio) return;
      setProcessingAudio(true);

      const blob = new Blob(chunks, { type: "audio/webm" });
      const formData = new FormData();
      formData.append("audio", blob, "voz.webm");

      setMessages(prev => [...prev, { from: "ai", text: "🎙️ Transcribiendo tu audio..." }]);

      try {
        const res = await axios.post("/api/ai/voice/transcribe", formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        const text = res.data.text || "";
        if (text) handleSend(text);
        else setMessages(prev => [...prev, { from: "error", text: "⚠️ No se detectó voz." }]);
      } finally {
        setProcessingAudio(false);
      }
    };

    mediaRecorder.start();
    setRecorder(mediaRecorder);
    setRecording(true);
  };

  const stopRecording = () => {
    recorder?.stop();
    setRecording(false);
  };

  // 📎 Subir archivo
  const handleFileUpload = async (file: File) => {
    if (!file) return;
    if (file.size > 10 * 1024 * 1024) {
      setMessages((prev) => [...prev, { from: "error", text: "⚠️ El archivo supera los 10 MB." }]);
      return;
    }

    const form = new FormData();
    form.append("file", file);
    form.append("prompt", "Analiza el contenido del archivo");

    try {
      const res = await axios.post("/api/ai/file/analyze", form);
      const data = res.data;
      setMessages((prev) => [
        ...prev,
        { from: "user", text: `📎 Archivo enviado: ${file.name}` },
        { from: "ai", text: data.analysis },
      ]);
      if (voiceEnabled) speak(data.analysis);
    } catch {
      setMessages((prev) => [...prev, { from: "error", text: "💥 Error al analizar archivo." }]);
    }
  };
const [mode, setMode] = useState<"chat" | "train">(
  () => (localStorage.getItem("veraMode") as "chat" | "train") || "chat"
);

useEffect(() => localStorage.setItem("veraMode", mode), [mode]);

return {
  messages, input, typingText, loading, chatSize,
  recording, forceNew, voiceEnabled, suggestions, showSuggestions,
  chatEndRef,
  mode, setMode,
  setInput, setForceNew, setVoiceEnabled, setChatSize, setShowSuggestions,
  handleInputChange, handleSend, handleFileUpload,
  handleSuggestionClick, // ✅ nuevo
  startRecording, stopRecording,
};


}
