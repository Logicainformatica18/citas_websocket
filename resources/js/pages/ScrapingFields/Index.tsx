import AppLayout from '@/layouts/app-layout';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import { Paintbrush, Trash2, Plus } from 'lucide-react';
import ScrapingFieldModal from './Modal';

const breadcrumbs = [{ title: 'Scrapings', href: '/scrapings' }];

type Scraping = {
  id: number;
  name: string;
};

type Field = {
  id: number;
  field_name: string;
  selector_type: string;
  selector_value: string;
  attr?: string | null;
  path?: string;
  parent_id?: number | null;
  parent?: { id: number; field_name: string } | null;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url: string | null;
  prev_page_url: string | null;
};

export default function ScrapingFieldsIndex() {
  const { scraping, fields: initialPagination } = usePage<{
    scraping: Scraping;
    fields: Pagination<Field>;
  }>().props;

  const [fields, setFields] = useState<Field[]>(initialPagination.data || []);
  const [pagination, setPagination] = useState(initialPagination);

  const [showModal, setShowModal] = useState(false);
  const [editField, setEditField] = useState<Field | null>(null);

  const handleSaved = (saved: Field) => {
    setFields((prev) => {
      const exists = prev.find((f) => f.id === saved.id);
      return exists ? prev.map((f) => (f.id === saved.id ? saved : f)) : [saved, ...prev];
    });
    setEditField(null);
  };

  const fetchField = async (id: number) => {
    try {
      const res = await axios.get(`/scrapings/${scraping.id}/fields/${id}`);
      setEditField(res.data.field);
      setShowModal(true);
    } catch (e) {
      console.error('Error al cargar campo', e);
    }
  };

  const fetchPage = async (url: string) => {
    try {
      const res = await axios.get(url);
      setFields(res.data.fields.data);
      setPagination(res.data.fields);
    } catch (e) {
      console.error('Error al cargar página', e);
    }
  };

  return (
    <AppLayout
      breadcrumbs={[
        ...breadcrumbs,
        { title: scraping.name, href: `/scrapings/${scraping.id}/fields` },
      ]}
    >
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-4">
          Campos de: <span className="text-blue-600">{scraping.name}</span>
        </h1>

        <button
          onClick={() => {
            setEditField(null);
            setShowModal(true);
          }}
          className="mb-4 px-4 py-2 flex items-center gap-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
        >
          <Plus className="w-4 h-4" /> Nuevo Campo
        </button>

        <div className="overflow-x-auto mt-4">
          <table className="min-w-full divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
            <thead className="bg-gray-100 dark:bg-gray-800">
              <tr>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">ID</th>
                <th className="px-4 py-2">Nombre</th>
                <th className="px-4 py-2">Padre</th>
                <th className="px-4 py-2">Tipo</th>
                <th className="px-4 py-2">Valor</th>
                <th className="px-4 py-2">Atributo</th>
                <th className="px-4 py-2">Ruta (path)</th>
              </tr>
            </thead>
            <tbody>
              {fields.map((f) => (
                <tr
                  key={f.id}
                  className="border-t hover:bg-gray-50 dark:hover:bg-gray-700 text-black dark:text-white"
                >
                  <td className="px-4 py-2 space-x-2 text-sm">
                    <button
                      onClick={() => fetchField(f.id)}
                      className="text-blue-600 hover:underline dark:text-blue-400 flex items-center gap-1"
                    >
                      <Paintbrush className="w-4 h-4" /> Editar
                    </button>
                    <button
                      onClick={async () => {
                        if (confirm(`¿Eliminar campo ${f.field_name}?`)) {
                          try {
                            await axios.delete(`/scrapings/${scraping.id}/fields/${f.id}`);
                            setFields((prev) => prev.filter((u) => u.id !== f.id));
                          } catch (e) {
                            alert('Error al eliminar');
                            console.error(e);
                          }
                        }
                      }}
                      className="text-red-600 hover:underline dark:text-red-400 flex items-center gap-1"
                    >
                      <Trash2 className="w-4 h-4" /> Eliminar
                    </button>
                  </td>

                  <td className="px-4 py-2">{f.id}</td>
                  <td className="px-4 py-2">{f.field_name}</td>
                  <td className="px-4 py-2">
                    {f.parent ? `${f.parent.field_name} (id #${f.parent.id})` : '—'}
                  </td>
                  <td className="px-4 py-2">{f.selector_type}</td>
                  <td className="px-4 py-2">{f.selector_value}</td>
                  <td className="px-4 py-2">{f.attr ?? '-'}</td>
                  <td className="px-4 py-2">{f.path || '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="flex justify-center mt-6 space-x-2">
          {[...Array(pagination.last_page)].map((_, index) => {
            const page = index + 1;
            return (
              <button
                key={page}
                onClick={() =>
                  fetchPage(`/scrapings/${scraping.id}/fields/fetch?page=${page}`)
                }
                className={`px-3 py-1 rounded text-sm font-medium transition ${
                  pagination.current_page === page
                    ? 'bg-blue-600 text-white'
                    : 'bg-gray-200 text-gray-800 hover:bg-gray-300'
                }`}
                disabled={pagination.current_page === page}
              >
                {page}
              </button>
            );
          })}
        </div>
      </div>

      {showModal && (
        <ScrapingFieldModal
          scrapingId={scraping.id}
          field={editField}
          onClose={() => {
            setShowModal(false);
            setEditField(null);
          }}
          onSaved={handleSaved}
        />
      )}
    </AppLayout>
  );
}
