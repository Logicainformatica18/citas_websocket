// useAiChatLogic.ts
import { useState, useEffect, useRef } from "react";
import axios from "axios";
import { useDashboard } from "../../DashboardContext";
import { v4 as uuidv4 } from "uuid"; // ✅ más seguro y compatible

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
  const [forceNew, setForceNew] = useState<boolean>(
    () => JSON.parse(localStorage.getItem("veraForceNew") || "false")
  );
  const [voiceEnabled, setVoiceEnabled] = useState<boolean>(
    () => JSON.parse(localStorage.getItem("veraVoiceEnabled") || "true")
  );

  // Chat
  const [messages, setMessages] = useState<Message[]>([
    {
      from: "ai",
      text: "👋 Hola, soy **VERA**, tu analista del Observatorio ISIL. ¿Qué información deseas analizar hoy?",
    },
  ]);
  const [input, setInput] = useState<string>("");
  const [loading, setLoading] = useState<boolean>(false);
  const [typingText, setTypingText] = useState<string>("");
  const [suggestions, setSuggestions] = useState<Suggestion[]>([]);
  const [showSuggestions, setShowSuggestions] = useState<boolean>(false);

  // Dimensiones
  const [chatSize, setChatSize] = useState<{ width: number; height: number }>(() => {
    const saved = localStorage.getItem("veraChatSize");
    return saved ? JSON.parse(saved) : { width: 420, height: 580 };
  });

  // Grabación / archivos
  const [recording, setRecording] = useState<boolean>(false);
  const [recorder, setRecorder] = useState<MediaRecorder | null>(null);
  const [processingAudio, setProcessingAudio] = useState<boolean>(false);

  // Persistencia
  useEffect(() => localStorage.setItem("veraForceNew", JSON.stringify(forceNew)), [forceNew]);
  useEffect(() => localStorage.setItem("veraVoiceEnabled", JSON.stringify(voiceEnabled)), [voiceEnabled]);
  useEffect(() => localStorage.setItem("veraChatSize", JSON.stringify(chatSize)), [chatSize]);

  // Scroll automático
  useEffect(() => {
    chatEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, typingText, loading]);

  // Session (✅ reemplazado crypto.randomUUID → uuidv4)
  const [sessionId] = useState<string>(() => {
    let id = sessionStorage.getItem("veraSessionId");
    if (!id) {
      id = uuidv4(); // ✅ usa uuidv4 para compatibilidad total
      sessionStorage.setItem("veraSessionId", id);
    }
    return id;
  });

  // Historial inicial
  useEffect(() => {
    axios
      .get("/api/ai/chat/history", { headers: { "X-Session-ID": sessionId } })
      .then((res) => {
        const hist = res.data?.messages || [];
        if (hist.length > 0) {
          setMessages([{ from: "ai", text: "🧠 Historial cargado desde el Observatorio IA." }, ...hist]);
        }
      })
      .catch(() => {});
  }, [sessionId]);

  // ==========================
  // 📦 AUTOCOMPLETADO (prompts)
  // ==========================
  const fetchSuggestions = async (query: string) => {
    try {
      const res = await axios.get(`/api/ai/suggestions?q=${encodeURIComponent(query)}`);
      setSuggestions(res.data?.suggestions || []);
      setShowSuggestions(true);
    } catch {
      setShowSuggestions(false);
    }
  };

  const handleSuggestionClick = (s: Suggestion) => {
    setShowSuggestions(false);
    setSuggestions([]);
    setInput("");
    handleSend(s.prompt, s.id);
  };

  const handleInputChange = (value: string) => {
    setInput(value);
    if (debounceRef.current) clearTimeout(debounceRef.current);

    if (value.trim().length >= 2) {
      debounceRef.current = setTimeout(() => fetchSuggestions(value), 300);
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
    } catch {
      /* silencioso */
    }
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
  // ===========================================
  // 🧠 FASE 1: generar SQL a partir del prompt
  // ===========================================
  res = await axios.post(
    "/api/ai/training/start",
    { prompt: textToSend },
    { headers: { "X-Session-ID": sessionId } }
  );

  const data = res.data;

  setMessages((prev) => [
    ...prev,
    { from: "ai", text: data.message },
    { from: "ai", text: `\`\`\`sql\n${data.sql_generated}\n\`\`\`` },
  ]);

  // ===========================================
  // 🧪 FASE 2: validar automáticamente el SQL
  // ===========================================
  const testRes = await axios.post("/api/ai/training/test", {
    sql_training_id: data.sql_training_id,
    sql_query: data.sql_generated,
  });

  if (testRes.data.status === "ok") {
    setMessages((prev) => [
      ...prev,
      { from: "ai", text: `✅ SQL válida (${testRes.data.rows} filas)` },
      { from: "ai", text: "Generando explicación final..." },
    ]);

    // ===========================================
    // 🎓 FASE 3: generar explicación y CSV final
    // ===========================================
   // ===========================================
// 🎓 FASE 3: generar explicación + Excel + Voz
// ===========================================
// ===========================================
// 🎓 FASE 3: generar explicación + Excel + Voz + Guardado
// ===========================================
// ===========================================
// 🎓 FASE 3: generar explicación + Excel + Voz + Guardado
// ===========================================
// ===========================================
// 🎓 FASE 3: generar explicación + Excel + Voz (sin guardar aún)
// ===========================================
try {
  const finalizeRes = await axios.post("/api/ai/training/finalize", {
    sql_training_id: data.sql_training_id,
    prompt: textToSend,
    voice_enabled: voiceEnabled,
    save: false, // 👈 genera pero no guarda aún
  });

  const { message, ai_response, excel_path, voice_url } = finalizeRes.data;

  setMessages((prev) => [
    ...prev,
    { from: "ai", text: message },
    { from: "ai", text: ai_response },
    ...(excel_path ? [{ from: "ai", text: `📊 [Descargar resultados en Excel](${excel_path})` }] : []),
    ...(voice_url ? [{ from: "ai", text: `🔊 [Reproducir explicación en voz](${voice_url})` }] : []),
    {
      from: "ai",
      text: "💾 ¿Deseas guardar este entrenamiento?",
      saveIntent: { sql_training_id: data.sql_training_id, prompt: textToSend },
    },
  ]);
} catch (err: any) {
  console.error("💥 Error finalizando entrenamiento:", err);
  const msg =
    err.response?.data?.message ||
    err.response?.data?.error ||
    "💥 Error al finalizar entrenamiento.";
  setMessages((prev) => [...prev, { from: "error", text: msg }]);
}




  } else {
    setMessages((prev) => [
      ...prev,
      { from: "error", text: "⚠️ Error validando SQL." },
    ]);
  }
}
 else {
      // ===========================================
      // 💬 CHAT NORMAL
      // ===========================================
      const payload = trainingId
        ? { training_id: trainingId, force_new: forceNew }
        : { message: textToSend, force_new: forceNew };

      res = await axios.post("/api/ai/chat", payload, {
        headers: { "X-Session-ID": sessionId },
      });

      const data = res.data;
      if (data.topic && data.result) {
  updateDashboard(data.result, data.topic, data.component ?? null);

  const newMessages: Message[] = [
    { from: "ai", text: `📘 **${data.prompt}**` },
    { from: "ai", text: data.explanation ?? "✅ Consulta procesada correctamente." },
  ];

  // 📊 Si el backend devolvió Excel
  if (data.excel_path) {
    newMessages.push({
      from: "ai",
      text: `📊 [Descargar resultados en Excel](${data.excel_path})`,
    });
  }

  // 🔊 Si el backend devolvió audio
  if (data.voice_url) {
    newMessages.push({
      from: "ai",
      text: `🔊 [Reproducir explicación en voz](${data.voice_url})`,
    });

    // 🗣️ Reproduce automáticamente si está activado
    if (voiceEnabled) {
      new Audio(data.voice_url).play().catch(() => {});
    }
  }

  setMessages((prev) => [...prev, ...newMessages]);
}
else if (data.message) {
        setMessages(prev => [...prev, { from: "ai", text: data.message }]);
      } else {
        setMessages(prev => [...prev, { from: "error", text: "⚠️ No se encontró un entrenamiento asociado." }]);
      }
    }
  } catch (e: any) {
    console.error("💥 Error en handleSend:", e);
    const msg =
      e.response?.data?.message ||
      e.response?.data?.error ||
      "💥 Error al conectar con la IA.";
    setMessages(prev => [...prev, { from: "error", text: msg }]);
  } finally {
    setLoading(false);
    setTypingText("");
  }
};


  // 🎙️ Grabación
  const startRecording = async () => {
    if (recording || processingAudio) return;
    if (!navigator.mediaDevices?.getUserMedia) {
      setMessages((prev) => [...prev, { from: "error", text: "🎙️ Tu navegador no permite grabar audio." }]);
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

      setMessages((prev) => [...prev, { from: "ai", text: "🎙️ Transcribiendo tu audio..." }]);

      try {
        const res = await axios.post("/api/ai/voice/transcribe", formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        const text = res.data.text || "";
        if (text) handleSend(text);
        else setMessages((prev) => [...prev, { from: "error", text: "⚠️ No se detectó voz." }]);
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

  // 📊 Modo (chat/train)
  const [mode, setMode] = useState<"chat" | "train">(
    () => (localStorage.getItem("veraMode") as "chat" | "train") || "chat"
  );
  useEffect(() => localStorage.setItem("veraMode", mode), [mode]);
const handleSaveTraining = async (sql_training_id: number, prompt: string) => {
  try {
    const res = await axios.post("/api/ai/training/finalize", {
      sql_training_id,
      prompt,
      voice_enabled: voiceEnabled,
      save: true,
    });

    const { message, ai_response, excel_path, voice_url } = res.data;

    // ✅ Agrega nuevamente los elementos de Excel y voz si existen
    setMessages((prev) => [
      ...prev,
      { from: "ai", text: message },
      ...(excel_path
        ? [
            {
              from: "ai",
              text: `📊 [Descargar resultados en Excel](${excel_path})`,
            },
          ]
        : []),
      ...(voice_url
        ? [
            {
              from: "ai",
              text: `🔊 [Reproducir explicación en voz](${voice_url})`,
            },
          ]
        : []),
    ]);

    // 🔊 Reproduce voz si está activado
    if (voiceEnabled && voice_url) {
      new Audio(voice_url).play().catch(() => {});
    } else if (voiceEnabled && ai_response) {
      await speak(ai_response);
    }
  } catch (err: any) {
    console.error("💥 Error guardando entrenamiento:", err);
    const msg =
      err.response?.data?.message ||
      err.response?.data?.error ||
      "💥 Error al guardar el entrenamiento.";
    setMessages((prev) => [...prev, { from: "error", text: msg }]);
  }
};


  return {
    messages,
    input,
    typingText,
    loading,
    chatSize,
    recording,
    forceNew,
    voiceEnabled,
    suggestions,
    showSuggestions,
    chatEndRef,
    mode,
    setMode,
    setInput,
    setForceNew,
    setVoiceEnabled,
    setChatSize,
    setShowSuggestions,
    handleInputChange,
    handleSend,
    handleFileUpload,
    handleSuggestionClick,
    startRecording,
    stopRecording,
      handleSaveTraining,
  };
}
