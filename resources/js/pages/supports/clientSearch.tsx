import { useState, useEffect, useRef } from 'react';
import axios from 'axios';

export default function ClientSearch({
  query,
  setQuery,
  onSelect,
  selectedClient, // <- prop desde el padre
}: {
  query: string;
  setQuery: (value: string) => void;
  onSelect: (client: any) => void;
  selectedClient?: any;
}) {
  const [results, setResults] = useState<any[]>([]);
  const [showDropdown, setShowDropdown] = useState(false);
  const [internalSelectedClient, setInternalSelectedClient] = useState<any>(null);
  const [loading, setLoading] = useState(false);
  const wrapperRef = useRef<HTMLDivElement>(null);

const search = async (q: string) => {
  if (!q || typeof q !== 'string') return; // ✅ protección extra

  if (q.length >= 2) {
    try {
      setLoading(true);
      const res = await axios.get(`/clients/search?q=${q}`);
      setResults(res.data);
      setLoading(false);
      if (res.data.length > 0 && !internalSelectedClient) {
        setShowDropdown(true);
      }
    } catch (e) {
      console.error('Error al buscar clientes:', e);
      setLoading(false);
      setShowDropdown(false);
    }
  } else {
    setResults([]);
    setShowDropdown(false);
  }
};


const debounceTimeout = useRef<NodeJS.Timeout | null>(null);

useEffect(() => {
  if (typeof query === 'string' && (!internalSelectedClient || query !== internalSelectedClient.names)) {
    if (debounceTimeout.current) clearTimeout(debounceTimeout.current);

    debounceTimeout.current = setTimeout(() => {
      search(query);
    }, 600); // ⏱️ Espera 500ms después de que el usuario deja de escribir
  }
  return () => {
    if (debounceTimeout.current) clearTimeout(debounceTimeout.current);
  };
}, [query]);



  // ⬇️ Este hook sincroniza el cliente al editar
  useEffect(() => {
    if (selectedClient) {
      setInternalSelectedClient(selectedClient);
      setQuery(selectedClient.names);
    }
  }, [selectedClient]);

  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) {
        setShowDropdown(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  return (
    <div className="relative col-span-3" ref={wrapperRef}>
      <input
        type="text"
        value={query}
        onChange={(e) => {
          setQuery(e.target.value);
          setInternalSelectedClient(null); // limpiar selección si se escribe de nuevo
        }}
        onFocus={() => {
          if (!internalSelectedClient && results.length > 0) {
            setShowDropdown(true);
          }
        }}
        placeholder="Buscar cliente por DNI o razón social"
        className="w-full border px-3 py-2 rounded"
      />

      {loading && (
        <div className="absolute z-10 bg-white mt-1 border w-full px-4 py-2 text-sm text-gray-500 rounded shadow">
          Buscando...
        </div>
      )}

      {!loading && showDropdown && results.length > 0 && (
        <ul className="absolute z-10 bg-white border mt-1 rounded w-full max-h-60 overflow-auto shadow-md">
          {results.map((client) => (
            <li
              key={client.id}
              onClick={() => {
                onSelect(client);
                setQuery(client.names);
                setInternalSelectedClient(client);
                setShowDropdown(false);
              }}
              className="px-3 py-2 hover:bg-blue-100 cursor-pointer"
            >
              <div className="font-medium">{client.names}</div>
              <div className="text-xs text-gray-500">DNI: {client.dni}</div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
