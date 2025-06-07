import { Button } from '@/components/ui/button';

export default function ArticleListTable({
  articles,
  onDelete,
  onSubmit,
}: {
  articles: any[];
  onDelete: (index: number) => void;
  onSubmit: () => void;
}) {
  return (
    <div className="mt-6">
      <h2 className="text-lg font-semibold mb-4">Artículos agregados</h2>
      {articles.length > 0 ? (
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200 bg-white dark:bg-black shadow-md rounded">
            <thead className="bg-gray-100 dark:bg-gray-800">
              <tr>
                <th className="px-4 py-2 text-left text-black dark:text-white">Artículo</th>
                <th className="px-4 py-2 text-left text-black dark:text-white">Cantidad</th>
                <th className="px-4 py-2 text-left text-black dark:text-white">Estado</th>
                <th className="px-4 py-2 text-left text-black dark:text-white">Imagen 1</th>
                <th className="px-4 py-2 text-left text-black dark:text-white">Imagen 2</th>
                <th className="px-4 py-2 text-left text-black dark:text-white">Imagen 3</th>
                <th className="px-4 py-2 text-left text-black dark:text-white">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {articles.map((article, index) => (
                <tr key={index} className="border-t hover:bg-gray-50 dark:hover:bg-gray-700">
                  <td className="px-4 py-2 text-black dark:text-white">{article.description}</td>
                  <td className="px-4 py-2 text-black dark:text-white">{article.quanty}</td>
                  <td className="px-4 py-2 text-black dark:text-white">{article.state}</td>
                  {[1, 2, 3].map((num) => {
                    const field = `file_${num}`;
                    const file = article[field];
                    return (
                      <td key={field} className="px-4 py-2 text-black dark:text-white">
                        {file ? (
                          file instanceof File ? (
                            <img
                              src={URL.createObjectURL(file)}
                              alt="Imagen"
                              className="w-12 h-12 object-cover rounded"
                            />
                          ) : (
                            <a
                              href={`/uploads/${file}`}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="text-blue-600 underline text-xs"
                            >
                              Ver archivo
                            </a>
                          )
                        ) : (
                          <span className="text-xs text-gray-400">Sin imagen</span>
                        )}
                      </td>
                    );
                  })}
                  <td className="px-4 py-2">
                    <Button
                      variant="destructive"
                      size="sm"
                      onClick={() => onDelete(index)}
                    >
                      Eliminar
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          <div className="flex justify-end mt-4">
            <Button onClick={onSubmit}>Guardar Todo</Button>
          </div>
        </div>
      ) : (
        <p className="text-gray-600 text-sm">No hay artículos agregados aún.</p>
      )}
    </div>
  );
}
