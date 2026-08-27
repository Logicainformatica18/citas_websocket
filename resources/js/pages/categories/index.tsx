import AppLayout from '@/layouts/app-layout';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import CategoryModal from './modal';
import axios from 'axios';
import { Paintbrush, Trash2 } from 'lucide-react';

const breadcrumbs = [{ title: 'Categorías', href: '/categories' }];

type Category = {
    id: number;
    description: string;
    detail?: string | null;
};

type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

export default function Categories() {
    const { categories: initialPagination } = usePage<{
        categories: Pagination<Category>;
    }>().props;

    const [categories, setCategories] = useState<Category[]>(initialPagination?.data || []);
    const [pagination, setPagination] = useState(initialPagination);
    const [showModal, setShowModal] = useState(false);
    const [editCategory, setEditCategory] = useState<Category | null>(null);

    const handleCategorySaved = (saved: Category) => {
        setCategories((prev) => {
            const exists = prev.find((c) => c.id === saved.id);
            return exists
                ? prev.map((c) => (c.id === saved.id ? saved : c))
                : [saved, ...prev];
        });
        setEditCategory(null);
    };

    const fetchCategory = async (id: number) => {
        const res = await axios.get(`/categories/${id}`);
        setEditCategory(res.data.category);
        setShowModal(true);
    };

    const fetchPage = async (url: string) => {
        try {
            const res = await axios.get(url);
            setCategories(res.data.categories.data);
            setPagination(res.data.categories);
        } catch (e) {
            console.error('Error al cargar página', e);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="p-8">
                <h1 className="text-2xl font-bold mb-4">Listado de Categorías</h1>

                <button
                    onClick={() => {
                        setEditCategory(null);
                        setShowModal(true);
                    }}
                    className="mb-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
                >
                    Nueva Categoría
                </button>

                <div className="overflow-x-auto mt-4">
                    <table className="min-w-full divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
                        <thead className="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-2 text-black dark:text-white">Acciones</th>
                                <th className="px-4 py-2 text-black dark:text-white">ID</th>
                                <th className="px-4 py-2 text-black dark:text-white">Descripción</th>
                                <th className="px-4 py-2 text-black dark:text-white">Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            {categories.map((category) => (
                                <tr
                                    key={category.id}
                                    className="border-t hover:bg-gray-50 dark:hover:bg-gray-700 text-black dark:text-white"
                                >
                                    <td className="px-4 py-2 space-x-2 text-sm">
                                        <button
                                            onClick={() => fetchCategory(category.id)}
                                            className="text-blue-600 hover:underline dark:text-blue-400 flex items-center gap-1"
                                        >
                                            <Paintbrush className="w-4 h-4" />
                                            Editar
                                        </button>
                                        <button
                                            onClick={async () => {
                                                if (confirm(`¿Eliminar la categoría ${category.description}?`)) {
                                                    try {
                                                        await axios.delete(`/categories/${category.id}`);
                                                        setCategories((prev) =>
                                                            prev.filter((c) => c.id !== category.id)
                                                        );
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

                                    <td className="px-4 py-2">{category.id}</td>
                                    <td className="px-4 py-2">{category.description}</td>
                                    <td className="px-4 py-2">
                                        {category.detail ? (
                                            category.detail
                                        ) : (
                                            <span className="text-gray-400 italic">Sin detalle</span>
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
                                onClick={() => fetchPage(`/categories/fetch?page=${page}`)}
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
                <CategoryModal
                    open={showModal}
                    onClose={() => {
                        setShowModal(false);
                        setEditCategory(null);
                    }}
                    onSaved={handleCategorySaved}
                    categoryToEdit={editCategory}
                />
            )}
        </AppLayout>
    );
}
