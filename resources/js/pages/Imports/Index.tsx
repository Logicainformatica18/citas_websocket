import { useForm } from "@inertiajs/react";

export default function ImportIndex({ imports }: { imports: any[] }) {
  const { post } = useForm();

  return (
    <div className="p-6 text-white">
      <h1 className="text-2xl font-bold mb-4">Importar CSV de Ofertas</h1>

      <form
        onSubmit={(e) => {
          e.preventDefault();
          const input = document.querySelector<HTMLInputElement>("#file");
          if (input?.files?.length) {
            const form = new FormData();
            form.append("file", input.files[0]);
            post(route("imports.upload"), form);
          }
        }}
        className="mb-6"
      >
        <input id="file" type="file" accept=".csv" className="text-white" />
        <button className="ml-3 px-4 py-2 bg-blue-600 rounded hover:bg-blue-700">
          Subir CSV
        </button>
      </form>

      <table className="w-full border border-slate-700 text-sm">
        <thead className="bg-slate-700 text-left">
          <tr>
            <th className="p-2">Archivo</th>
            <th className="p-2">Estado</th>
            <th className="p-2">Acción</th>
          </tr>
        </thead>
        <tbody>
          {imports.map((imp) => (
            <tr key={imp.id} className="border-b border-slate-700">
              <td className="p-2">{imp.filename}</td>
              <td className="p-2">{imp.status}</td>
              <td className="p-2">
                {imp.status === "pending" && (
                  <a
                    href={route("imports.map", imp.id)}
                    className="text-blue-400 hover:underline"
                  >
                    Mapear
                  </a>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
