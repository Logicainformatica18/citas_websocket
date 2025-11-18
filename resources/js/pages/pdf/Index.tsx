import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { FileText, Upload, Eye } from 'lucide-react';
import Swal from 'sweetalert2';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Documentos PDF', href: '/pdf' },
];

// Fecha formateada
function formatDate(dateString?: string | null): string {
  if (!dateString) return '-';
  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '-';
    return date.toLocaleDateString('es-PE', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    });
  } catch {
    return '-';
  }
}

type PdfDoc = {
  id: number;
  title: string;
  source?: string | null;
  file_path: string;
  total_pages?: number | null;
  processed: boolean;
  created_at?: string;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url?: string | null;
  prev_page_url?: string | null;
};

export default function PDFIndex() {
  const { documents: initialPagination } = usePage<{ documents: Pagination<PdfDoc> }>().props;

  const [items, setItems] = useState<PdfDoc[]>([]);
  const [pagination, setPagination] = useState(initialPagination);
  const [pdfFile, setPdfFile] = useState<File | null>(null);
  const [title, setTitle] = useState("");

  useEffect(() => {
    setItems(initialPagination.data);
    setPagination(initialPagination);
  }, [initialPagination]);

  const normalizePagePayload = (payload: any): Pagination<PdfDoc> => {
    const pager = payload?.documents ?? payload ?? {};
    const data: PdfDoc[] = Array.isArray(pager)
      ? pager
      : (pager?.data ?? []);

    return {
      data,
      current_page: pager?.current_page ?? 1,
      last_page: pager?.last_page ?? 1,
      next_page_url: pager?.next_page_url ?? null,
      prev_page_url: pager?.prev_page_url ?? null,
    };
  };

  const fetchPage = async (url: string) => {
    try {
      const res = await axios.get(url);
      const norm = normalizePagePayload(res?.data ?? null);
      setItems(norm.data);
      setPagination(norm);
    } catch (e) {
      console.error('Error al cargar página', e);
      alert('No se pudo cargar la página.');
    }
  };

  const uploadPdf = async (e: any) => {
    e.preventDefault();

    if (!pdfFile) {
      Swal.fire("Ups!", "Selecciona un archivo PDF", "warning");
      return;
    }

    const formData = new FormData();
    formData.append("pdf", pdfFile);
    formData.append("title", title || pdfFile.name);

    try {
      await axios.post('/pdf/upload', formData);
      Swal.fire("Listo!", "El PDF se está procesando.", "success");

      setPdfFile(null);
      setTitle("");

      fetchPage('/pdf'); // Refrescar lista
    } catch (error) {
      console.error(error);
      Swal.fire("Error", "No se pudo subir el PDF.", "error");
    }
  };

  const getStatusBadge = (processed: boolean) => {
    if (processed) {
      return (
        <span className="px-3 py-1 bg-green-600 text-white text-xs rounded-full">
          Procesado
        </span>
      );
    }
    return (
      <span className="px-3 py-1 bg-yellow-500 text-white text-xs rounded-full animate-pulse">
        Procesando…
      </span>
    );
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen transition-colors duration-200">

        {/* HEADER */}
        <div className="flex items-center justify-between mb-6 border-b border-gray-200 dark:border-gray-800 pb-4">
          <h1 className="text-3xl font-semibold flex items-center gap-2">
            <FileText className="w-7 h-7 text-blue-600 dark:text-blue-400" />
            Documentos PDF
          </h1>

          {/* Botón para seleccionar archivo PDF */}
          <label className="px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-md shadow-md flex items-center gap-2 transition cursor-pointer">
            <Upload className="w-4 h-4" />
            Subir PDF
            <input
              type="file"
              accept="application/pdf"
              className="hidden"
              onChange={(e) => {
                setPdfFile(e.target.files?.[0] ?? null);
                setTitle(e.target.files?.[0]?.name ?? "");
              }}
            />
          </label>
        </div>

        {/* CARD para subir */}
        {pdfFile && (
          <form
            onSubmit={uploadPdf}
            className="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 mb-6 rounded-lg shadow space-y-4"
          >
            <h2 className="font-semibold text-lg">Nuevo Documento PDF</h2>

            <div>
              <label className="block font-semibold text-sm mb-1">Título</label>
              <input
                type="text"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                className="w-full border p-2 rounded-md dark:bg-gray-900 dark:border-gray-700"
                placeholder="Título del documento"
              />
            </div>

            <div className="flex justify-end gap-3">
              <button
                type="button"
                onClick={() => {
                  setPdfFile(null);
                  setTitle("");
                }}
                className="px-4 py-2 bg-gray-300 dark:bg-gray-700 rounded-md"
              >
                Cancelar
              </button>

              <button
                type="submit"
                className="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md"
              >
                Procesar PDF
              </button>
            </div>
          </form>
        )}

        {/* TABLA */}
        <div className="overflow-x-auto rounded-lg shadow border border-gray-200 dark:border-gray-800">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
            <thead className="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm uppercase">
              <tr>
                <th className="px-4 py-2">Título</th>
                <th className="px-4 py-2">Origen</th>
                <th className="px-4 py-2 text-center">Páginas</th>
                <th className="px-4 py-2 text-center">Estado</th>
                <th className="px-4 py-2 text-right">Acciones</th>
              </tr>
            </thead>

            <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
              {items.map((item) => (
                <tr key={item.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/60 transition">
                  <td className="px-4 py-2 font-semibold">{item.title}</td>
                  <td className="px-4 py-2">{item.source ?? '-'}</td>
                  <td className="px-4 py-2 text-center">{item.total_pages ?? '-'}</td>
                  <td className="px-4 py-2 text-center">{getStatusBadge(item.processed)}</td>

                  <td className="px-4 py-2 text-right">
                    <Link
                      href={route("pdf.show", item.id)}
                      className="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg inline-flex items-center gap-1"
                    >
                      <Eye className="w-4 h-4" /> Ver detalle
                    </Link>
                  </td>
                </tr>
              ))}

              {items.length === 0 && (
                <tr>
                  <td className="px-4 py-6 text-center text-gray-500 dark:text-gray-400" colSpan={5}>
                    No hay documentos cargados.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Paginación */}
        <div className="flex justify-center mt-6 gap-1">
          {Array.from({ length: pagination.last_page }, (_, i) => i + 1)
            .filter(
              (page) =>
                page <= 2 ||
                page >= pagination.last_page - 1 ||
                (page >= pagination.current_page - 2 &&
                  page <= pagination.current_page + 2)
            )
            .map((page, idx, arr) => {
              const prev = arr[idx - 1];
              const gap = prev && page - prev > 1;

              return (
                <span key={page} className="flex">
                  {gap && (
                    <span className="px-2 py-1 text-gray-400 dark:text-gray-600">…</span>
                  )}

                  <button
                    onClick={() => fetchPage(`/pdf?page=${page}`)}
                    className={`px-3 py-1 rounded-md text-sm font-medium transition ${
                      page === pagination.current_page
                        ? 'bg-blue-600 text-white shadow'
                        : 'bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600'
                    }`}
                    disabled={page === pagination.current_page}
                  >
                    {page}
                  </button>
                </span>
              );
            })}
        </div>
      </div>
    </AppLayout>
  );
}
