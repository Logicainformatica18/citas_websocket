import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {
    Database,
    Calendar,
    Clock,
    Activity,
} from "lucide-react";

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    data?: any;
}

export function JobMarketStatusModal({
    open,
    onOpenChange,
    data,
}: Props) {
    if (!open) return null;

    const global = data?.global;
    const period = data?.period;

    const Item = ({
        icon: Icon,
        label,
        value,
        hint,
    }: {
        icon: any;
        label: string;
        value: string | number;
        hint?: string;
    }) => (
        <div className="flex gap-3 rounded-xl border bg-[#F8FCFE] p-4 dark:bg-[#0F2A3A]">
            <Icon className="mt-0.5 h-4 w-4 text-[#00B6E8]" />
            <div className="space-y-0.5">
                <p className="text-xs font-semibold text-[#005F7A] dark:text-slate-300">
                    {label}
                </p>
                <p className="text-sm font-bold text-[#0A2540] dark:text-slate-100">
                    {value}
                </p>
                {hint && (
                    <p className="text-[11px] text-slate-500 dark:text-slate-400">
                        {hint}
                    </p>
                )}
            </div>
        </div>
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Database className="h-5 w-5 text-[#00B6E8]" />
                        Estado del mercado laboral
                    </DialogTitle>
                </DialogHeader>

                {/* ===================== MÉTRICAS GLOBALES ===================== */}
                <div className="grid gap-4 pt-4 sm:grid-cols-2">
                    <Item
                        icon={Database}
                        label="Total histórico de ofertas"
                        value={
                            global?.offers_total
                                ? global.offers_total.toLocaleString()
                                : "—"
                        }
                        hint={
                            global?.history_age
                                ? `Histórico de ${global.history_age}`
                                : undefined
                        }
                    />

                    <Item
                        icon={Calendar}
                        label="Nuevas ofertas este mes"
                        value={
                            global?.offers_new_month
                                ? `+${global.offers_new_month.toLocaleString()}`
                                : "—"
                        }
                    />

                    <Item
                        icon={Clock}
                        label="Última actualización del sistema"
                        value={global?.last_update?.human ?? "—"}
                        hint={global?.last_update?.at ?? undefined}
                    />

                    <Item
                        icon={Clock}
                        label="Última oferta publicada"
                        value={global?.last_published?.human ?? "—"}
                        hint={global?.last_published?.at ?? undefined}
                    />
                </div>

                {/* ===================== PERÍODO ===================== */}
                {period && (
                    <div className="mt-6 space-y-4">
                        <p className="text-xs font-bold uppercase tracking-wide text-[#00B6E8]">
                            Período analizado
                        </p>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Item
                                icon={Activity}
                                label="Ofertas analizadas en el período"
                                value={period.offers_analysed.toLocaleString()}
                                hint={`${period.date_range.from} → ${period.date_range.to}`}
                            />

                            <Item
                                icon={Calendar}
                                label="Promedio diario"
                                value={`${Math.round(period.avg_per_day).toLocaleString()} ofertas/día`}
                                hint={`${Math.round(period.days_covered).toLocaleString()} días cubiertos`}
                            />
                        </div>
                    </div>
                )}

                <p className="pt-6 text-xs text-slate-500 dark:text-slate-400">
                    Métricas globales del mercado laboral y datos ajustados al
                    período seleccionado.
                </p>
            </DialogContent>
        </Dialog>
    );
}
