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
    Plus,
    MessageCircle,
} from "lucide-react";
import HelpTooltip from "@/components/ui/HelpTooltip";



import { useAiChatLogic } from "./useAiChatLogic";
import ChartSelector from "./ChartSelector";
import HelpIcon from "@/components/ui/HelpIcon";

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
    const debounceRef = useRef<number | null>(null);


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
    useEffect(() => {
        if (suppressSuggestionsRef.current) {
            suppressSuggestionsRef.current = false;
            return;
        }

        if (!logic.input || logic.input.length < 3) {
            setShowSuggestions(false);
            return;
        }

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        debounceRef.current = window.setTimeout(() => {
            fetchSuggestions(logic.input);
        }, 300);

        return () => {
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }
        };
    }, [logic.input]);


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
    const [showActions, setShowActions] = useState(false);
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
    const suppressSuggestionsRef = useRef(false);

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

    const handleSendWrapped = () => {
        suppressSuggestionsRef.current = true;
        setShowSuggestions(false);
        logic.handleSend();
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

                    <HelpIcon
                        text="VERA es el asistente de inteligencia del Observatorio. Puede generar análisis, consultas SQL y gráficos a partir de tus preguntas."
                        pulseKey="vera-header"
                    />
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
    border-t px-5 py-4 relative
    ${logic.mode === "train"
                        ? "bg-[#ECFDF3] border-[#9FE3BF]"
                        : "bg-[#ECFAFD] border-[#A7E5F6]"
                    }
    dark:bg-[#40414f] dark:border-[#3f4144]
  `}
            >
                {/* ===============================
      HEADER — MODO
  =============================== */}
                <div className="flex justify-end mb-3">
                    <div className="flex items-center gap-2 w-full">


                        <button
                            onClick={() =>
                                logic.setMode(logic.mode === "chat" ? "train" : "chat")
                            }
                            className={`
    w-full
    flex items-center justify-center gap-2
    px-4 py-3
    rounded-full
    text-sm font-semibold
    transition
    cursor-pointer
    ${logic.mode === "chat"
                                    ? "bg-[#1CBCE8] text-white hover:bg-[#17a8cf]"
                                    : "bg-green-600 text-white hover:bg-green-700"
                                }
  `}
                        >
                            {logic.mode === "chat" ? (
                                <>
                                    💬 Estás en modo Conversando
                                </>
                            ) : (
                                <>
                                    🧠 Estás en modo Consultando Datos
                                </>
                            )}
                        </button>
                        <HelpIcon
                            text="Modo Conversar responde preguntas generales. Modo Consultar datos ejecuta consultas estructuradas sobre la base de datos del observatorio."
                            pulseKey="vera-mode"
                        />
                    </div>


                </div>

                {/* ===============================
      INPUT
  =============================== */}

                <div className="relative">

                    {/* PANEL DE ACCIONES (CLICK) */}
                    {showActions && (
                        <div
                            className="
          absolute bottom-full mb-3 left-4
          flex gap-2
          px-3 py-2
          rounded-xl
        bg-white dark:bg-[#2a2b31] 
border border-[#A7E5F6] dark:border-[#3f4144]
          shadow-lg
          animate-slide-up
          z-20
        "
                        >
                            {/* 🔊 Voz */}
                            <button
                                onClick={() => logic.setVoiceEnabled(!logic.voiceEnabled)}
                                className={`
            w-10 h-10 rounded-full
            flex items-center justify-center
            border cursor-pointer
          ${logic.voiceEnabled
  ? "bg-green-600 text-white border-green-600"
  : "bg-white dark:bg-[#34353c] border-[#A7E5F6] dark:border-[#3f4144] text-gray-700 dark:text-gray-200"
}
          `}
                                title="Voz"
                            >
                                <Volume2 size={16} />
                            </button>

                            {/* 🎤 Mic */}
                            <button
                                onClick={
                                    logic.recording ? logic.stopRecording : logic.startRecording
                                }
                                className={`
            w-10 h-10 rounded-full
            flex items-center justify-center
            border cursor-pointer
           ${logic.recording
  ? "bg-red-600 text-white border-red-600"
  : "bg-white dark:bg-[#34353c] border-[#A7E5F6] dark:border-[#3f4144] text-gray-700 dark:text-gray-200"
}
          `}
                                title="Grabar"
                            >
                                {logic.recording ? <Square size={16} /> : <Mic size={16} />}
                            </button>

                            {/* 📎 Adjuntar */}
                            <label
                                className="
            w-10 h-10 rounded-full
            flex items-center justify-center
           bg-white dark:bg-[#2a2b31] 
border border-[#A7E5F6] dark:border-[#3f4144]
            cursor-pointer
          "
                                title="Adjuntar archivo"
                            >
                                <Paperclip size={16} />
                                <input
                                    type="file"
                                    hidden
                                    onChange={(e) =>
                                        e.target.files?.[0] &&
                                        logic.handleFileUpload(e.target.files[0])
                                    }
                                />
                            </label>
                        </div>
                    )}

                    {/* TEXTAREA */}
                    <textarea
                        rows={2}
                        value={logic.input}
                        onChange={(e) => {
                            logic.handleInputChange(e.target.value);
                            e.target.style.height = "auto";
                            e.target.style.height =
                                Math.min(e.target.scrollHeight, 160) + "px";
                        }}
                        onFocus={() => setShowActions(false)}
                        onKeyDown={(e) => {
                            if (e.key === "Enter" && !e.shiftKey) {
                                e.preventDefault();
                                handleSendWrapped();
                            }
                        }}

                        className={`
        w-full resize-none
        px-5 py-4 pr-28
        rounded-2xl text-sm border
        min-h-[72px]
       ${logic.mode === "train"
  ? "bg-[#E6F8EE] dark:bg-[#1f2d26] border-[#9FE3BF] dark:border-[#2e5f46]"
  : "bg-white dark:bg-[#1f2937] border-[#A7E5F6] dark:border-[#334155]"
}
text-gray-800 dark:text-gray-200
      `}
                        placeholder={
                            logic.mode === "train"
                                ? "Describe la consulta que quieres enseñar a VERA…"
                                : "Escribe tu mensaje…"
                        }
                    />
                    {showSuggestions && suggestions.length > 0 && (
                        <div
                            className="
      absolute left-0 right-0 bottom-full mb-2
      bg-white dark:bg-[#2a2b31]
      border border-[#A7E5F6] dark:border-[#3f4144]
      rounded-xl shadow-lg
      max-h-48 overflow-y-auto
      z-30
    "
                        >
                            {suggestions.map((s) => (
                                <button
                                    key={s.id}
                                    onClick={() => {
                                        suppressSuggestionsRef.current = true;
                                        logic.handleInputChange(s.prompt);
                                        setShowSuggestions(false);
                                    }}

                                    className="
          w-full text-left px-4 py-2
          hover:bg-[#ECFAFD] dark:hover:bg-[#3a3b40]
          text-sm text-gray-800 dark:text-gray-200
          transition
        "
                                >
                                    <div className="font-medium">{s.prompt}</div>
                                    {s.description && (
                                        <div className="text-xs text-gray-500 dark:text-gray-400">
                                            {s.description}
                                        </div>
                                    )}
                                </button>
                            ))}
                        </div>
                    )}

                    {/* BOTÓN ACCIONES */}
                    <HelpTooltip text="Acciones adicionales: activar voz, grabar audio o adjuntar archivos.">
                        <button
                            onClick={() => setShowActions((v) => !v)}
                            title="Acciones"
                            className="
        absolute right-14 bottom-4
        w-10 h-10
        rounded-full
        flex items-center justify-center
      bg-white dark:bg-[#34353c]
border border-[#A7E5F6] dark:border-[#3f4144]
text-gray-700 dark:text-gray-200
        cursor-pointer
      "
                        >
                            <Plus size={16} />
                        </button>
                    </HelpTooltip>
                    {/* ENVIAR */}
                    <button
                        onClick={handleSendWrapped}
                        disabled={!logic.input.trim()}
                        title="Enviar"
                        className="
        absolute right-3 bottom-4
        w-10 h-10
        rounded-full
        flex items-center justify-center
        bg-[#1CBCE8] text-white
        disabled:opacity-50
        cursor-pointer
      "
                    >
                        <Send size={16} />
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
