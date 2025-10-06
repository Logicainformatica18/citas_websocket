import { useForm } from "@inertiajs/react";

export default function ImportMap({ job, detected, fields }: any) {
  const { data, setData, post } = useForm({
    mapping: Object.fromEntries(fields.map((f: string) => [f, ""])),
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post(route("imports.saveMapping", job.id));
  };

  return (
    <div className="p-6 text-white">
      <h1 className="text-xl font-bold mb-4">
        Mapeo de columnas para {job.filename}
      </h1>

      <form onSubmit={handleSubmit} className="space-y-3">
        {fields.map((field: string) => (
          <div key={field} className="flex items-center gap-2">
            <label className="w-40 capitalize">{field}</label>
            <select
              value={data.mapping[field]}
              onChange={(e) =>
                setData("mapping", {
                  ...data.mapping,
                  [field]: e.target.value,
                })
              }
              className="bg-slate-700 text-white rounded p-2 flex-1"
            >
              <option value="">-- Selecciona columna --</option>
              {detected.map((col: string) => (
                <option key={col} value={col}>
                  {col}
                </option>
              ))}
            </select>
          </div>
        ))}

        <button className="mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded">
          Guardar mapeo
        </button>
      </form>
    </div>
  );
}
