import React, { useEffect, useMemo, useRef, useState } from "react";
import { X, Copy, Download, Search } from "lucide-react";

type OcrModalPayload = {
  paymentId?: number;
  text?: string;
};

export default function OcrTextModal() {
  const [open, setOpen] = useState(false);
  const [paymentId, setPaymentId] = useState<number | undefined>(undefined);
  const [text, setText] = useState<string>("");
  const [q, setQ] = useState<string>("");
  const contentRef = useRef<HTMLDivElement>(null);

  // Escuchar el evento global disparado desde PaymentsIndex
  useEffect(() => {
    const handler = (e: Event) => {
      const detail = (e as CustomEvent<OcrModalPayload>).detail || {};
      setPaymentId(detail.paymentId);
      setText(detail.text ?? "");
      setQ("");
      setOpen(true);
      // foco al buscar
      setTimeout(() => {
        const input = document.getElementById("ocr-modal-search") as HTMLInputElement | null;
        input?.focus();
      }, 100);
    };

    window.addEventListener("open-ocr-modal", handler as EventListener);
    return () => window.removeEventListener("open-ocr-modal", handler as EventListener);
  }, []);

  const close = () => setOpen(false);

  const copyToClipboard = async () => {
    try {
      await navigator.clipboard.writeText(text);
      // feedback rápido
      const btn = document.getElementById("ocr-copy-btn");
      if (btn) {
        btn.classList.add("ring-2", "ring-emerald-400");
        setTimeout(() => btn.classList.remove("ring-2", "ring-emerald-400"), 400);
      }
    } catch {
      alert("No se pudo copiar al portapapeles.");
    }
  };

  const downloadTxt = () => {
    const blob = new Blob([text || ""], { type: "text/plain;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    const name = paymentId ? `ocr_payment_${paymentId}.txt` : "ocr_text.txt";
    a.href = url;
    a.download = name;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  };

  // Resaltado simple de coincidencias (case-insensitive)
  const highlighted = useMemo(() => {
    if (!q.trim()) return text;
    try {
      const esc = q.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
      const re = new RegExp(`(${esc})`, "gi");
      return text.replace(re, "««HIGHLIGHT:$1»»"); // marcas temporales
    } catch {
      return text;
    }
  }, [text, q]);

  // Render del texto con <mark> para coincidencias
  const renderWithMarks = (s: string) => {
    const parts = s.split(/««HIGHLIGHT:|»»/g); // separa por marcas
    const out: React.ReactNode[] = [];
    for (let i = 0; i < parts.length; i++) {
      if (i % 2 === 1) {
        // parte resaltada
        out.push(
          <mark key={i} className="bg-yellow-200 text-black rounded px-0.5">
            {parts[i]}
          </mark>
        );
      } else {
        out.push(<span key={i}>{parts[i]}</span>);
      }
    }
    return out;
  };

  if (!open) return null;

  return (
    <div
      aria-modal
      role="dialog"
      className="fixed inset-0 z-[999] flex items-center justify-center"
    >
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/50"
        onClick={close}
        aria-hidden
      />

      {/* Modal */}
      <div className="relative mx-4 w-full max-w-4xl rounded-xl bg-white shadow-2xl ring-1 ring-black/10">
        {/* Header */}
        <div className="flex items-center justify-between border-b px-4 py-3">
          <h2 className="text-lg font-semibold">
            OCR del voucher {paymentId ? `#${paymentId}` : ""}
          </h2>
          <button
            onClick={close}
            className="rounded p-1 hover:bg-gray-100"
            aria-label="Cerrar"
            title="Cerrar"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Toolbar */}
        <div className="flex flex-col gap-2 border-b px-4 py-3 md:flex-row md:items-center md:justify-between">
          <div className="flex items-center gap-2">
            <div className="relative">
              <Search className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                id="ocr-modal-search"
                className="w-64 rounded-md border border-gray-300 pl-9 pr-3 py-2 text-sm"
                placeholder="Buscar en el texto…"
                value={q}
                onChange={(e) => setQ(e.target.value)}
              />
            </div>
          </div>
          <div className="flex items-center gap-2">
            <button
              id="ocr-copy-btn"
              onClick={copyToClipboard}
              className="inline-flex items-center gap-2 rounded-md bg-gray-100 px-3 py-2 text-sm hover:bg-gray-200"
              title="Copiar todo"
            >
              <Copy className="h-4 w-4" /> Copiar
            </button>
            <button
              onClick={downloadTxt}
              className="inline-flex items-center gap-2 rounded-md bg-gray-100 px-3 py-2 text-sm hover:bg-gray-200"
              title="Descargar .txt"
            >
              <Download className="h-4 w-4" /> Descargar
            </button>
          </div>
        </div>

        {/* Content */}
        <div className="max-h-[65vh] overflow-auto px-4 py-4">
          <div
            ref={contentRef}
            className="whitespace-pre-wrap break-words font-mono text-sm leading-relaxed text-gray-900"
          >
            {renderWithMarks(highlighted)}
          </div>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-2 border-t px-4 py-3">
          <button
            onClick={close}
            className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>
  );
}