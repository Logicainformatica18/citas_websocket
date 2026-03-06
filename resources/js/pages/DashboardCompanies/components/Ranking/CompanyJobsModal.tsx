"use client"

import { useEffect, useState } from "react"

export default function CompanyJobsModal({ company, open, onClose }: any) {
  const [jobs, setJobs] = useState([])
  const [page, setPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)

  useEffect(() => {
    if (!company) return

    const fetchJobs = async () => {
      const res = await fetch(
        `/api/jobs?company=${company}&page=${page}`
      )

      const data = await res.json()

      setJobs(data.jobs)
      setTotalPages(data.totalPages)
    }

    fetchJobs()
  }, [company, page])

  if (!open) return null

  return (
    <div className="fixed inset-0 bg-black/40 flex items-center justify-center">
      <div className="bg-white w-[700px] rounded-xl p-6">
        <div className="flex justify-between mb-4">
          <h2 className="text-lg font-semibold">
            Empleos en {company}
          </h2>

          <button onClick={onClose}>Cerrar</button>
        </div>

        <div className="space-y-2">
          {jobs.map((job: any) => (
            <div
              key={job.id}
              className="border p-3 rounded-lg"
            >
              <div className="font-medium">{job.title}</div>
              <div className="text-sm text-muted-foreground">
                {job.location}
              </div>
            </div>
          ))}
        </div>

        {/* paginación */}
        <div className="flex justify-between mt-6">
          <button
            disabled={page === 1}
            onClick={() => setPage(page - 1)}
          >
            Anterior
          </button>

          <span>
            Página {page} de {totalPages}
          </span>

          <button
            disabled={page === totalPages}
            onClick={() => setPage(page + 1)}
          >
            Siguiente
          </button>
        </div>
      </div>
    </div>
  )
}
