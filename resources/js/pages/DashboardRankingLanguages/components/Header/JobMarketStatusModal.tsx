import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Badge } from "@/components/ui/badge";
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import {
  Database,
  Calendar,
  Activity,
  TrendingUp,
  AlertTriangle,
  CheckCircle2,
  Loader2,
  CalendarClock,
  HelpCircle,
} from "lucide-react";

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  data?: any;              // JobMarketStatusController
  scrapingStatus?: any;    // ScrapingStatusService
}

export function JobMarketStatusModal({
  open,
  onOpenChange,
  data,
  scrapingStatus,
}: Props) {
  if (!open) return null;

  const global = data?.global;
  const period = data?.period;

  /* =========================
     ESTADO DEL SISTEMA
  ========================= */

  const StatusIcon = () => {
    if (!scrapingStatus) return null;
    if (scrapingStatus.is_running)
      return <Loader2 className="h-4 w-4 animate-spin text-blue-500" />;
    if (scrapingStatus.is_failed)
      return <AlertTriangle className="h-4 w-4 text-red-500" />;
    return <CheckCircle2 className="h-4 w-4 text-emerald-500" />;
  };

  const StatusLabel = () => {
    if (!scrapingStatus) return "Sin información";
    if (scrapingStatus.is_running) return "Scraping en ejecución";
    if (scrapingStatus.is_failed) return "Última ejecución fallida";
    if (scrapingStatus.is_stale) return "Datos desactualizados";
    return "Sistema actualizado";
  };

  const SourceBadge = () => {
    if (!scrapingStatus?.source) return null;
    return (
      <Badge className="bg-[#E6F7FD] text-[#005F7A]">
        Fuente: {scrapingStatus.source}
      </Badge>
    );
  };

  /* =========================
     TOOLTIP HELP
  ========================= */

  const Help = ({ text }: { text: string }) => (
    <TooltipProvider delayDuration={150}>
      <Tooltip>
        <TooltipTrigger asChild>
          <span className="inline-flex cursor-help">
            <HelpCircle className="h-4 w-4 text-slate-400 hover:text-[#00B6E8]" />
          </span>
        </TooltipTrigger>
        <TooltipContent
          side="top"
          className="max-w-xs text-xs leading-relaxed"
        >
          {text}
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );

  /* =========================
     CARD ITEM
  ========================= */

  const Item = ({
    icon: Icon,
    label,
    value,
    hint,
    help,
  }: {
    icon: any;
    label: string;
    value: string | number;
    hint?: string;
    help?: string;
  }) => (
    <div className="flex gap-3 rounded-xl border bg-[#F8FCFE] p-4 dark:bg-[#0F2A3A]">
      <Icon className="mt-1 h-4 w-4 text-[#00B6E8]" />

      <div className="flex-1 space-y-0.5">
        <div className="flex items-center gap-2">
          <p className="text-xs font-semibold text-[#005F7A] dark:text-slate-300">
            {label}
          </p>
          {help && <Help text={help} />}
        </div>

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
      <DialogContent className="max-w-3xl p-0">
        {/* ===================== HEADER ===================== */}
        <DialogHeader className="border-b px-6 py-4">
          <DialogTitle className="flex items-center gap-3">
            <Database className="h-5 w-5 text-[#00B6E8]" />
            Estado del mercado laboral
          </DialogTitle>

          {scrapingStatus && (
            <div className="mt-2 flex items-center gap-3 rounded-lg bg-slate-50 px-4 py-2 dark:bg-slate-900">
              <StatusIcon />
              <span className="text-sm font-medium">{StatusLabel()}</span>
              {scrapingStatus.last_run_human && (
                <span className="text-xs text-slate-500">
                  · Última ejecución {scrapingStatus.last_run_human}
                </span>
              )}
            </div>
          )}
        </DialogHeader>

        {/* ===================== BODY (SIN SCROLL VERTICAL) ===================== */}
        <div className="px-6 py-6 space-y-8">

          {/* ÚLTIMA EJECUCIÓN */}
          {scrapingStatus?.finished_at && (
            <div className="rounded-xl border bg-[#F8FCFE] p-5 dark:bg-[#0F2A3A] space-y-3">
              <div className="flex items-center gap-2">
                <Calendar className="h-5 w-5 text-[#00B6E8]" />
                <p className="text-sm font-semibold text-[#005F7A]">
                  Última ejecución del scraping
                </p>
                <Help text="Fecha y hora exacta en la que el sistema ejecutó por última vez la recolección automática de ofertas laborales indicando la bolsa de trabajo." />
              </div>

              <p className="text-lg font-bold text-[#0A2540] dark:text-slate-100">
                {scrapingStatus.finished_at}
              </p>

              <div className="flex flex-wrap gap-2">
                <SourceBadge />
              </div>
            </div>
          )}

          {/* MÉTRICAS GLOBALES */}
          <div className="space-y-4">
            <p className="text-xs font-bold uppercase tracking-wide text-[#00B6E8]">
              Métricas globales
            </p>

            <div className="grid gap-4 sm:grid-cols-2">
              <Item
                icon={Database}
                label="Total histórico de ofertas"
                value={global?.offers_total?.toLocaleString() ?? "—"}
                hint={
                  global?.history_age
                    ? `Histórico de ${global.history_age}`
                    : undefined
                }
                help="Cantidad total de ofertas laborales recolectadas históricamente por el sistema sin importar el período e indicador seleccionado."
              />

              <Item
                icon={CalendarClock}
                label="Nuevas ofertas este mes"
                value={
                  global?.offers_new_month
                    ? `+${global.offers_new_month.toLocaleString()}`
                    : "—"
                }
                help="Número de ofertas nuevas incorporadas desde el inicio del mes actual sin importar el período e indicador seleccionado."
              />
            </div>
          </div>

          {/* PERÍODO */}
          {period && (
            <div className="space-y-4">
              <p className="text-xs font-bold uppercase tracking-wide text-[#00B6E8]">
                Período analizado
              </p>

              <div className="grid gap-4 sm:grid-cols-2">
                <Item
                  icon={Activity}
                  label="Ofertas analizadas"
                  value={period.offers_analysed.toLocaleString()}
                  hint={`${period.date_range.from} → ${period.date_range.to}`}
                  help="Cantidad de ofertas laborales consideradas dentro del período seleccionado sin importar el período e indicador seleccionado."
                />

                <Item
                  icon={TrendingUp}
                  label="Promedio diario"
                  value={`${Math.round(period.avg_per_day).toLocaleString()} ofertas/día`}
                  hint={`${period.days_covered} días cubiertos`}
                  help="Promedio de ofertas publicadas por día dentro del período analizado sin importar el período e indicador seleccionado."
                />
              </div>
            </div>
          )}
        </div>

        {/* ===================== FOOTER ===================== */}
        <div className="border-t px-6 py-3 text-xs text-slate-500 dark:text-slate-400">
          Datos del mercado laboral y estado operativo del sistema.
        </div>
      </DialogContent>
    </Dialog>
  );
}
