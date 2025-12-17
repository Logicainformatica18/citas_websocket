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
import { useAiChatLogic } from "./useAiChatLogic";
import ChartSelector from "./ChartSelector";

export default function AiChatView({ docked = false }: { docked?: boolean }) {

    const logic = useAiChatLogic();
    const [visible, setVisible] = useState(true);
    const [resizing, setResizing] = useState(false);
    const resizeRef = useRef<{ x: number; y: number; width: number; height: number } | null>(null);

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
        if (docked) return; // ⛔ no control externo cuando está dockeado

        window.vera = {
            open: () => setVisible(true),
            close: () => setVisible(false),
            toggle: () => setVisible((v) => !v),
            isVisible: () => visible,
        };
    }, [visible, docked]);


    return (
        <div
            className={`
    ${docked
                    ? "relative w-full h-full flex flex-col"
                    : "fixed bottom-4 right-4 z-50 flex flex-col"}
    transition-all duration-300 ease-in-out
    ${!docked && !visible ? "opacity-0 scale-90 pointer-events-none" : "opacity-100 scale-100"}

    bg-white dark:bg-[#202123]
    border border-[#A7E5F6] dark:border-[#3f4144]
    rounded-xl
  `}
            style={
                docked
                    ? {} // ⬅️ dockeado: el layout manda
                    : {
                        width: logic.chatSize.width,
                        height: logic.chatSize.height,
                        boxShadow: "0 8px 25px rgba(0,0,0,0.4)",
                    }
            }
        >


            {/* HEADER */}
            {/* HEADER */}
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

                {!docked && (
                    <button
                        onClick={() => setVisible(false)}
                        className="
        text-gray-500 hover:text-gray-900
        dark:text-gray-400 dark:hover:text-white
        transition
      "
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
                                className="
            mt-3 self-start
            bg-green-600 hover:bg-green-700
            text-white px-3 py-1.5
            rounded-md text-sm transition
          "
                            >
                                💾 Guardar entrenamiento
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



         <div
  className="
    px-4 py-3
    border-t border-[#A7E5F6] dark:border-[#3f4144]
    bg-[#ECFAFD] dark:bg-[#343541]
    text-sm text-gray-700 dark:text-gray-300
    space-y-3
  "
>
  {/* FILA 1 → MODO */}
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

  {/* FILA 2 → ACCIONES */}
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

  {/* FILA 3 → CONTROLES DE VOZ */}
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
</div>




         <div
  className="
    px-3 py-3
    bg-[#ECFAFD] dark:bg-[#40414f]
    border-t border-[#A7E5F6] dark:border-[#3f4144]
    flex items-center gap-2
  "
>
  {/* Adjuntar */}
  <label
    className="
      shrink-0
      cursor-pointer p-2 rounded-lg
      bg-white dark:bg-[#565869]
      hover:bg-[#D5F3FB] dark:hover:bg-[#6b6d7b]
      flex items-center justify-center
    "
  >
    <Paperclip size={16} className="text-gray-700 dark:text-gray-200" />
    <input
      type="file"
      hidden
      onChange={(e) =>
        e.target.files?.[0] && logic.handleFileUpload(e.target.files[0])
      }
    />
  </label>

  {/* Texto */}
  <input
    value={logic.input}
    onChange={(e) => logic.handleInputChange(e.target.value)}
    onKeyDown={(e) => e.key === "Enter" && logic.handleSend()}
    className="
      flex-1
      text-sm px-4 py-2 rounded-full
      bg-white dark:bg-[#202123]
      text-gray-900 dark:text-gray-100
      placeholder-gray-500
      border border-[#A7E5F6] dark:border-[#3f4144]
      focus:outline-none focus:ring-2 focus:ring-[#1CBCE8]
    "
    placeholder={
      logic.mode === "train"
        ? "Describe la consulta que quieres enseñar a VERA..."
        : "Escribe tu mensaje..."
    }
  />

  {/* Mic */}
  <button
    onClick={logic.recording ? logic.stopRecording : logic.startRecording}
    className={
      logic.recording
        ? "shrink-0 p-2 rounded-lg bg-red-600 text-white"
        : "shrink-0 p-2 rounded-lg bg-white dark:bg-[#565869] text-gray-700 dark:text-gray-100 hover:bg-[#D5F3FB] dark:hover:bg-[#6b6d7b]"
    }
  >
    {logic.recording ? <Square size={16} /> : <Mic size={16} />}
  </button>

  {/* Enviar */}
  <button
    onClick={() => logic.handleSend()}
    disabled={logic.loading || !logic.input.trim()}
    className="
      shrink-0
      px-4 py-2
      rounded-lg
      bg-[#1CBCE8] hover:bg-[#1399BE]
      text-white
      disabled:opacity-50
      transition
    "
    title="Enviar"
  >
    <Send size={16} />
  </button>
</div>




            {/* 🟦 Esquina de redimensionamiento */}
            {!docked && (
                <div
                    onMouseDown={startResize}
                    className="absolute bottom-0 right-0 w-4 h-4 cursor-se-resize bg-transparent"
                />
            )}
        </div>
    );
}
