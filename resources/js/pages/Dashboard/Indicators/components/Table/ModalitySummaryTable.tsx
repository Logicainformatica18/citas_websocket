interface Item {
  modalidad: string;
  vacantes: number;
  porcentaje: number;
}

export default function ModalitySummaryTable({ data }: { data: Item[] }) {

  const validModalities = ["remoto", "híbrido", "presencial"];

  const filteredData = data.filter((row) =>
    validModalities.includes(row.modalidad)
  );

  return (
    <div className="rounded-2xl border bg-white shadow-sm dark:bg-[#0F2A3A] dark:border-slate-700">
      <table className="w-full text-sm">
        <thead className="bg-slate-50 dark:bg-slate-800">
          <tr>
            <th className="px-4 py-3 text-left font-semibold">Modalidad</th>
            <th className="px-4 py-3 text-right font-semibold">Vacantes</th>
            <th className="px-4 py-3 text-right font-semibold">%</th>
          </tr>
        </thead>

        <tbody>
          {filteredData.map((row) => (
            <tr
              key={row.modalidad}
              className="border-t dark:border-slate-700"
            >
              <td className="px-4 py-3 capitalize">
                {row.modalidad}
              </td>

              <td className="px-4 py-3 text-right">
                {row.vacantes.toLocaleString()}
              </td>

              <td className="px-4 py-3 text-right">
                {row.porcentaje.toFixed(2)}%
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
