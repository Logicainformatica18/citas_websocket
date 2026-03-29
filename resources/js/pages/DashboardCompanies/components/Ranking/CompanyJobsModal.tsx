"use client"

import { useEffect, useState } from "react"
import axios from "axios"
import {
  Building2,
  MapPin,
  Calendar,
  ChevronLeft,
  ChevronRight,
  X,
  ExternalLink
} from "lucide-react"

type Job = {
  id: number
  title: string
  country: string
  published_at: string
  url: string
}

type Props = {
  company: string | null
  country?: string | null
  open: boolean
  onClose: () => void
}

export default function CompanyJobsModal({ company, country, open, onClose }) {

  const [jobs, setJobs] = useState<Job[]>([])
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState<any>(null)
  const [loading, setLoading] = useState(false)

useEffect(() => {
  if (company && open) {
    loadJobs(1)
  }
}, [company, open, country])

  async function loadJobs(pageNumber: number) {

    if (!company) return

    setLoading(true)

    try {

    const res = await axios.get(
  `/dashboard/indicators/companies/${encodeURIComponent(company)}/jobs`,
  {
  params: {
  page: pageNumber,
  ...(country ? { country } : {})
}
  }
)

      setJobs(res.data.data)
      setPagination(res.data)
      setPage(pageNumber)

    } catch (error) {

      console.error("Error cargando empleos", error)

    } finally {

      setLoading(false)

    }
  }

  if (!open) return null

  return (

    <div
      className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50"
      onClick={onClose}
    >

      <div
        onClick={(e) => e.stopPropagation()}
        className="w-[780px] max-h-[85vh] overflow-y-auto rounded-2xl shadow-2xl
        bg-white dark:bg-slate-900
        border border-slate-200 dark:border-slate-700
        p-6"
      >

        {/* HEADER */}

        <div className="flex justify-between items-center mb-6">

          <div className="flex items-center gap-3">

            <div className="bg-blue-600 text-white p-2 rounded-lg">
              <Building2 size={20} />
            </div>

            <div>

              <h2 className="text-xl font-semibold text-slate-900 dark:text-white">
                Empleos en {company}
              </h2>

              {pagination && (
                <p className="text-sm text-slate-500 dark:text-slate-400">
                  {pagination.total} vacantes encontradas
                </p>
              )}

            </div>

          </div>

          <button
            onClick={onClose}
            className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"
          >
            <X size={18} />
          </button>

        </div>

        {/* LOADING */}

        {loading ? (

          <div className="py-16 text-center text-slate-500 dark:text-slate-400">
            Cargando empleos...
          </div>

        ) : (

          <>

            {/* TABLA */}

            <div className="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">

              <table className="w-full text-sm">

                <thead className="bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">

                  <tr className="text-slate-600 dark:text-slate-300">

                    <th className="p-3 text-left">
                      Puesto
                    </th>

                    <th className="p-3 text-left">
                      País
                    </th>

                    <th className="p-3 text-left">
                      Publicado
                    </th>

                    <th className="p-3 text-center">
                      Oferta
                    </th>

                  </tr>

                </thead>

                <tbody>

                  {jobs.length === 0 && (

                    <tr>

                      <td colSpan={4} className="py-10 text-center text-slate-500 dark:text-slate-400">
                        No hay empleos registrados
                      </td>

                    </tr>

                  )}

                  {jobs.map((job) => (

                    <tr
                      key={job.id}
                      className="border-b border-slate-200 dark:border-slate-700
                      hover:bg-slate-50 dark:hover:bg-slate-800 transition"
                    >

                      <td className="p-3 font-medium text-slate-800 dark:text-slate-200">
                        {job.title}
                      </td>

                      <td className="p-3">

                        <div className="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                          <MapPin size={14} />
                          {job.country}
                        </div>

                      </td>

                      <td className="p-3">

                        <div className="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                          <Calendar size={14} />
                          {job.published_at}
                        </div>

                      </td>

                      {/* ENLACE A LA OFERTA */}

                      <td className="p-3 text-center">

                        {job.url ? (

                          <a
                            href={job.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1 text-blue-600
                            hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300
                            font-medium"
                          >
                            Ver
                            <ExternalLink size={14} />
                          </a>

                        ) : (

                          <span className="text-slate-400 text-sm">
                            —
                          </span>

                        )}

                      </td>

                    </tr>

                  ))}

                </tbody>

              </table>

            </div>

            {/* PAGINACIÓN */}

            {pagination && pagination.last_page > 1 && (

              <div className="flex items-center justify-center gap-4 mt-6">

                <button
                  disabled={page === 1}
                  onClick={() => loadJobs(page - 1)}
                  className="flex items-center gap-1 px-3 py-1 rounded-lg border
                  border-slate-300 dark:border-slate-700
                  hover:bg-slate-100 dark:hover:bg-slate-800
                  disabled:opacity-40"
                >
                  <ChevronLeft size={16} />
                  Anterior
                </button>

                <span className="text-sm text-slate-600 dark:text-slate-400">
                  Página {pagination.current_page} de {pagination.last_page}
                </span>

                <button
                  disabled={page === pagination.last_page}
                  onClick={() => loadJobs(page + 1)}
                  className="flex items-center gap-1 px-3 py-1 rounded-lg border
                  border-slate-300 dark:border-slate-700
                  hover:bg-slate-100 dark:hover:bg-slate-800
                  disabled:opacity-40"
                >
                  Siguiente
                  <ChevronRight size={16} />
                </button>

              </div>

            )}

          </>

        )}

      </div>

    </div>

  )
}
