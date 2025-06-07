import AppLayout from '@/layouts/app-layout';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import UserModal from './modal';
import UserRolesModal from './UserRolesModal';
import axios from 'axios';
import { Paintbrush, Trash2, Settings } from 'lucide-react';

const breadcrumbs = [{ title: 'Usuarios', href: '/users' }];

type User = {
id: number;
dni?: string;
firstname: string;
lastname: string;
names: string;
email: string;
cellphone?: string;
sex?: string;
datebirth?: string;
roles?: { name: string }[];
photo?: string;
};

type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    };

    export default function Users() {
    const { users: initialPagination, roles } = usePage<{ users: Pagination<User>;
        roles: { name: string }[];
        }>().props;

        const [users, setUsers] = useState<User[]>(initialPagination?.data || []);
            const [pagination, setPagination] = useState(initialPagination);
            const [showModal, setShowModal] = useState(false);
            const [editUser, setEditUser] = useState<User | null>(null);
                // const [selectedIds, setSelectedIds] = useState<number[]>([]);
                    const [showRoleModal, setShowRoleModal] = useState(false);
                    const [selectedUser, setSelectedUser] = useState<User | null>(null);

                        const handleUserSaved = (saved: User) => {
                        setUsers((prev) => {
                        const exists = prev.find((u) => u.id === saved.id);
                        return exists ? prev.map((u) => (u.id === saved.id ? saved : u)) : [saved, ...prev];
                        });
                        setEditUser(null);
                        };

                        const fetchUser = async (id: number) => {
                        const res = await axios.get(`/users/${id}`);
                        setEditUser(res.data.user);
                        setShowModal(true);
                        };

                        const fetchPage = async (url: string) => {
                        try {
                        const res = await axios.get(url);
                        setUsers(res.data.users.data);
                        setPagination(res.data.users);
                        } catch (e) {
                        console.error('Error al cargar página', e);
                        }
                        };

                        return (
                        <AppLayout breadcrumbs={breadcrumbs}>
                            <div className="p-8">
                                <h1 className="text-2xl font-bold mb-4">Listado de Usuarios</h1>

                                <button onClick={()=> {
                                    setEditUser(null);
                                    setShowModal(true);
                                    }}
                                    className="mb-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
                                    >
                                    Nuevo Usuario
                                </button>

                                <div className="overflow-x-auto mt-4">
                                    <table
                                        className="min-w-full divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
                                        <thead className="bg-gray-100 dark:bg-gray-800">
                                            <tr>
                                                <th className="px-4 py-2 text-black dark:text-white">Acciones</th>
                                                <th className="px-4 py-2 text-black dark:text-white">Gestionar</th>
                                                <th className="px-4 py-2 text-black dark:text-white">ID</th>
                                                <th className="px-4 py-2 text-black dark:text-white">Nombres</th>
                                                <th className="px-4 py-2 text-black dark:text-white">Email</th>
                                                <th className="px-4 py-2 text-black dark:text-white">Roles</th>
                                                <th className="px-4 py-2 text-black dark:text-white">Foto</th>

                                            </tr>
                                        </thead>
                                        <tbody>
                                            {users.map((user) => (
                                            <tr key={user.id}
                                                className="border-t hover:bg-gray-50 dark:hover:bg-gray-700 text-black dark:text-white">
                                                <td className="px-4 py-2 space-x-2 text-sm">
                                                    <button onClick={()=> fetchUser(user.id)}
                                                        className="text-blue-600 hover:underline dark:text-blue-400 flex items-center gap-1"
                                                        >
                                                        <Paintbrush className="w-4 h-4" />
                                                        Editar
                                                    </button>
                                                    <button onClick={async ()=> {
                                                        if (confirm(`¿Eliminar usuario ${user.names}?`)) {
                                                        try {
                                                        await axios.delete(`/users/${user.id}`);
                                                        setUsers((prev) => prev.filter((u) => u.id !== user.id));
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

                                                <td className="px-4 py-2 text-sm">
                                                    <button onClick={()=> {
                                                        setSelectedUser(user);
                                                        setShowRoleModal(true);
                                                        }}
                                                        className="text-indigo-600 hover:underline flex items-center gap-1 dark:text-indigo-400"
                                                        title="Gestionar roles"
                                                        >
                                                        <Settings className="w-4 h-4" />
                                                        Roles
                                                    </button>
                                                </td>

                                                <td className="px-4 py-2">{user.id}</td>
                                                <td className="px-4 py-2">{user.names}</td>
                                                <td className="px-4 py-2">{user.email}</td>
                                                <td className="px-4 py-2">{user.roles?.map((r) => r.name).join(', ')}
                                                </td>
                                                <td className="px-4 py-2">
                                                    {user.photo ? (
                                                    <a href={`/imageusers/${user.photo}`} download
                                                        className="text-blue-600 underline dark:text-blue-400">
                                                        Descargar
                                                    </a>
                                                    ) : (
                                                    <span className="text-gray-400 italic">Sin foto</span>
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
                                    <button key={page} onClick={()=> fetchPage(`/users/fetch?page=${page}`)}
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
                            <UserModal open={showModal} onClose={()=> {
                                setShowModal(false);
                                setEditUser(null);
                                }}
                                onSaved={handleUserSaved}
                                userToEdit={editUser}
                                availableRoles={roles}
                                />
                                )}

                                {selectedUser && (
                                <UserRolesModal open={showRoleModal} onClose={()=> setShowRoleModal(false)}
                                    userId={selectedUser.id}
                                    currentRoles={selectedUser.roles?.map((r) => r.name) || []}
                                    availableRoles={roles}
                                    onUpdated={(updatedRoles) => {
                                    setUsers((prev) =>
                                    prev.map((u) =>
                                    u.id === selectedUser.id
                                    ? { ...u, roles: updatedRoles.map((name) => ({ name })) }
                                    : u
                                    )
                                    );
                                    setShowRoleModal(false);
                                    }}
                                    />
                                    )}
                        </AppLayout>
                        );
                        }
