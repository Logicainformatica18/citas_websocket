// useAiChatLogic.ts
import { useState, useEffect, useRef } from "react";
import axios from "axios";
import { useDashboard } from "../../DashboardContext";
import { v4 as uuidv4 } from "uuid"; // ✅ más seguro y compatible

export type Message = {
  from: "user" | "ai" | "error";
  text: string;

  // 🔹 Opcional: botones de guardar entrenamiento
  saveIntent?: {
    sql_training_id: number;
    prompt: string;
  };

  // 🔹 Opcional: selector de gráfico (usado tras ejecutar una consulta)
  chartSelector?: {
    training_id: number;
    chartTypes: string[];
  };

  // 🔹 Opcional: botones de tipo de gráfico (tras guardar entrenamiento)
  showChartOption?: {
    training_id: number;
  };
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
// 🔊 Control de audio activo
const [currentAudio, setCurrentAudio] = useState<HTMLAudioElement | null>(null);
const [isAudioPlaying, setIsAudioPlaying] = useState<boolean>(false);

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
      playAudio(res.data.url);

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

// 🧠 1️⃣ Mostrar todos los mensajes generados
const newMessages: Message[] = [
  { from: "ai", text: message },
  { from: "ai", text: ai_response },
  ...(excel_path ? [{ from: "ai", text: `📊 [Descargar resultados en Excel](${excel_path})` }] : []),
  ...(voice_url ? [{ from: "ai", text: `🔊 [Reproducir explicación en voz](${voice_url})` }] : []),
{
  from: "ai",
  text: "💾 ¿Deseas guardar este entrenamiento?",
  saveIntent: {
    sql_training_id: finalizeRes.data.sql_training_id ?? data.sql_training_id ?? 0,
    prompt: textToSend,
  },
},

];
setMessages((prev) => [...prev, ...newMessages]);



// 🎨 Nuevo paso: ofrecer tipos de gráfico al usuario
if (excel_path) {
  try {
    const chartTypesRes = await axios.get("/api/chart-types");
    const chartTypes = chartTypesRes.data;

    // Envía un mensaje especial con el selector de gráficos
    // setMessages((prev) => [
    //   ...prev,
    //   {
    //     from: "ai",
    //     text: "📊 ¿Cómo deseas visualizar los datos de la consulta?",
    //     chartSelector: {
    //       training_id: finalizeRes.data.training_id ?? data.sql_training_id,
    //       chartTypes,
    //     },
    //   },
    // ]);

  } catch (err) {
    console.warn("⚠️ No se pudieron cargar los tipos de gráfico:", err);
  }
}





// 🗣️ 2️⃣ Reproducir voz automática (si está activa)
if (voiceEnabled) {
  try {
    // si el backend ya devolvió una voz
  if (voice_url) {
  playAudio(voice_url);
}

    // si no vino audio, generarlo desde el texto
    else if (ai_response) {
      const ttsRes = await axios.post("/api/ai/voice/speak", { text: ai_response });
      const ttsUrl = ttsRes.data?.url;
     if (ttsUrl) {
  playAudio(ttsUrl);
}

    }
  } catch (err) {
    console.warn("⚠️ Error generando voz en entrenamiento:", err);
  }
}

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
    // ===========================================
// 💬 CHAT NORMAL (adaptado con voz automática)
// ===========================================
const payload = trainingId
  ? { training_id: trainingId, force_new: forceNew }
  : { message: textToSend, force_new: forceNew };

res = await axios.post("/api/ai/chat", payload, {
  headers: { "X-Session-ID": sessionId },
});

const data = res.data;

// 🧠 Si el backend devuelve datos estructurados (dashboard)
if (data.topic && data.result) {
  updateDashboard(data.result, data.topic, data.component ?? null);

  const newMessages: Message[] = [
    { from: "ai", text: `📘 **${data.prompt}**` },
    { from: "ai", text: data.explanation ?? "✅ Consulta procesada correctamente." },
  ];

  // 📊 Excel si existe
  if (data.excel_path) {
    newMessages.push({
      from: "ai",
      text: `📊 [Descargar resultados en Excel](${data.excel_path})`,
    });
  }

  // 🔊 Si el backend devuelve voz directa
  if (data.voice_url) {
    newMessages.push({
      from: "ai",
      text: `🔊 [Reproducir explicación en voz](${data.voice_url})`,
    });

  if (voiceEnabled) {
  playAudio(data.voice_url);
}

  }

  // 📩 Añadir mensajes al chat
  setMessages(prev => [...prev, ...newMessages]);

  // 🗣️ Generar TTS si no vino voice_url
  const textForVoice =
    data.explanation || data.message || data.prompt || "Respuesta generada correctamente.";

  // 🗣️ 2️⃣ Reproducir voz (solo una vez)
// 🔊 Si hay voz disponible, mostrar enlace pero NO reproducir automáticamente
if (data.voice_url) {
  setMessages((prev) => [
    ...prev,
    {
      from: "ai",
      text: `🔊 [Haz clic aquí para escuchar la explicación](${data.voice_url})`,
    },
  ]);
}
else if (voiceEnabled && data.explanation) {
  try {
    const ttsRes = await axios.post("/api/ai/voice/speak", { text: data.explanation });
    const ttsUrl = ttsRes.data?.url;
    if (ttsUrl) {
      setMessages((prev) => [
        ...prev,
        { from: "ai", text: `🔊 [Reproducir explicación en voz](${ttsUrl})` },
      ]);
    }
  } catch (err) {
    console.warn("⚠️ Error generando voz al guardar:", err);
  }
}



}
// 🧩 Si solo viene texto plano
else if (data.message) {
  setMessages(prev => [...prev, { from: "ai", text: data.message }]);

  // 🔊 Generar voz también en respuestas simples
if (voiceEnabled && data.message) {
  try {
    const ttsRes = await axios.post("/api/ai/voice/speak", { text: data.message });
    const voiceUrl = ttsRes.data?.url;

    if (voiceUrl) {
      playAudio(voiceUrl);
    }
  } catch (err) {
    console.warn("⚠️ Error generando voz simple:", err);
  }
}

}
// ⚠️ Si no hay respuesta válida
else {
  setMessages(prev => [
    ...prev,
    { from: "error", text: "⚠️ No se encontró un entrenamiento asociado." },
  ]);
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

    const { training_id, ai_response, excel_path, voice_url } = res.data;

    // ✅ Guarda el ID del entrenamiento para permitir generar gráfico
    if (training_id) {
      localStorage.setItem("veraLastTrainingId", training_id.toString());
      setMessages((prev) => [
        ...prev,
        {
          from: "ai",
          text: "💾 Entrenamiento guardado correctamente. Ahora puedes generar un gráfico con estos datos.",
          showChartOption: { training_id },
        },
      ]);
    } else {
      setMessages((prev) => [
        ...prev,
        { from: "ai", text: "💾 Entrenamiento guardado correctamente." },
      ]);
    }

    // 🔊 Reproduce voz una sola vez (sin duplicar mensajes)
    if (voiceEnabled && (voice_url || ai_response)) {
      try {
        let audioUrl = voice_url;
        if (!audioUrl && ai_response) {
          const ttsRes = await axios.post("/api/ai/voice/speak", { text: ai_response });
          audioUrl = ttsRes.data?.url;
        }
        if (audioUrl) playAudio(audioUrl);
      } catch (err) {
        console.warn("⚠️ Error reproduciendo voz al guardar:", err);
      }
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

const handleGenerateChart = async (trainingId: number, chartType: string) => {
  try {
    setMessages(prev => [
      ...prev,
      { from: "ai", text: "📊 Generando gráfico con los datos del entrenamiento..." },
    ]);

    const res = await axios.post("/api/ai/dashboard-widgets/from-training", {
      training_id: trainingId,
      chart_type: chartType,
    });

    const { message, widget_id } = res.data;

    setMessages(prev => [
      ...prev,
      { from: "ai", text: `${message} (Widget ID: ${widget_id})` },
    ]);
  } catch (err: any) {
    console.error("💥 Error generando gráfico:", err);
    const msg =
      err.response?.data?.message ||
      err.response?.data?.error ||
      "💥 Error al generar gráfico.";
    setMessages(prev => [...prev, { from: "error", text: msg }]);
  }
};

// 🎧 Reproduce un audio y detiene el anterior si existe
const playAudio = (url: string) => {
  try {
    // Detiene el anterior si estaba sonando
    if (currentAudio) {
      currentAudio.pause();
      setIsAudioPlaying(false);
    }

    const audio = new Audio(url);
    audio.crossOrigin = "anonymous";
    audio.volume = 1.0;
    audio.play()
      .then(() => setIsAudioPlaying(true))
      .catch(err => console.warn("🔇 Autoplay bloqueado:", err.message));

    setCurrentAudio(audio);

    // Cuando termine
    audio.onended = () => setIsAudioPlaying(false);
  } catch (err) {
    console.warn("⚠️ Error al reproducir audio:", err);
  }
};

// 🔇 Pausar o reanudar el audio actual
const toggleAudioPlayback = () => {
  if (!currentAudio) return;
  if (currentAudio.paused) {
    currentAudio.play();
    setIsAudioPlaying(true);
  } else {
    currentAudio.pause();
    setIsAudioPlaying(false);
  }
};

// 🛑 Detener audio completamente
const stopAudio = () => {
  if (currentAudio) {
    currentAudio.pause();
    currentAudio.currentTime = 0;
    setIsAudioPlaying(false);
  }
};
// 🧹 Limpieza de audio al desmontar el componente
useEffect(() => {
  return () => {
    if (currentAudio) {
      currentAudio.pause();
      currentAudio.currentTime = 0;
    }
  };
}, [currentAudio]);


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
  playAudio,
  toggleAudioPlayback,
  stopAudio,
  isAudioPlaying,
  handleGenerateChart,
};

}
