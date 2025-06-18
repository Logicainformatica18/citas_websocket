import { BarChart, Bar, PieChart, Pie, Cell, Tooltip, XAxis, YAxis, ResponsiveContainer, Legend } from 'recharts';

const colors = ['#60a5fa', '#facc15', '#f87171', '#34d399', '#a78bfa', '#fb923c'];

interface StatChartsProps {
  stats: {
    priorities: Record<string, number>;
    internalStates: Record<string, number>;
    externalStates: Record<string, number>;
    statusGlobal: Record<string, number>;
  };
}

function toChartData(data: Record<string, number>) {
  return Object.entries(data).map(([name, value]) => ({ name, value }));
}

export default function StatCharts({ stats }: StatChartsProps) {
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div className="bg-white dark:bg-gray-900 border rounded-xl p-4">
        <h2 className="text-sm mb-2 text-gray-600 dark:text-gray-300">Prioridades</h2>
        <ResponsiveContainer width="100%" height={250}>
          <BarChart data={toChartData(stats.priorities)}>
            <XAxis dataKey="name" />
            <YAxis />
            <Tooltip />
            <Bar dataKey="value">
              {toChartData(stats.priorities).map((_, index) => (
                <Cell key={index} fill={colors[index % colors.length]} />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </div>

      <div className="bg-white dark:bg-gray-900 border rounded-xl p-4">
        <h2 className="text-sm mb-2 text-gray-600 dark:text-gray-300">Estados Internos</h2>
        <ResponsiveContainer width="100%" height={250}>
          <PieChart>
            <Pie
              data={toChartData(stats.internalStates)}
              dataKey="value"
              nameKey="name"
              outerRadius={80}
              fill="#8884d8"
              label
            >
              {toChartData(stats.internalStates).map((_, index) => (
                <Cell key={index} fill={colors[index % colors.length]} />
              ))}
            </Pie>
            <Tooltip />
            <Legend />
          </PieChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
