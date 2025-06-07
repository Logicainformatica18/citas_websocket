// resources/js/pages/roles/index.tsx
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import RoleModal from './modal';
import axios from 'axios';
import { Paintbrush, Trash2 } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Roles', href: '/roles' },
];

type Role = {
  id: number;
  name: string;
  permissions: { id: number; name: string }[];
};

type Pagination<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url: string | null;
  prev_page_url: string | null;
};

export default function Roles() {
  const { roles: initialPagination = { data: [], current_page: 1, last_page: 1, next_page_url: null, prev_page_url: null } } = usePage<{ roles?: Pagination<Role> }>().props;

  const [roles, setRoles] = useState<Role[]>(initialPagination.data);
  const [pagination, setPagination] = useState(initialPagination);
  const [showModal, setShowModal] = useState(false);
  const [editRole, setEditRole] = useState<Role | null>(null);

  const handleSaved = (saved: Role) => {
    setRoles((prev) => {
      const exists = prev.find((r) => r.id === saved.id);
      return exists ? prev.map((r) => (r.id === saved.id ? saved : r)) : [saved, ...prev];
    });
    setEditRole(null);
    setShowModal(false);
  };

  const fetchRole = async (id: number) => {
    try {
      const res = await axios.get(`/roles/${id}`);
      setEditRole(res.data.role);
      setShowModal(true);
    } catch (error) {
      console.error('Error fetching role', error);
      alert('Error al obtener el rol');
    }
  };

  const fetchPage = async (url: string) => {
    try {
      const res = await axios.get(url);
      setRoles(res.data.roles.data);
      setPagination(res.data.roles);
    } catch (e) {
      console.error('Error loading page', e);
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8">
        <h1 className="text-2xl font-bold mb-4">Listado de Roles</h1>
        <p className="text-muted-foreground mb-6">Administra los roles y permisos del sistema.</p>

        <button
          onClick={() => {
            setEditRole(null);
            setShowModal(true);
          }}
          className="mb-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
        >
          Nuevo Rol
        </button>

        <table className="min-w-full divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
          <thead className="bg-gray-100 dark:bg-gray-800">
            <tr>
              <th className="px-4 py-2 text-black dark:text-white">ID</th>
              <th className="px-4 py-2 text-black dark:text-white">Nombre</th>
              <th className="px-4 py-2 text-black dark:text-white">Permisos</th>
              <th className="px-4 py-2 text-black dark:text-white">Acciones</th>
            </tr>
          </thead>
          <tbody>
            {roles.map((role) => (
              <tr key={role.id} className="border-t hover:bg-gray-50 dark:hover:bg-gray-700 text-black dark:text-white">
                <td className="px-4 py-2">{role.id}</td>
                <td className="px-4 py-2">{role.name}</td>
                <td className="px-4 py-2 text-sm">
                  {role.permissions.map((p) => p.name).join(', ')}
                </td>
                <td className="px-4 py-2 space-x-2">
                  <button
                    onClick={() => fetchRole(role.id)}
                    className="flex items-center gap-1 text-blue-600 hover:underline dark:text-blue-400"
                  >
                    <Paintbrush className="w-4 h-4" />
                    Editar
                  </button>
                  <button
                    onClick={async () => {
                      if (confirm(`¿Eliminar rol "${role.name}"?`)) {
                        try {
                          await axios.delete(`/roles/${role.id}`);
                          setRoles((prev) => prev.filter((r) => r.id !== role.id));
                        } catch (e) {
                          alert('Error al eliminar');
                          console.error(e);
                        }
                      }
                    }}
                    className="flex items-center gap-1 text-red-600 hover:underline dark:text-red-400"
                  >
                    <Trash2 className="w-4 h-4" />
                    Eliminar
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        <div className="flex justify-center mt-6 space-x-2">
          {[...Array(pagination.last_page)].map((_, index) => {
            const page = index + 1;
            return (
              <button
                key={page}
                onClick={() => fetchPage(`/roles/fetch?page=${page}`)}
                className={`px-3 py-1 rounded text-sm font-medium transition ${
                  pagination.current_page === page
                    ? 'bg-blue-600 text-white'
                    : 'bg-gray-200 text-gray-800 hover:bg-gray-300'
                }`}
              >
                {page}
              </button>
            );
          })}
        </div>
      </div>

      {showModal && (
        <RoleModal
          open={showModal}
          onClose={() => {
            setShowModal(false);
            setEditRole(null);
          }}
          onSaved={handleSaved}
          roleToEdit={editRole}
        />
      )}
    </AppLayout>
  );
}
