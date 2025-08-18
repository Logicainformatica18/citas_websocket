import { useState, useRef, useEffect } from 'react';
import axios from 'axios';

const CACHE_TTL = 60_000; // 60s
const DEBOUNCE_MS = 350;

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
const skipNextSearchRef = useRef(false);

  const wrapperRef = useRef<HTMLDivElement>(null);
  const debounceRef = useRef<number | null>(null);
  const abortRef = useRef<AbortController | null>(null);
  const cacheRef = useRef<Map<string, { ts: number; data: any[] }>>(new Map());
  const lastSearchedRef = useRef<string>("");

  const getCached = (q: string) => {
    const hit = cacheRef.current.get(q);
    if (!hit) return null;
    if (Date.now() - hit.ts > CACHE_TTL) {
      cacheRef.current.delete(q);
      return null;
    }
    return hit.data;
  };
const inputRef = useRef<HTMLInputElement>(null);
  const search = async (q: string) => {
    if (q.length < 2) {
      setResults([]);
      setShowDropdown(false);
      lastSearchedRef.current = "";
      return;
    }

    // Si hay cache fresco, úsalo y evita llamada
    const cached = getCached(q);
    if (cached) {
      setResults(cached);
      setShowDropdown(cached.length > 0);
      lastSearchedRef.current = q;
      return;
    }

    // Evita repetir si ya buscaste exactamente lo mismo hace nada
    if (lastSearchedRef.current === q) return;

    // Cancela request anterior si existe
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      const res = await axios.get(`/products/search?q=${encodeURIComponent(q)}`, {
        signal: controller.signal as any,
      });
      const data = Array.isArray(res.data) ? res.data : [];
      cacheRef.current.set(q, { ts: Date.now(), data });
      setResults(data);
      setShowDropdown(data.length > 0);
      lastSearchedRef.current = q;
    } catch (e: any) {
      if (axios.isCancel?.(e) || e.name === 'CanceledError' || e.code === 'ERR_CANCELED') {
        // request cancelada: no loggear como error
      } else {
        console.error('Error en búsqueda de productos:', e);
      }
    }
  };

  // Debounce sobre `query`
useEffect(() => {
  if (skipNextSearchRef.current) {
    skipNextSearchRef.current = false; // consume el salto
    return; // NO buscar
  }
  if (query.length >= 2) {
    search(query);
  } else {
    setResults([]);
    setShowDropdown(false);
  }
}, [query]);


  // Cierre del dropdown al click fuera
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
        onFocus={() => {
          // Al enfocar, si hay cache para el query actual, mostrar sin re-buscar
          const cached = getCached(query);
          if (cached) {
            setResults(cached);
            setShowDropdown(cached.length > 0);
          }
        }}
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
    skipNextSearchRef.current = true;     // <- evita la búsqueda re-disparada
    setShowDropdown(false);
    setQuery(item.description);           // actualiza el input sin volver a buscar
    inputRef.current?.blur();             // opcional: quita foco para no reabrir
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
