import { useState, useRef, useEffect } from 'react';
import axios from 'axios';

export default function ProductSearch({
  query,
  setQuery,
  onSelect,
}: {
  query: string;
  setQuery: (value: string) => void;
  onSelect: (product: any) => void;
}) {
  const [results, setResults] = useState<any[]>([]);
  const [showDropdown, setShowDropdown] = useState(false);
  const wrapperRef = useRef<HTMLDivElement>(null);

  const search = async (q: string) => {
    if (q.length >= 2) {
      try {
        const res = await axios.get(`/products/search?q=${q}`);
        setResults(res.data);
        setShowDropdown(true);
      } catch (e) {
        console.error('Error en búsqueda de productos:', e);
      }
    } else {
      setResults([]);
      setShowDropdown(false);
    }
  };

  useEffect(() => {
    if (query.length >= 2) {
      search(query);
    } else {
      setResults([]);
      setShowDropdown(false);
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
    <div className="relative" ref={wrapperRef}>
      <input
        type="text"
        value={query}
        onChange={(e) => setQuery(e.target.value)}
        placeholder="Buscar producto por descripción"
        className="w-full border px-3 py-2 rounded"
      />
      {showDropdown && results.length > 0 && (
        <ul className="absolute z-10 bg-white border mt-1 rounded w-full max-h-60 overflow-auto shadow-md">
          {results.map((item) => (
            <li
              key={item.id}
              onClick={() => {
                onSelect(item);
                setQuery(item.description); // Actualiza el input al seleccionar
                setShowDropdown(false);
              }}
              className="px-3 py-2 hover:bg-blue-100 cursor-pointer"
            >
              <div className="font-medium">{item.description}</div>
              {item.code && <div className="text-xs text-gray-500">Código: {item.code}</div>}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
