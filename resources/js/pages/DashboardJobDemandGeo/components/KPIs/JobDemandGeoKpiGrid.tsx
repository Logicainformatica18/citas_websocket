import { MapPin, Building2, TrendingUp, Percent } from "lucide-react";

export default function JobDemandGeoKpiGrid({ meta }: { meta: any }) {
    const items = [
        {
            label: "Ofertas analizadas",
            value: meta.total_jobs,
            icon: TrendingUp,
        },
        {
            label: "Ciudades activas",
            value: meta.cities_count,
            icon: Building2,
        },
        {
            label: "Ciudad líder",
            value: meta.top_city ?? "—",
            icon: MapPin,
        },
        {
            label: "Concentración Top 5",
            value: `${meta.top5_concentration}%`,
            icon: Percent,
        },
    ];

    return (
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            {items.map((item) => (
                <div
                    key={item.label}
                    className="bg-white dark:bg-[#0F2A3A] border rounded-xl p-4"
                >
                    <div className="flex items-center gap-3">
                        <item.icon className="w-5 h-5 text-[#1CBCE8]" />
                        <p className="text-sm text-slate-500">{item.label}</p>
                    </div>
                    <p className="text-2xl font-semibold mt-2 text-slate-900 dark:text-slate-100">
                        {item.value}
                    </p>
                </div>
            ))}
        </div>
    );
}
