export default function ImprovedCareersTable({ data }: { data: any }) {
  const tableData = Array.isArray(data.data)
    ? data.data
    : [];

  return (
    <div className="overflow-x-auto">
      <h4 className="text-sm text-gray-300 mb-2">{data.label}</h4>
      <table className="w-full text-sm text-gray-300">
        <thead className="bg-gray-700 text-gray-200">
          <tr>
            <th className="py-2 px-3 text-left">Carrera</th>
            <th className="py-2 px-3 text-right">Mejora</th>
          </tr>
        </thead>
        <tbody>
          {tableData.length > 0 ? (
            tableData.map((row: any, idx: number) => (
              <tr
                key={idx}
                className="border-b border-gray-700 hover:bg-gray-800 transition"
              >
                <td className="py-2 px-3">{row.carrera}</td>
                <td className="py-2 px-3 text-right text-blue-400">
                  {row.mejora ?? 0}
                </td>
              </tr>
            ))
          ) : (
            <tr>
              <td colSpan={2} className="py-4 text-center text-gray-500">
                No hay datos disponibles.
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}
