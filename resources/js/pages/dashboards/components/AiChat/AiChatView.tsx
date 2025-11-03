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

export default function AiChatView() {
  const logic = useAiChatLogic();
  const [visible, setVisible] = useState(true);
  const [resizing, setResizing] = useState(false);
  const resizeRef = useRef<{ x: number; y: number; width: number; height: number } | null>(null);

  // 🎨 Colores
  const colorByRole = {
    user: "bg-[#343541] text-white self-end ml-auto",
    ai: "bg-[#444654] text-gray-100 self-start",
    error: "bg-red-700 text-white self-start",
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
    window.vera = {
      open: () => setVisible(true),
      close: () => setVisible(false),
      toggle: () => setVisible((v) => !v),
      isVisible: () => visible,
    };
  }, [visible]);

  return (
    <div
      className={`fixed bottom-4 right-4 z-50 transition-all duration-300 ease-in-out ${
        visible ? "opacity-100 scale-100" : "opacity-0 scale-90 pointer-events-none"
      }`}
      style={{
        width: logic.chatSize.width,
        height: logic.chatSize.height,
        backgroundColor: "#202123",
        border: "1px solid #3f4144",
        borderRadius: "0.75rem",
        boxShadow: "0 8px 25px rgba(0,0,0,0.4)",
        display: "flex",
        flexDirection: "column",
        overflow: "hidden",
        backdropFilter: "blur(12px)",
      }}
    >
      {/* HEADER */}
      <div className="flex justify-between items-center px-4 py-2 bg-[#343541] border-b border-[#3f4144]">
        <div className="flex items-center gap-2 text-gray-200 font-semibold text-sm cursor-default">
          🤖 VERA <span className="text-xs text-gray-400">| Observatorio ISIL</span>
        </div>
        <button
          onClick={() => setVisible(false)}
          className="p-1 rounded hover:bg-[#565869] transition text-gray-300 hover:text-white"
          title="Cerrar chat"
        >
          <X size={16} />
        </button>
      </div>

      {/* MENSAJES */}
      <div className="flex-1 overflow-y-auto px-5 py-4 space-y-4">
        {logic.messages.map((m, i) => (
          <div
            key={i}
            className={`flex flex-col w-fit max-w-[90%] p-3 rounded-lg shadow-sm whitespace-pre-wrap ${colorByRole[m.from]}`}
          >
            <div className="prose prose-invert text-[15px] leading-relaxed">
              <ReactMarkdown
                remarkPlugins={[remarkGfm]}
                rehypePlugins={[rehypeRaw, rehypeHighlight]}
                components={{
                  pre: ({ node, ...props }) => (
                    <pre {...props} className="bg-[#2a2b31] p-3 rounded-lg overflow-x-auto" />
                  ),
                  code: ({ inline, children, ...props }) => (
                    <code
                      {...props}
                      className={
                        inline
                          ? "bg-[#2a2b31] px-1.5 py-0.5 rounded text-[13px]"
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

            {m.saveIntent && (
              <button
                onClick={() =>
                  logic.handleSaveTraining(
                    m.saveIntent.sql_training_id,
                    m.saveIntent.prompt
                  )
                }
                className="mt-3 self-start bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-md text-sm transition"
              >
                💾 Guardar entrenamiento
              </button>
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

      {/* TOOLBAR */}
      <div className="px-4 py-2 border-t border-[#3f4144] bg-[#343541] flex flex-wrap items-center gap-3 text-sm text-gray-300">
        <div className="flex items-center gap-2">
          <Database size={16} className="text-blue-400" />
          <select
            value={logic.mode}
            onChange={(e) => logic.setMode(e.target.value as "chat" | "train")}
            className="bg-[#202123] text-gray-200 text-sm px-2 py-1 rounded border border-[#3f4144] focus:ring-1 focus:ring-blue-500"
          >
            <option value="chat">💬 Conversar</option>
            <option value="train">🎓 Entrenamiento SQL</option>
          </select>
        </div>

        <button
          onClick={() => logic.setForceNew(!logic.forceNew)}
          className={`flex items-center gap-1 px-2 py-1 rounded transition ${
            logic.forceNew
              ? "bg-blue-600 text-white"
              : "bg-[#202123] text-gray-300 hover:bg-[#2a2b2f]"
          }`}
        >
          <RefreshCw size={14} /> Forzar nueva
        </button>

        <button
          onClick={() => logic.setVoiceEnabled(!logic.voiceEnabled)}
          className={`flex items-center gap-1 px-2 py-1 rounded transition ${
            logic.voiceEnabled
              ? "bg-green-600 text-white"
              : "bg-[#202123] text-gray-300 hover:bg-[#2a2b2f]"
          }`}
        >
          <Volume2 size={14} /> Voz
        </button>
        {/* 🎧 Controles de reproducción */}
{logic.voiceEnabled && (
  <div className="flex items-center gap-2 ml-auto">
    <button
      onClick={logic.toggleAudioPlayback}
      className="flex items-center gap-1 px-2 py-1 rounded bg-[#202123] hover:bg-[#2a2b2f] text-gray-300 transition"
      title={logic.isAudioPlaying ? "Pausar voz" : "Reanudar voz"}
    >
      {logic.isAudioPlaying ? "⏸️ Pausar" : "▶️ Reanudar"}
    </button>

    <button
      onClick={logic.stopAudio}
      className="flex items-center gap-1 px-2 py-1 rounded bg-[#202123] hover:bg-[#2a2b2f] text-gray-300 transition"
      title="Detener voz"
    >
      ⏹️ Detener
    </button>
  </div>
)}

      </div>

      {/* INPUT */}
      <div className="relative p-3 bg-[#40414f] border-t border-[#3f4144] flex items-center gap-2">
        <label className="cursor-pointer p-2 bg-[#565869] hover:bg-[#6b6d7b] rounded transition flex items-center">
          <Paperclip size={16} className="text-gray-200" />
          <input
            type="file"
            hidden
            onChange={(e) =>
              e.target.files?.[0] && logic.handleFileUpload(e.target.files[0])
            }
          />
        </label>

        <input
          value={logic.input}
          onChange={(e) => logic.handleInputChange(e.target.value)}
          onKeyDown={(e) => e.key === "Enter" && logic.handleSend()}
          className="flex-1 text-sm px-3 py-2 rounded-lg bg-[#202123] text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder={
            logic.mode === "train"
              ? "Describe la consulta que quieres enseñar a VERA..."
              : "Escribe tu mensaje..."
          }
        />

        <button
          onClick={logic.recording ? logic.stopRecording : logic.startRecording}
          className={`p-2 rounded transition ${
            logic.recording
              ? "bg-red-600 text-white"
              : "bg-[#565869] hover:bg-[#6b6d7b] text-gray-100"
          }`}
        >
          {logic.recording ? <Square size={16} /> : <Mic size={16} />}
        </button>

        <button
          onClick={() => logic.handleSend()}
          disabled={logic.loading}
          className="p-2 bg-blue-600 hover:bg-blue-700 text-white rounded transition disabled:opacity-50"
        >
          <Send size={16} />
        </button>
      </div>

      {/* 🟦 Esquina de redimensionamiento */}
      <div
        onMouseDown={startResize}
        className="absolute bottom-0 right-0 w-4 h-4 cursor-se-resize bg-transparent"
      />
    </div>
  );
}
