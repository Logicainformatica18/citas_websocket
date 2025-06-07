import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import ProductModal from './modal';
import axios from 'axios';
import { Paintbrush, Trash2 } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Productos', href: '/products' },
];

type Product = {
  id: number;
  code: string;
  sku: string;
  description: string;
  detail?: string;
  brand?: string;
  model?: string;
  serial_number?: string;
  condition?: string;
  state?: string;
  quantity?: number;
  price?: number;
  location?: string;
  file_1?: string;
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url: string | null;
  prev_page_url: string | null;
};

export default function Products() {
  const { products: initialPagination } = usePage<{ products: Pagination<Product> }>().props;

  const [products, setProducts] = useState<Product[]>(initialPagination.data);
  const [pagination, setPagination] = useState(initialPagination);
  const [showModal, setShowModal] = useState(false);
  const [editProduct, setEditProduct] = useState<Product | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);

  const handleProductSaved = (saved: Product) => {
    setProducts((prev) => {
      const exists = prev.find((p) => p.id === saved.id);
      return exists ? prev.map((p) => (p.id === saved.id ? saved : p)) : [saved, ...prev];
    });
    setEditProduct(null);
  };

  const fetchProduct = async (id: number) => {
    const res = await axios.get(`/products/${id}`);
    setEditProduct(res.data.product);
    setShowModal(true);
  };

  const fetchPage = async (url: string) => {
    try {
      const res = await axios.get(url);
      setProducts(res.data.products.data);
      setPagination(res.data.products);
    } catch (e) {
      console.error('Error al cargar página', e);
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-4">Listado de Productos</h1>

        <button
          onClick={() => {
            setEditProduct(null);
            setShowModal(true);
          }}
          className="mb-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
        >
          Nuevo Producto
        </button>

        {selectedIds.length > 0 && (
          <button
            onClick={async () => {
              if (confirm(`¿Eliminar ${selectedIds.length} productos?`)) {
                try {
                  await axios.post('/products/bulk-delete', { ids: selectedIds });
                  setProducts((prev) => prev.filter((p) => !selectedIds.includes(p.id)));
                  setSelectedIds([]);
                } catch (e) {
                  alert('Error al eliminar en lote');
                  console.error(e);
                }
              }
            }}
            className="ml-2 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition"
          >
            Eliminar seleccionados
          </button>
        )}

        <div className="overflow-x-auto mt-4">
          <table className="min-w-full divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
            <thead className="bg-gray-100 dark:bg-gray-800">
              <tr>
                <th className="px-4 py-2">
                  <input
                    type="checkbox"
                    checked={selectedIds.length === products.length}
                    onChange={(e) =>
                      setSelectedIds(e.target.checked ? products.map((p) => p.id) : [])
                    }
                  />
                </th>
                <th className="px-4 py-2">Acciones</th>
                <th className="px-4 py-2">ID</th>
                <th className="px-4 py-2">Código</th>
                <th className="px-4 py-2">SKU</th>
                <th className="px-4 py-2">Descripción</th>
                <th className="px-4 py-2">Marca</th>
                <th className="px-4 py-2">Modelo</th>
                <th className="px-4 py-2">Serie</th>
                <th className="px-4 py-2">Condición</th>
                <th className="px-4 py-2">Estado</th>
                <th className="px-4 py-2">Cantidad</th>
                {/* <th className="px-4 py-2">Precio</th> */}
                <th className="px-4 py-2">Ubicación</th>
                <th className="px-4 py-2">Archivo</th>
              </tr>
            </thead>
            <tbody>
              {products.map((product) => (
                <tr key={product.id} className="border-t hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td className="px-4 py-2">
                    <input
                      type="checkbox"
                      checked={selectedIds.includes(product.id)}
                      onChange={(e) =>
                        setSelectedIds((prev) =>
                          e.target.checked
                            ? [...prev, product.id]
                            : prev.filter((id) => id !== product.id)
                        )
                      }
                    />
                  </td>
                  <td className="px-4 py-2 text-sm space-x-2">
                    <button
                      onClick={() => fetchProduct(product.id)}
                      className="text-blue-600 hover:underline dark:text-blue-400 flex items-center gap-1"
                    >
                      <Paintbrush className="w-4 h-4" /> Editar
                    </button>
                    <button
                      onClick={async () => {
                        if (confirm(`¿Eliminar producto "${product.description}"?`)) {
                          try {
                            await axios.delete(`/products/${product.id}`);
                            setProducts((prev) => prev.filter((p) => p.id !== product.id));
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
                  <td className="px-4 py-2">{product.id}</td>
                  <td className="px-4 py-2">{product.code}</td>
                  <td className="px-4 py-2">{product.sku}</td>
                  <td className="px-4 py-2">{product.description}</td>
                  <td className="px-4 py-2">{product.brand}</td>
                  <td className="px-4 py-2">{product.model}</td>
                  <td className="px-4 py-2">{product.serial_number}</td>
                  <td className="px-4 py-2">{product.condition}</td>
                  <td className="px-4 py-2">{product.state}</td>
                  <td className="px-4 py-2">{product.quantity}</td>
                  {/* <td className="px-4 py-2">{product.price}</td> */}
                  <td className="px-4 py-2">{product.location}</td>
                  <td className="px-4 py-2">
                    {product.file_1 && (
                      <a
                        href={`../../uploads/${product.file_1}`}
                        download
                        className="text-blue-600 underline dark:text-blue-400"
                      >
                        {product.file_1}
                      </a>
                    )}
                  </td>
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
                onClick={() => fetchPage(`/products/fetch?page=${page}`)}
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
        <ProductModal
          open={showModal}
          onClose={() => {
            setShowModal(false);
            setEditProduct(null);
          }}
          onSaved={handleProductSaved}
          productToEdit={editProduct}
        />
      )}
    </AppLayout>
  );
}
