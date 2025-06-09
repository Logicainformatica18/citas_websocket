import { useState, useEffect, useRef } from 'react';
import axios from 'axios';

export default function ClientSearch({
  query,
  setQuery,
  onSelect,
}: {
  query: string;
  setQuery: (value: string) => void;
  onSelect: (client: any) => void;
}) {
  const [results, setResults] = useState<any[]>([]);
  const [showDropdown, setShowDropdown] = useState(false);
  const [selectedClient, setSelectedClient] = useState<any>(null);
  const [loading, setLoading] = useState(false); // 👈 estado de carga
  const wrapperRef = useRef<HTMLDivElement>(null);

  const search = async (q: string) => {
    if (q.length >= 2) {
      try {
        setLoading(true); // 👈 comienza la carga
        const res = await axios.get(`/clients/search?q=${q}`);
        setResults(res.data);
        setLoading(false); // 👈 termina la carga
        if (res.data.length > 0 && !selectedClient) {
          setShowDropdown(true); // 👈 solo muestra si hay resultados
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

  useEffect(() => {
    if (!selectedClient || query !== selectedClient.names) {
      search(query);
    }
  }, [query]);

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
          setSelectedClient(null); // invalida selección previa
        }}
        onFocus={() => {
          if (!selectedClient && results.length > 0) {
            setShowDropdown(true);
          }
        }}
        placeholder="Buscar cliente por DNI o razón social"
        className="w-full border px-3 py-2 rounded"
      />

      {/* Mostrar loading opcional */}
      {loading && (
        <div className="absolute z-10 bg-white mt-1 border w-full px-4 py-2 text-sm text-gray-500 rounded shadow">
          Buscando...
        </div>
      )}

      {/* Solo muestra si terminó de buscar y hay resultados */}
      {!loading && showDropdown && results.length > 0 && (
        <ul className="absolute z-10 bg-white border mt-1 rounded w-full max-h-60 overflow-auto shadow-md">
          {results.map((client) => (
            <li
              key={client.id}
              onClick={() => {
                onSelect(client);
                setQuery(client.names);
                setSelectedClient(client);
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
