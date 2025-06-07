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
  const [selected, setSelected] = useState(false);
  const wrapperRef = useRef<HTMLDivElement>(null);

  const search = async (q: string) => {
    if (q.length >= 2) {
      try {
        const res = await axios.get(`/clients/search?q=${q}`);
        setResults(res.data);
        setShowDropdown(true);
      } catch (e) {
        console.error('Error al buscar clientes:', e);
      }
    } else {
      setResults([]);
      setShowDropdown(false);
    }
  };

  useEffect(() => {
    if (!selected) {
      search(query);
    }
  }, [query]);

  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) {
        setShowDropdown(false);
        setSelected(false); // permitir nueva búsqueda si hacen clic fuera
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
          setSelected(false); // desbloquea búsqueda al escribir de nuevo
        }}
        placeholder="Buscar cliente por DNI o razón social"
        className="w-full border px-3 py-2 rounded"
      />
      {showDropdown && results.length > 0 && (
        <ul className="absolute z-10 bg-white border mt-1 rounded w-full max-h-60 overflow-auto shadow-md">
          {results.map((client) => (
            <li
              key={client.id}
              onClick={() => {
                onSelect(client);
                setQuery(client.names);
                setSelected(true);
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
