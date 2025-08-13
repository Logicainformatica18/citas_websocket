import React, { useState } from 'react';
import axios from 'axios'; // 👈 igual que en Areas

export default function OcrPdf() {
  // ---- CSRF + axios defaults (mismo patrón que tu Areas) ----
  const csrf =
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

  axios.defaults.withCredentials = true; // enviar cookies de sesión
  axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
  if (csrf) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
  }

  const [file, setFile] = useState<File | null>(null);
  const [loading, setLoading] = useState(false);
  const [msg, setMsg] = useState<string>('');
  const [text, setText] = useState<string>('');
  const [pages, setPages] = useState<number | null>(null);
  const [outPrefix, setOutPrefix] = useState<string>('');
  const [objects, setObjects] = useState<string[]>([]);
  const [existingObject, setExistingObject] = useState<string>('MODELO-DE-OFICIO.pdf');

  const handleUploadSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setMsg('');
    setText('');
    setPages(null);
    setOutPrefix('');
    setObjects([]);

    if (!file) {
      setMsg('Selecciona un PDF.');
      return;
    }

    const fd = new FormData();
    fd.append('pdf', file);

    setLoading(true);
    try {
      const { data } = await axios.post(
        route ? route('ocr.pdf.upload') : '/ocr/pdf/upload', // si no usas Ziggy, usa la URL literal
        fd,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );

      setText(data.text || '');
      setPages(data.pages ?? null);
      setOutPrefix(data.out_prefix || '');
      setObjects(data.result_objects || []);
      setMsg('OCR completado ✔');
    } catch (err: any) {
      setMsg(err?.response?.data?.message || err?.message || 'Falló el OCR');
    } finally {
      setLoading(false);
    }
  };

  const handleExistingSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setMsg('');
    setText('');
    setPages(null);
    setOutPrefix('');
    setObjects([]);

    if (!existingObject.trim()) {
      setMsg('Indica el nombre del objeto en el bucket (ej: MODELO-DE-OFICIO.pdf)');
      return;
    }

    setLoading(true);
    try {
      const { data } = await axios.post(
        route ? route('ocr.pdf.existing') : '/ocr/pdf/existing',
        { object: existingObject.trim() }
      );

      setText(data.text || '');
      setPages(data.pages ?? null);
      setOutPrefix(data.out_prefix || '');
      setObjects(data.result_objects || []);
      setMsg('OCR completado ✔');
    } catch (err: any) {
      setMsg(err?.response?.data?.message || err?.message || 'Falló el OCR');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-4xl mx-auto p-6">
      <h1 className="text-2xl font-bold mb-4">OCR de PDF (Google Vision)</h1>

      {/* Subir y OCR */}
      <form onSubmit={handleUploadSubmit} className="mb-8 border rounded p-4">
        <h2 className="font-semibold mb-3">Subir un PDF y procesar</h2>
        <input
          type="file"
          accept="application/pdf"
          onChange={(e) => setFile(e.target.files?.[0] ?? null)}
          className="block mb-3"
        />
        <button
          type="submit"
          disabled={loading || !file}
          className="px-4 py-2 bg-blue-600 text-white rounded disabled:opacity-50"
          title={!file ? 'Selecciona un PDF' : ''}
        >
          {loading ? 'Procesando…' : 'Procesar PDF'}
        </button>
      </form>

      {/* OCR sobre objeto existente en GCS */}
      <form onSubmit={handleExistingSubmit} className="mb-8 border rounded p-4">
        <h2 className="font-semibold mb-3">Procesar un PDF ya existente en el bucket</h2>
        <div className="flex gap-2">
          <input
            type="text"
            placeholder="ej: MODELO-DE-OFICIO.pdf o carpeta/archivo.pdf"
            value={existingObject}
            onChange={(e) => setExistingObject(e.target.value)}
            className="flex-1 border rounded px-3 py-2"
          />
          <button
            type="submit"
            disabled={loading || !existingObject.trim()}
            className="px-4 py-2 bg-green-600 text-white rounded disabled:opacity-50"
          >
            {loading ? 'Procesando…' : 'Procesar existente'}
          </button>
        </div>
      </form>

      {/* Mensajes */}
      {msg && <div className="mb-4 p-3 bg-gray-100 rounded">{msg}</div>}

      {/* Resultados */}
      {(pages !== null || outPrefix || objects.length || text) && (
        <div className="border rounded p-4">
          <div className="mb-2"><b>Páginas detectadas:</b> {pages ?? '-'}</div>
          <div className="mb-2"><b>Prefijo de salida:</b> {outPrefix || '-'}</div>
          <div className="mb-2">
            <b>Archivos JSON generados:</b>
            <ul className="list-disc ml-5">
              {objects.map((o) => <li key={o}>{o}</li>)}
            </ul>
          </div>
          <div className="mb-2"><b>Texto OCR:</b></div>
          <textarea className="w-full border rounded p-2" rows={14} value={text} readOnly />
        </div>
      )}
    </div>
  );
}
