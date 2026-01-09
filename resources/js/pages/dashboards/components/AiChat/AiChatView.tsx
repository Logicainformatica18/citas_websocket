import React, { useState, useRef, useEffect } from "react";
import ReactMarkdown from "react-markdown";
import remarkGfm from "remark-gfm";
import rehypeRaw from "rehype-raw";
import rehypeHighlight from "rehype-highlight";
import {
    Send,
    X,
    Paperclip,
    Mic,
    Square,
    RefreshCw,
    Volume2,
    Database,
} from "lucide-react";
import { MessageCircle} from "lucide-react"
import { useAiChatLogic } from "./useAiChatLogic";
import ChartSelector from "./ChartSelector";

interface AiChatViewProps {
  embedded?: boolean;
}



export default function AiChatView({ embedded = false }: AiChatViewProps) {



    const logic = useAiChatLogic();
    const [visible, setVisible] = useState(true);
    const [resizing, setResizing] = useState(false);
    const resizeRef = useRef<{ x: number; y: number; width: number; height: number } | null>(null);

    type Suggestion = {
        id: number;
        prompt: string;
        description?: string;
        component?: string;
    };

    const [suggestions, setSuggestions] = useState<Suggestion[]>([]);
    const [showSuggestions, setShowSuggestions] = useState(false);
    const debounceRef = useRef<NodeJS.Timeout | null>(null);

    // 🎨 Colores (adaptado a tema claro / oscuro estilo Lovable)
    const colorByRole = {
        user: `
  bg-[#1CBCE8] text-white
  dark:bg-[#343541]
  self-end ml-auto
  mt-1
`,

        ai: `
        bg-[#ECFAFD] text-gray-900
        dark:bg-[#444654] dark:text-gray-100
        self-start
    `,
        error: `
        bg-red-600 text-white
        self-start
    `,
    };


    // 🧱 Redimensionamiento
    const startResize = (e: React.MouseEvent) => {
        e.preventDefault();
        resizeRef.current = {
            x: e.clientX,
            y: e.clientY,
            width: logic.chatSize.width,
            height: logic.chatSize.height,
        };
        setResizing(true);
    };

    const handleResize = (e: MouseEvent) => {
        if (!resizing || !resizeRef.current) return;
        const dx = e.clientX - resizeRef.current.x;
        const dy = e.clientY - resizeRef.current.y;
        const newWidth = Math.max(350, resizeRef.current.width + dx);
        const newHeight = Math.max(420, resizeRef.current.height + dy);
        logic.setChatSize({ width: newWidth, height: newHeight });
    };

    const stopResize = () => setResizing(false);

    useEffect(() => {
        if (resizing) {
            window.addEventListener("mousemove", handleResize);
            window.addEventListener("mouseup", stopResize);
        } else {
            window.removeEventListener("mousemove", handleResize);
            window.removeEventListener("mouseup", stopResize);
        }
        return () => {
            window.removeEventListener("mousemove", handleResize);
            window.removeEventListener("mouseup", stopResize);
        };
    }, [resizing]);

    // ⚙️ Control externo global (sin eventos duplicados)
  useEffect(() => {
  if (embedded) return;

  window.vera = {
    open: () => setVisible(true),
    close: () => setVisible(false),
    toggle: () => setVisible((v) => !v),
    isVisible: () => visible,
  };
}, [visible, embedded]);


    const fetchSuggestions = async (query: string) => {
        try {
            const res = await fetch(
                `/api/ai/suggestions?q=${encodeURIComponent(query)}`
            );
            const data = await res.json();
            setSuggestions(data.suggestions ?? []);
            setShowSuggestions(true);
        } catch (err) {
            console.error("❌ Error cargando sugerencias:", err);
            setShowSuggestions(false);
        }
    };


    return (
<div
  className={`
    flex flex-col w-full h-full
    bg-white dark:bg-[#202123]
    ${embedded
      ? "relative rounded-none border-l border-[#D9EEF5] dark:border-gray-700"
      : "fixed bottom-4 right-4 z-50 rounded-xl border shadow-xl"}
    transition-all
    ${!embedded && !visible ? "opacity-0 scale-90 pointer-events-none" : ""}
  `}
>





<div
  className="
    flex justify-between items-center px-4 py-2
    bg-[#ECFAFD] dark:bg-[#343541]
    border-b border-[#A7E5F6] dark:border-[#3f4144]
  "
>


                <div className="flex items-center gap-2 font-semibold text-sm cursor-default">
                    <span className="text-gray-900 dark:text-gray-200">🤖 VERA</span>
                    <span className="text-xs text-gray-500 dark:text-gray-400">
                        | Observatorio ISIL
                    </span>
                </div>

               {!embedded && (
  <button
    onClick={() => setVisible(false)}
    className="text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
  >
    <X size={16} />
  </button>
)}

            </div>


            {/* MENSAJES */}
            {/* MENSAJES */}
            <div
                className="
    flex-1 overflow-y-auto
    px-4 py-4 pr-4
    space-y-4
  "
                style={{ scrollbarGutter: "stable" }}
            >


                {logic.messages.map((m, i) => (
                    <div
                        key={i}
                        className={`
        flex flex-col w-fit max-w-[80%] sm:max-w-[75%] p-3 rounded-lg shadow-sm whitespace-pre-wrap
        ${colorByRole[m.from]}
      `}
                    >
                        {/* 🔹 Render normal del texto */}
                        <div className="prose dark:prose-invert text-[15px] leading-relaxed text-gray-900 dark:text-gray-100">
                            <ReactMarkdown
                                remarkPlugins={[remarkGfm]}
                                rehypePlugins={[rehypeRaw, rehypeHighlight]}
                                components={{
                                    pre: ({ node, ...props }) => (
                                        <pre
                                            {...props}
                                            className="
                  bg-[#F1F5F9] dark:bg-[#2a2b31]
                  p-3 rounded-lg overflow-x-auto
                  text-gray-900 dark:text-gray-100
                "
                                        />
                                    ),
                                    code: ({ inline, children, ...props }) => (
                                        <code
                                            {...props}
                                            className={
                                                inline
                                                    ? "bg-[#E2E8F0] dark:bg-[#2a2b31] px-1.5 py-0.5 rounded text-[13px]"
                                                    : "block text-sm"
                                            }
                                        >
                                            {children}
                                        </code>
                                    ),
                                }}
                            >
                                {m.text}
                            </ReactMarkdown>
                        </div>

                        {/* 🧩 Selector de gráfico antiguo (de prueba) */}
                        {m.chartSelector && (
                            <div className="mt-3">
                                <ChartSelector
                                    trainingId={m.chartSelector.training_id}
                                    chartTypes={m.chartSelector.chartTypes}
                                />
                            </div>
                        )}
                        {/* 💾 Botón de guardar entrenamiento */}
                     {m.saveIntent && (
  <button
    onClick={() =>
      logic.handleSaveTraining(
        m.saveIntent.sql_training_id,
        m.saveIntent.prompt
      )
    }
    disabled={logic.savingTrainingId === m.saveIntent.sql_training_id}
    className="
      mt-3 self-start
      flex items-center gap-2
      px-3 py-1.5
      rounded-md text-sm font-medium
      bg-green-600 hover:bg-green-700
      text-white
      disabled:opacity-60 disabled:cursor-not-allowed
      transition
    "
  >
    {logic.savingTrainingId === m.saveIntent.sql_training_id ? (
      <>
        <span
          className="h-4 w-4 rounded-full border-2 border-white border-t-transparent animate-spin"
        />
        Guardando...
      </>
    ) : (
      <>💾 Guardar entrenamiento</>
    )}
  </button>
)}


                        {/* 📊 Nuevo bloque: botones de tipo de gráfico */}
                        {m.showChartOption && (
                            <div className="mt-3 flex flex-wrap gap-2">
                                {["bar", "line", "pie", "radar"].map((type) => (
                                    <button
                                        key={type}
                                        onClick={() =>
                                            logic.handleGenerateChart(
                                                m.showChartOption.training_id,
                                                type
                                            )
                                        }
                                        className="
                bg-blue-600 hover:bg-blue-700
                text-white px-3 py-1.5
                rounded-md text-sm transition
              "
                                    >
                                        {type.toUpperCase()}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                ))}

                {logic.typingText && (
                    <div className="flex items-center gap-2 text-gray-400 text-sm animate-pulse">
                        <div className="w-2 h-2 bg-blue-400 rounded-full animate-bounce" />
                        {logic.typingText}
                    </div>
                )}

                <div ref={logic.chatEndRef} />
            </div>



        {/* <div
  className={`
    px-3 py-2 border-t transition-colors
    ${
      logic.mode === "train"
        ? "bg-[#ECFDF3] border-[#9FE3BF]"
        : "bg-[#ECFAFD] border-[#A7E5F6]"
    }
    dark:bg-[#40414f] dark:border-[#3f4144]
  `}
>


                <div className="flex items-center gap-2">
                    <Database size={16} className="text-[#1CBCE8]" />
                    <select
                        value={logic.mode}
                        onChange={(e) => logic.setMode(e.target.value as "chat" | "train")}
                        className="
        w-full
        bg-white dark:bg-[#202123]
        text-gray-900 dark:text-gray-200
        px-3 py-2 rounded-lg
        border border-[#A7E5F6] dark:border-[#3f4144]
        focus:ring-2 focus:ring-[#1CBCE8]
      "
                    >
                        <option value="chat">💬 Conversar</option>
                        <option value="train">🎓 Entrenamiento de Datos</option>
                    </select>
                </div>


                <div className="grid grid-cols-2 gap-2">
                    <button
                        onClick={() => logic.setForceNew(!logic.forceNew)}
                        className={
                            logic.forceNew
                                ? "px-3 py-2 rounded-lg bg-[#1CBCE8] text-white flex items-center justify-center gap-1"
                                : "px-3 py-2 rounded-lg bg-white dark:bg-[#202123] text-gray-700 dark:text-gray-300 hover:bg-[#D5F3FB] dark:hover:bg-[#2a2b2f] flex items-center justify-center gap-1"
                        }
                    >
                        <RefreshCw size={14} />
                        Forzar nueva
                    </button>

                    <button
                        onClick={() => logic.setVoiceEnabled(!logic.voiceEnabled)}
                        className={
                            logic.voiceEnabled
                                ? "px-3 py-2 rounded-lg bg-green-600 text-white flex items-center justify-center gap-1"
                                : "px-3 py-2 rounded-lg bg-white dark:bg-[#202123] text-gray-700 dark:text-gray-300 hover:bg-[#D5F3FB] dark:hover:bg-[#2a2b2f] flex items-center justify-center gap-1"
                        }
                    >
                        <Volume2 size={14} />
                        Voz
                    </button>
                </div>


                {logic.voiceEnabled && (
                    <div className="grid grid-cols-2 gap-2">
                        <button
                            onClick={logic.toggleAudioPlayback}
                            className="
          px-3 py-2 rounded-lg
          bg-white dark:bg-[#202123]
          hover:bg-[#D5F3FB] dark:hover:bg-[#2a2b2f]
          flex items-center justify-center gap-1
        "
                        >
                            {logic.isAudioPlaying ? "⏸️ Pausar" : "▶️ Reanudar"}
                        </button>

                        <button
                            onClick={logic.stopAudio}
                            className="
          px-3 py-2 rounded-lg
          bg-white dark:bg-[#202123]
          hover:bg-[#D5F3FB] dark:hover:bg-[#2a2b2f]
          flex items-center justify-center gap-1
        "
                        >
                            ⏹️ Detener
                        </button>
                    </div>
                )}
            </div> */}


<div
  className={`
    border-t px-4 py-4
    ${
      logic.mode === "train"
        ? "bg-[#ECFDF3] border-[#9FE3BF]"
        : "bg-[#ECFAFD] border-[#A7E5F6]"
    }
    dark:bg-[#40414f] dark:border-[#3f4144]
  `}
>
  {/* ================= GRID CONTENEDOR ================= */}
  <div
    className="
      grid grid-cols-[40px_1fr_40px_40px]
      grid-rows-[auto_auto]
      gap-x-3 gap-y-3
      items-center
    "
  >
    {/* ================= VOZ (ARRIBA IZQUIERDA) ================= */}
    <button
      onClick={() => logic.setVoiceEnabled(!logic.voiceEnabled)}
      className={`
        col-start-1 row-start-1
        w-10 h-10
        flex items-center justify-center
        rounded-lg border cursor-pointer transition
        ${
          logic.voiceEnabled
            ? "bg-green-600 text-white border-green-600"
            : "bg-white border-[#A7E5F6] hover:bg-[#D5F3FB]"
        }
      `}
      title="Voz"
    >
      <Volume2 size={18} />
    </button>

    {/* ================= MODO (ARRIBA DERECHA) ================= */}
    <button
      onClick={() =>
        logic.setMode(logic.mode === "chat" ? "train" : "chat")
      }
      className={`
        col-start-4 row-start-1
        justify-self-end
        px-4 py-2 rounded-full text-sm
        border cursor-pointer transition
        ${
          logic.mode === "train"
            ? "bg-[#1CBCE8] text-white border-[#1CBCE8]"
            : "bg-white text-[#1CBCE8] border-[#1CBCE8] hover:bg-[#D5F3FB]"
        }
      `}
    >
      {logic.mode === "train" ? "Conversar" : "Entrenar con datos"}
    </button>

    {/* ================= ADJUNTAR (ABAJO IZQUIERDA) ================= */}
    <label
      className="
        col-start-1 row-start-2
        w-10 h-10
        flex items-center justify-center
        rounded-lg border bg-white
        border-[#A7E5F6]
        hover:bg-[#D5F3FB]
        cursor-pointer
      "
      title="Adjuntar archivo"
    >
      <Paperclip size={18} />
      <input
        type="file"
        hidden
        onChange={(e) =>
          e.target.files?.[0] && logic.handleFileUpload(e.target.files[0])
        }
      />
    </label>

    {/* ================= TEXTAREA CENTRAL ================= */}
    <textarea
      rows={2}
      value={logic.input}
      onChange={(e) => {
        logic.handleInputChange(e.target.value);
        e.target.style.height = "auto";
        e.target.style.height = Math.min(e.target.scrollHeight, 140) + "px";
      }}
      onKeyDown={(e) => {
        if (e.key === "Enter" && !e.shiftKey) {
          e.preventDefault();
          logic.handleSend();
        }
      }}
      className={`
        col-start-2 col-span-1 row-start-2
        resize-none
        px-4 py-3
        rounded-2xl text-sm border
        min-h-[56px] max-h-[140px]
        ${
          logic.mode === "train"
            ? "bg-[#E6F8EE] border-[#9FE3BF]"
            : "bg-white border-[#A7E5F6]"
        }
        focus:outline-none focus:ring-2 focus:ring-[#1CBCE8]
      `}
      placeholder={
        logic.mode === "train"
          ? "Describe la consulta que quieres enseñar a VERA…"
          : "Escribe tu mensaje…"
      }
    />

    {/* ================= MIC (ABAJO DERECHA - 1) ================= */}
    <button
      onClick={logic.recording ? logic.stopRecording : logic.startRecording}
      className={`
        col-start-3 row-start-2
        w-10 h-10
        flex items-center justify-center
        rounded-lg border cursor-pointer transition
        ${
          logic.recording
            ? "bg-red-600 text-white border-red-600"
            : "bg-white border-[#A7E5F6] hover:bg-[#D5F3FB]"
        }
      `}
      title="Grabar voz"
    >
      {logic.recording ? <Square size={18} /> : <Mic size={18} />}
    </button>

    {/* ================= ENVIAR (ABAJO DERECHA - 2) ================= */}
    <button
      onClick={logic.handleSend}
      disabled={!logic.input.trim()}
      className="
        col-start-4 row-start-2
        w-10 h-10
        flex items-center justify-center
        rounded-lg
        bg-[#1CBCE8] text-white
        hover:bg-[#17a8cf]
        disabled:opacity-50 disabled:cursor-not-allowed
        cursor-pointer
      "
      title="Enviar"
    >
      <Send size={18} />
    </button>
  </div>
</div>






           {!embedded && (

                <div
                    onMouseDown={startResize}
                    className="absolute bottom-0 right-0 w-4 h-4 cursor-se-resize bg-transparent"
                />
            )}
        </div>
    );
}
