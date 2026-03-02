export default function JobDemandGeoMethodologyCard() {
    return (
        <div className="bg-[#F5FCFE] dark:bg-[#0F2A3A] border rounded-xl p-4 text-sm">
            <p className="font-semibold mb-2 text-slate-900 dark:text-slate-100">
                Metodología
            </p>

            <ul className="space-y-1 text-slate-700 dark:text-slate-300">
                <li>• Ofertas publicadas en el Periodo seleccionado</li>
                <li>• Agrupación por ciudad, región y país</li>
                <li>• Ranking por volumen de vacantes</li>
                <li>• Concentración calculada sobre el total</li>
            </ul>

            <p className="mt-2 text-xs text-slate-500">
                Este indicador permite identificar focos geográficos de demanda
                laboral activa.
            </p>
        </div>
    );
}
