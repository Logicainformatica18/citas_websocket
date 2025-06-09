import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import ArticleModal from './modal';
import axios from 'axios';
import { Paintbrush, Trash2 } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Artículos', href: '/articles' },
];

type Article = {
    id: number;
    title: string;
    description?: string;
    details?: string;
    quanty?: number;
    price?: number;
    file_1?: string;
    file_2?: string;
    file_3?: string;
    file_4?: string;
    transfer_id?: number;
    code?: string;
    condition?: string;
    state?: string;
  };


type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url: string | null;
  prev_page_url: string | null;
};

export default function Articles() {
    const { articles: initialPagination, transfer_id } = usePage<{
        articles?: Pagination<Article>;
        transfer_id?: number;
      }>().props;

      const [articles, setArticles] = useState<Article[]>(initialPagination?.data || []);
      const [pagination, setPagination] = useState(initialPagination || {
        data: [],
        current_page: 1,
        last_page: 1,
        next_page_url: null,
        prev_page_url: null
      });


//   const [articles, setArticles] = useState<Article[]>(initialPagination.data);
//   const [pagination, setPagination] = useState(initialPagination);
  const [showModal, setShowModal] = useState(false);
  const [editArticle, setEditArticle] = useState<Article | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);

  const handleArticleSaved = () => {
    fetchPage(`/articles/fetch?page=${pagination.current_page}${transfer_id ? `&transfer_id=${transfer_id}` : ''}`);
    setEditArticle(null);
  };
  

  const fetchArticle = async (id: number) => {
    const res = await axios.get(`/articles/${id}`);
    setEditArticle(res.data.article);
    setShowModal(true);
  };

  const fetchPage = async (url: string) => {
    try {
      const res = await axios.get(url);
      setArticles(res.data.articles.data);
      setPagination(res.data.articles);
    } catch (e) {
      console.error('Error al cargar página', e);
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-4">Listado de Artículos</h1>
        {transfer_id && (
          <p className="mb-4 text-sm text-gray-600">Artículos asociados a la transferencia #{transfer_id}</p>
        )}

        <button
          onClick={() => {
            setEditArticle(null);
            setShowModal(true);
          }}
          className="mb-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
        >
          Nuevo Artículo
        </button>

        {selectedIds.length > 0 && (
          <button
            onClick={async () => {
              if (confirm(`¿Eliminar ${selectedIds.length} artículos?`)) {
                try {
                  await axios.post('/articles/bulk-delete', { ids: selectedIds });
                  setArticles((prev) => prev.filter((a) => !selectedIds.includes(a.id)));
                  setSelectedIds([]);
                } catch (e) {
                  alert('Error al eliminar en Lote');
                  console.error(e);
                }
              }
            }}
            className="ml-2 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition"
          >
            Eliminar seleccionados
          </button>
        )}

<a href={`/articles/${transfer_id}/export-excel`}
                                className="px-4 ml-2 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition"
                                target="_blank">
                                Exportar a Excel
                            </a>

        <div className="overflow-x-auto mt-4">
        <table className="min-w-full divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
  <thead className="bg-gray-100 dark:bg-gray-800">
    <tr>
      <th className="px-4 py-2 text-black dark:text-white">
        <input
          type="checkbox"
          checked={selectedIds.length === articles.length}
          onChange={(e) =>
            setSelectedIds(e.target.checked ? articles.map((a) => a.id) : [])
          }
        />
      </th>
      <th className="px-4 py-2 text-black dark:text-white">Acciones</th>
      <th className="px-4 py-2 text-black dark:text-white">ID</th>
      <th className="px-4 py-2 text-black dark:text-white">Título</th>
      <th className="px-4 py-2 text-black dark:text-white">Descripción</th>
      <th className="px-4 py-2 text-black dark:text-white">Cantidad</th>
      {/* <th className="px-4 py-2 text-black dark:text-white">Precio</th> */}
      <th className="px-4 py-2 text-black dark:text-white">Código</th>
      <th className="px-4 py-2 text-black dark:text-white">Condición</th>
      <th className="px-4 py-2 text-black dark:text-white">Estado</th>
      <th className="px-4 py-2 text-black dark:text-white">Archivo 1</th>
      <th className="px-4 py-2 text-black dark:text-white">Archivo 2</th>
      <th className="px-4 py-2 text-black dark:text-white">Archivo 3</th>
      <th className="px-4 py-2 text-black dark:text-white">Archivo 4</th>
    </tr>
  </thead>
  <tbody>
    {articles.map((article) => (
      <tr key={article.id} className="border-t hover:bg-gray-50 dark:hover:bg-gray-700 text-black dark:text-white">
        <td className="px-4 py-2">
          <input
            type="checkbox"
            checked={selectedIds.includes(article.id)}
            onChange={(e) =>
              setSelectedIds((prev) =>
                e.target.checked
                  ? [...prev, article.id]
                  : prev.filter((id) => id !== article.id)
              )
            }
          />
        </td>
        <td className="px-4 py-2 space-x-2 text-sm">
          <button
            onClick={() => fetchArticle(article.id)}
            className="text-blue-600 hover:underline dark:text-blue-400 flex items-center gap-1"
          >
            <Paintbrush className="w-4 h-4" />
            Editar
          </button>
          <button
            onClick={async () => {
              if (confirm(`¿Eliminar artículo "${article.title}"?`)) {
                try {
                  await axios.delete(`/articles/${article.id}`);
                  setArticles((prev) => prev.filter((a) => a.id !== article.id));
                } catch (e) {
                  alert('Error al eliminar');
                  console.error(e);
                }
              }
            }}
            className="text-red-600 hover:underline dark:text-red-400 flex items-center gap-1"
          >
            <Trash2 className="w-4 h-4" />
            Eliminar
          </button>
        </td>
        <td className="px-4 py-2">{article.id}</td>
        <td className="px-4 py-2">{article.title}</td>
        <td className="px-4 py-2">{article.description}</td>
        <td className="px-4 py-2">{article.quanty}</td>
        {/* <td className="px-4 py-2">{article.price}</td> */}
        <td className="px-4 py-2">{article.code}</td>
        <td className="px-4 py-2">{article.condition}</td>
        <td className="px-4 py-2">{article.state}</td>
        {[article.file_1, article.file_2, article.file_3, article.file_4].map((file, i) => (
          <td key={i} className="px-4 py-2">
            {file && (
              <a
                href={`../../uploads/${file}`}
                download={file}
                className="text-blue-600 underline dark:text-blue-400"
              >
                {file}
              </a>
            )}
          </td>
        ))}
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
                  fetchPage(
                    `/articles/fetch?page=${page}${
                      transfer_id ? `&transfer_id=${transfer_id}` : ''
                    }`
                  )
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
        <ArticleModal
          open={showModal}
          onClose={() => {
            setShowModal(false);
            setEditArticle(null);
          }}
          onSaved={handleArticleSaved}
          articleToEdit={editArticle}
          transfer_id={transfer_id}
        />
      )}
    </AppLayout>
  );
}
