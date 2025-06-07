import AppLayout from '@/layouts/app-layout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ClientModal from './modal';
import ClientTable from './table';

interface Client {
  id_cliente: number;
  Codigo: string;
  Razon_Social: string;
  DNI: string;
  Email: string;
  Telefono: string;
  Direccion: string;
  canal: string;
  habilitado: number;
}

interface PageProps {
  clients: {
    data: Client[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
  };
}

export default function Clients() {
  const { clients } = usePage<PageProps>().props;
  const [clientList, setClientList] = useState<Client[]>(clients.data);
  const [pagination, setPagination] = useState(clients);
  const [modalOpen, setModalOpen] = useState(false);
  const [clientToEdit, setClientToEdit] = useState<Client | null>(null);

  const fetchPage = async (url: string) => {
    const response = await fetch(url);
    const json = await response.json();
    setClientList(json.data);
    setPagination(json);
  };

  const fetchClient = async (id: number) => {
    const response = await fetch(`/clients/${id}`);
    const data = await response.json();
    setClientToEdit(data);
    setModalOpen(true);
  };

  return (
    <AppLayout>
      <Head title="Clientes" />

      <div className="flex justify-between items-center mb-4">
        <h1 className="text-2xl font-bold">Clientes</h1>
        <button
          className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
          onClick={() => {
            setClientToEdit(null);
            setModalOpen(true);
          }}
        >
          Nuevo Cliente
        </button>
      </div>

      <ClientTable
        clients={clientList}
        setClients={setClientList}
        pagination={pagination}
        fetchPage={fetchPage}
        fetchClient={fetchClient}
      />

      {modalOpen && (
        <ClientModal
          open={modalOpen}
          onClose={() => setModalOpen(false)}
          clientToEdit={clientToEdit}
          onSaved={() => {
            setModalOpen(false);
            router.reload({ only: ['clients'] });
          }}
        />
      )}
    </AppLayout>
  );
}
