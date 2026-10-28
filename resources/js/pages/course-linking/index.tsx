import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import { ChevronDown, Link2, Search, Ban, CheckCircle2 } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Vinculación de cursos', href: '/course-linking' }];

// Valor especial del select para "No vincular"
const IGNORE = 'ignore';

type Career = { codigo: string; nombre: string; mapped: boolean };

type PendingSyllabus = {
  id: number;
  modelo: string;
  silabo_id: number;
  code: string;
  name: string;
  periodo: string | null;
  version: number | null;
  careers: Career[];
  suggestion: { course_id: number; similarity: number } | null;
  received_at: string | null;
};

type CourseOption = { id: number; name: string; careers: string[] };

type Choice = number | typeof IGNORE | null;

export default function CourseLinkingIndex() {
  const { pending, courses } = usePage<{ pending: PendingSyllabus[]; courses: CourseOption[] }>().props;

  // Preselección: la sugerencia por similitud (mismo cálculo del reporte de correspondencia).
  // Si dos sílabos sugieren el mismo curso, se preselecciona solo en el de mayor similitud.
  const initialChoices = () => {
    const result: Record<number, Choice> = Object.fromEntries(pending.map((p) => [p.id, null]));
    const used = new Set<number>();
    [...pending]
      .filter((p) => p.suggestion)
      .sort((a, b) => b.suggestion!.similarity - a.suggestion!.similarity)
      .forEach((p) => {
        if (!used.has(p.suggestion!.course_id)) {
          used.add(p.suggestion!.course_id);
          result[p.id] = p.suggestion!.course_id;
        }
      });
    return result;
  };

  const [choices, setChoices] = useState<Record<number, Choice>>(initialChoices);
  const [saving, setSaving] = useState<number | null>(null);
  const [errors, setErrors] = useState<Record<number, string>>({});
  const [notice, setNotice] = useState<string | null>(null);

  useEffect(() => {
    setChoices(initialChoices());
    setErrors({});
  }, [pending]);

  // Un curso elegido en una fila ya no se ofrece en las demás (cada código va a un solo curso).
  const takenBy = useMemo(() => {
    const map: Record<number, number> = {};
    Object.entries(choices).forEach(([syllabusId, choice]) => {
      if (typeof choice === 'number') map[choice] = Number(syllabusId);
    });
    return map;
  }, [choices]);

  const save = async (item: PendingSyllabus) => {
    const choice = choices[item.id];
    if (choice === null || choice === undefined) {
      setErrors((e) => ({ ...e, [item.id]: 'Elige un curso o la opción "No vincular".' }));
      return;
    }
    if (choice === IGNORE && !confirm(`¿No vincular el sílabo ${item.code} — ${item.name}? Quedará como ignorado.`)) {
      return;
    }

    setSaving(item.id);
    setErrors((e) => ({ ...e, [item.id]: '' }));
    try {
      const res = await axios.post(
        `/course-linking/${item.id}`,
        choice === IGNORE ? { action: 'ignore' } : { action: 'link', course_id: choice },
      );
      setNotice(res.data?.message ?? 'Guardado.');
      // Recarga la lista, los cursos sin código y el contador del menú.
      router.reload({ only: ['pending', 'courses', 'pendingCourseLinks'] });
    } catch (e: any) {
      const data = e?.response?.data;
      const message =
        (data?.errors && (Object.values(data.errors).flat()[0] as string)) ||
        data?.message ||
        'No se pudo guardar.';
      setErrors((prev) => ({ ...prev, [item.id]: message }));
    } finally {
      setSaving(null);
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <div className="p-8 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen transition-colors duration-200">

        {/* HEADER ISIL */}
        <div className="flex items-center justify-between mb-6 border-b border-gray-200 dark:border-gray-800 pb-4">
          <h1 className="text-3xl font-semibold flex items-center gap-2">
            <Link2 className="w-7 h-7 text-[#1CBCE8]" />
            <span className="text-[#0C647A] dark:text-[#1CBCE8]">Vinculación de cursos</span>
          </h1>
          <span className="text-sm text-gray-500 dark:text-gray-400">
            {pending.length} pendiente(s) · {courses.length} curso(s) sin código
          </span>
        </div>

        <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">
          Sílabos aprobados en silabos_v2 cuyo código no corresponde a ningún curso del observatorio.
          Al vincular, el código se guarda en el curso elegido y el sílabo se procesa.
        </p>

        {notice && (
          <div className="mb-4 flex items-center gap-2 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/30 dark:text-green-200">
            <CheckCircle2 className="w-4 h-4" /> {notice}
          </div>
        )}

        {/* TABLA */}
        <div className="overflow-x-auto rounded-lg shadow border border-gray-200 dark:border-gray-800">
          <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
            <thead className="bg-[#1CBCE8] dark:bg-[#1CBCE8]/20 text-white dark:text-[#1CBCE8] uppercase text-xs">
              <tr>
                <th className="px-4 py-2 text-left">Código</th>
                <th className="px-4 py-2 text-left">Sílabo (silabos_v2)</th>
                <th className="px-4 py-2 text-left">Carreras (silabos_v2)</th>
                <th className="px-4 py-2 text-left w-[28rem]">Curso del observatorio</th>
                <th className="px-4 py-2">Acción</th>
              </tr>
            </thead>

            <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
              {pending.map((item) => (
                <tr key={item.id} className="align-top hover:bg-[#E7F9FD] dark:hover:bg-[#1CBCE8]/10 transition-colors">
                  <td className="px-4 py-3 font-mono text-sm whitespace-nowrap">{item.code}</td>

                  <td className="px-4 py-3">
                    <div className="font-semibold">{item.name}</div>
                    <div className="text-xs text-gray-500 dark:text-gray-400">
                      {item.modelo.toUpperCase()} · id {item.silabo_id} · v{item.version ?? '-'} · {item.periodo ?? '-'}
                    </div>
                  </td>

                  <td className="px-4 py-3">
                    <div className="flex flex-wrap gap-1">
                      {item.careers.length === 0 && <span className="text-xs text-gray-400">Sin carreras</span>}
                      {item.careers.map((c) => (
                        <span
                          key={c.codigo}
                          title={c.mapped ? 'Carrera del observatorio' : 'Carrera que el observatorio no sigue'}
                          className={`px-2 py-0.5 rounded-md text-xs font-medium ${
                            c.mapped
                              ? 'bg-[#E7F9FD] text-[#0C647A] dark:bg-[#1CBCE8]/20 dark:text-[#1CBCE8]'
                              : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                          }`}
                        >
                          {c.codigo} {c.nombre}
                        </span>
                      ))}
                    </div>
                  </td>

                  <td className="px-4 py-3">
                    <CourseSelect
                      value={choices[item.id] ?? null}
                      onChange={(v) => {
                        setChoices((prev) => ({ ...prev, [item.id]: v }));
                        setErrors((prev) => ({ ...prev, [item.id]: '' }));
                      }}
                      courses={courses.filter((c) => takenBy[c.id] === undefined || takenBy[c.id] === item.id)}
                      suggestion={item.suggestion}
                      syllabusName={item.name}
                    />
                    {errors[item.id] && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors[item.id]}</p>}
                  </td>

                  <td className="px-4 py-3 text-center">
                    <button
                      onClick={() => save(item)}
                      disabled={saving === item.id}
                      className="px-4 py-2 bg-[#1CBCE8] hover:bg-[#17A8D0] disabled:opacity-60 text-white rounded-md shadow text-sm transition"
                    >
                      {saving === item.id ? 'Guardando…' : 'Guardar'}
                    </button>
                  </td>
                </tr>
              ))}

              {pending.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                    No hay sílabos pendientes de vinculación.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </AppLayout>
  );
}

/* ======================================================
   SELECT CON BUSCADOR
====================================================== */
function CourseSelect({
  value,
  onChange,
  courses,
  suggestion,
  syllabusName,
}: {
  value: Choice;
  onChange: (v: Choice) => void;
  courses: CourseOption[];
  suggestion: PendingSyllabus['suggestion'];
  syllabusName: string;
}) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const close = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', close);
    return () => document.removeEventListener('mousedown', close);
  }, []);

  const normalize = (s: string) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

  const filtered = useMemo(() => {
    const q = normalize(query.trim());
    return q ? courses.filter((c) => normalize(c.name).includes(q)) : courses;
  }, [courses, query]);

  const selected = typeof value === 'number' ? courses.find((c) => c.id === value) : null;
  const similarityOf = (id: number) => (suggestion?.course_id === id ? suggestion.similarity : null);

  const pick = (v: Choice) => {
    onChange(v);
    setOpen(false);
    setQuery('');
  };

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen(!open)}
        className="w-full flex items-center justify-between gap-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-left text-sm"
      >
        <span className="truncate">
          {value === IGNORE && (
            <span className="inline-flex items-center gap-1 text-red-600 dark:text-red-400">
              <Ban className="w-4 h-4" /> No vincular
            </span>
          )}
          {selected && (
            <>
              {selected.name}
              {similarityOf(selected.id) !== null && (
                <span className="ml-2 text-xs text-[#0C647A] dark:text-[#1CBCE8]">
                  sugerido · {similarityOf(selected.id)}%
                </span>
              )}
            </>
          )}
          {value === null && <span className="text-gray-400">Elegir curso…</span>}
        </span>
        <ChevronDown className="w-4 h-4 shrink-0 text-gray-400" />
      </button>

      {open && (
        <div className="absolute z-20 mt-1 w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg">
          <div className="flex items-center gap-2 border-b border-gray-200 dark:border-gray-700 px-3 py-2">
            <Search className="w-4 h-4 text-gray-400" />
            <input
              autoFocus
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder={`Buscar curso para «${syllabusName}»…`}
              className="w-full bg-transparent text-sm outline-none"
            />
          </div>

          <ul className="max-h-64 overflow-y-auto py-1 text-sm">
            <li>
              <button
                type="button"
                onClick={() => pick(IGNORE)}
                className="w-full flex items-center gap-2 px-3 py-2 text-left text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
              >
                <Ban className="w-4 h-4" /> No vincular (marcar como ignorado)
              </button>
            </li>

            {filtered.map((c) => (
              <li key={c.id}>
                <button
                  type="button"
                  onClick={() => pick(c.id)}
                  className={`w-full px-3 py-2 text-left hover:bg-[#E7F9FD] dark:hover:bg-[#1CBCE8]/10 ${
                    value === c.id ? 'bg-[#E7F9FD] dark:bg-[#1CBCE8]/10' : ''
                  }`}
                >
                  <div className="flex items-center justify-between gap-2">
                    <span>{c.name}</span>
                    {similarityOf(c.id) !== null && (
                      <span className="shrink-0 text-xs text-[#0C647A] dark:text-[#1CBCE8]">
                        sugerido · {similarityOf(c.id)}%
                      </span>
                    )}
                  </div>
                  {c.careers.length > 0 && (
                    <div className="text-xs text-gray-500 dark:text-gray-400">{c.careers.join(' · ')}</div>
                  )}
                </button>
              </li>
            ))}

            {filtered.length === 0 && (
              <li className="px-3 py-2 text-gray-500 dark:text-gray-400">No hay cursos sin código con ese nombre.</li>
            )}
          </ul>
        </div>
      )}
    </div>
  );
}
