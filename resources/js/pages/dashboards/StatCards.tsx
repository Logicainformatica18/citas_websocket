interface StatCardsProps {
  stats: {
    totalSupports: number;
    totalDetails: number;
  };
}

export default function StatCards({ stats }: StatCardsProps) {
  return (
    <div className="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-4">
      <div className="bg-white dark:bg-gray-900 border rounded-xl p-4">
        <h2 className="text-sm text-gray-500 dark:text-gray-400">Total Soportes</h2>
        <p className="text-3xl font-bold text-gray-900 dark:text-white">{stats.totalSupports}</p>
      </div>
      <div className="bg-white dark:bg-gray-900 border rounded-xl p-4">
        <h2 className="text-sm text-gray-500 dark:text-gray-400">Total Detalles</h2>
        <p className="text-3xl font-bold text-gray-900 dark:text-white">{stats.totalDetails}</p>
      </div>
    </div>
  );
}
