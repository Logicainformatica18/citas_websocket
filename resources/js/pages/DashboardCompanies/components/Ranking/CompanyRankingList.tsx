"use client"

import { useState } from "react"
import CompanyJobsModal from "./CompanyJobsModal"

export default function CompanyRankingList({ ranking, filters }: any) {

  const [selectedCompany, setSelectedCompany] = useState<string | null>(null)
  const [selectedCountry, setSelectedCountry] = useState<string | null>(null)
  const [open, setOpen] = useState(false)

  return (
    <>
      <div className="rounded-xl border overflow-hidden">

        <table className="w-full text-sm">

          <thead className="bg-muted">
            <tr>
              <th className="p-3 text-left">#</th>
              <th className="p-3 text-left">Empresa</th>
              <th className="p-3 text-right">Vacantes</th>
            </tr>
          </thead>

          <tbody>

            {ranking.map((row: any, i: number) => (

              <tr key={row.company} className="border-t">

                <td className="p-3">
                  {i + 1}
                </td>

                <td
                  className="p-3 font-medium text-blue-600 hover:underline cursor-pointer"
                  onClick={() => {
                    setSelectedCompany(row.company)

                    // 🔥 CLAVE: usa el country del filtro activo
                    setSelectedCountry(filters?.country || null)

                    setOpen(true)
                  }}
                >
                  {row.company}
                </td>

                <td className="p-3 text-right font-semibold">
                  {row.total_vacancies}
                </td>

              </tr>

            ))}

          </tbody>

        </table>

      </div>

      {/* MODAL */}
      <CompanyJobsModal
        company={selectedCompany}
        country={selectedCountry}
        open={open}
        onClose={() => setOpen(false)}
      />
    </>
  )
}
