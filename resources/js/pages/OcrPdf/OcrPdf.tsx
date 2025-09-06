import React, { useState } from 'react';
import axios from 'axios';

export default function OcrPdf() {
  // ---- CSRF + axios defaults ----
  const csrf =
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

  axios.defaults.withCredentials = true;
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

  // nuevos estados
  const [rows, setRows] = useState<string[]>([]);
  const [structured, setStructured] = useState<any[]>([]);

  // 🚀 estado para JSON manual
  const [jsonInput, setJsonInput] = useState<string>('');

  const handleUploadSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    resetState();

    if (!file) {
      setMsg('Selecciona un PDF.');
      return;
    }

    const fd = new FormData();
    fd.append('pdf', file);

    setLoading(true);
    try {
      const { data } = await axios.post(
        route ? route('ocr.pdf.upload') : '/ocr/pdf/upload',
        fd,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );

      setText(data.text || '');
      setPages(data.pages ?? null);
      setOutPrefix(data.out_prefix || '');
      setObjects(data.result_objects || []);
      setRows(data.rows || []);
      setStructured(data.structured || []);
      setMsg('OCR completado ✔');
    } catch (err: any) {
      setMsg(err?.response?.data?.message || err?.message || 'Falló el OCR');
    } finally {
      setLoading(false);
    }
  };

  const handleExistingSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    resetState();

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
      setRows(data.rows || []);
      setStructured(data.structured || []);
      setMsg('OCR completado ✔');
    } catch (err: any) {
      setMsg(err?.response?.data?.message || err?.message || 'Falló el OCR');
    } finally {
      setLoading(false);
    }
  };

  // 🚀 procesar JSON pegado manualmente
  const handleJsonSubmit = () => {
    try {
      const parsed = JSON.parse(jsonInput);
      if (Array.isArray(parsed)) {
        setStructured(parsed);
        setMsg('JSON cargado correctamente ✔');
      } else {
        setMsg('El JSON debe ser un array de objetos.');
      }
    } catch (e) {
      setMsg('JSON inválido ❌');
    }
  };

  const resetState = () => {
    setMsg('');
    setText('');
    setPages(null);
    setOutPrefix('');
    setObjects([]);
    setRows([]);
    setStructured([]);
  };

  return (
    <div className="max-w-6xl mx-auto p-6">
      <h1 className="text-2xl font-bold mb-4">OCR de PDF (Google Vision + JSON manual)</h1>

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
        >
          {loading ? 'Procesando…' : 'Procesar PDF'}
        </button>
      </form>

      {/* OCR sobre objeto existente */}
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

      {/* Pegado de JSON manual */}
      <div className="mb-8 border rounded p-4">
        <h2 className="font-semibold mb-3">Pegar JSON manualmente</h2>
        <textarea
          value={jsonInput}
          onChange={(e) => setJsonInput(e.target.value)}
          rows={6}
          className="w-full border rounded p-2 font-mono text-xs"
          placeholder='Pega aquí el JSON estructurado [{"fecha": "03-06", ...}]'
        />
        <button
          onClick={handleJsonSubmit}
          className="mt-2 px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700"
        >
          Cargar JSON
        </button>
      </div>

      {/* Mensajes */}
      {msg && <div className="mb-4 p-3 bg-gray-100 rounded">{msg}</div>}

      {/* Tabla */}
      {structured.length > 0 && (
        <div className="border rounded p-4 space-y-4">
          <b>Tabla de transacciones:</b>
          <table className="w-full border mt-2 text-sm">
            <thead>
              <tr className="bg-gray-200">
                <th className="p-2 border">Fecha Proc</th>
                <th className="p-2 border">Fecha Valor</th>
                <th className="p-2 border">Descripción</th>
                <th className="p-2 border">Lugar</th>
                <th className="p-2 border">Suc-Age</th>
                <th className="p-2 border">Num Op</th>
                <th className="p-2 border">Hora</th>
                <th className="p-2 border">Origen</th>
                <th className="p-2 border">Tipo</th>
                <th className="p-2 border">Cargo</th>
                <th className="p-2 border">Abono</th>
                <th className="p-2 border">Saldo</th>
              </tr>
            </thead>
            <tbody>
              {structured.map((row, idx) => (
                <tr key={idx}>
                  <td className="p-2 border">{row.fecha || row.fecha_proc}</td>
                  <td className="p-2 border">{row.fecha_valor}</td>
                  <td className="p-2 border">{row.descripcion}</td>
                  <td className="p-2 border">{row.lugar}</td>
                  <td className="p-2 border">{row.suc_age}</td>
                  <td className="p-2 border">{row.num_op}</td>
                  <td className="p-2 border">{row.hora}</td>
                  <td className="p-2 border">{row.origen}</td>
                  <td className="p-2 border">{row.tipo}</td>
                  <td className="p-2 border">{row.cargo}</td>
                  <td className="p-2 border">{row.abono}</td>
                  <td className="p-2 border">{row.saldo || row.saldo_contable}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
