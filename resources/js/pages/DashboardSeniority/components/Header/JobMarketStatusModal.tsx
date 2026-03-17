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
import { LucideIcon } from "lucide-react";

/* =====================================================
   TYPES
===================================================== */

interface GlobalData {
  offers_total?: number;
  offers_new_month?: number;
  history_age?: string;
}

interface PeriodData {
  offers_analysed: number;
  avg_per_day: number;
  days_covered: number;
  date_range: {
    from: string;
    to: string;
  };
}

interface ScrapingStatus {
  is_running?: boolean;
  is_failed?: boolean;
  is_stale?: boolean;
  source?: string;

  // backend real
  finished_at?: string;
  last_finished_at?: string;
  last_run_human?: string;

  // compat futuro
  updated_at?: string;
  updated_human?: string;

  status?: string;
}

interface JobMarketData {
  global?: GlobalData;
  period?: PeriodData;
  scraping?: ScrapingStatus;
}

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  data?: JobMarketData;
  scrapingStatus?: ScrapingStatus;
}

/* =====================================================
   COMPONENT
===================================================== */

export function JobMarketStatusModal({
  open,
  onOpenChange,
  data,
  scrapingStatus,
}: Props) {
  const global = data?.global;
  const period = data?.period;

  // 🔥 fuente unificada
  const systemStatus = scrapingStatus ?? data?.scraping ?? null;

  /* =====================================================
     NORMALIZADOR (🔥 CLAVE)
  ===================================================== */

  const normalizedStatus = systemStatus
    ? {
        updated_at:
          systemStatus.updated_at ||
          systemStatus.finished_at ||
          systemStatus.last_finished_at ||
          null,

        updated_human:
          systemStatus.updated_human ||
          systemStatus.last_run_human ||
          null,

        source: systemStatus.source,
        is_running: systemStatus.is_running,
        is_failed: systemStatus.is_failed,
        is_stale: systemStatus.is_stale,
        status: systemStatus.status,
      }
    : null;

  /* =====================================================
     STATUS LOGIC
  ===================================================== */

  const getStatus = () => {
    if (!normalizedStatus)
      return { label: "Sin información", icon: null };

    if (normalizedStatus.is_running)
      return {
        label: "Actualización en ejecución",
        icon: <Loader2 className="h-4 w-4 animate-spin text-blue-500" />,
      };

    if (normalizedStatus.is_failed)
      return {
        label: "Última actualización fallida",
        icon: <AlertTriangle className="h-4 w-4 text-red-500" />,
      };

    if (normalizedStatus.is_stale)
      return {
        label: "Datos desactualizados",
        icon: <AlertTriangle className="h-4 w-4 text-yellow-500" />,
      };

    return {
      label: "Sistema actualizado",
      icon: <CheckCircle2 className="h-4 w-4 text-emerald-500" />,
    };
  };

  const status = getStatus();

  /* =====================================================
     UTIL
  ===================================================== */

  const formatValue = (value?: number) =>
    value ? value.toLocaleString() : "Sin datos";

  /* =====================================================
     TOOLTIP
  ===================================================== */

  const Help = ({ text }: { text: string }) => (
    <TooltipProvider delayDuration={150}>
      <Tooltip>
        <TooltipTrigger asChild>
          <span className="inline-flex cursor-help">
            <HelpCircle className="h-4 w-4 text-slate-400 hover:text-[#00B6E8]" />
          </span>
        </TooltipTrigger>
        <TooltipContent className="max-w-xs text-xs">
          {text}
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );

  /* =====================================================
     ITEM
  ===================================================== */

  const Item = ({
    icon: Icon,
    label,
    value,
    hint,
    help,
  }: {
    icon: LucideIcon;
    label: string;
    value: React.ReactNode;
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
          <p className="text-[11px] text-slate-500">{hint}</p>
        )}
      </div>
    </div>
  );

  /* =====================================================
     RENDER
  ===================================================== */

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-3xl p-0">

        {/* HEADER */}
        <DialogHeader className="border-b px-6 py-4">
          <DialogTitle className="flex items-center gap-3">
            <Database className="h-5 w-5 text-[#00B6E8]" />
            Datos Generales
          </DialogTitle>

          {normalizedStatus && (
            <div className="mt-2 flex items-center gap-3 rounded-lg bg-slate-50 px-4 py-2 dark:bg-slate-900">
              {status.icon}
              <span className="text-sm font-medium">
                {status.label}
              </span>

              {normalizedStatus.updated_human && (
                <span className="text-xs text-slate-500">
                  · Actualizado {normalizedStatus.updated_human}
                </span>
              )}
            </div>
          )}

          {/* estado nunca ejecutado */}
          {/* {normalizedStatus?.status === "never_run" && (
            <div className="mt-2 text-xs text-amber-600">
              ⚠️ El sistema aún no ha ejecutado el scraping
            </div>
          )} */}
        </DialogHeader>

        {/* BODY */}
        <div className="px-6 py-6 space-y-8">

          {/* Última actualización */}
          {normalizedStatus?.updated_at && (
            <div className="rounded-xl border bg-[#F8FCFE] p-5 space-y-3">
              <div className="flex items-center gap-2">
                <Calendar className="h-5 w-5 text-[#00B6E8]" />
                <p className="text-sm font-semibold text-[#005F7A]">
                  Última actualización del mercado
                </p>
                <Help text="Fecha y hora de la última ejecución del sistema." />
              </div>

              <p className="text-lg font-bold text-[#0A2540]">
                {normalizedStatus.updated_at}
              </p>

              {normalizedStatus.source && (
                <Badge className="bg-[#E6F7FD] text-[#005F7A]">
                  Fuente: {normalizedStatus.source}
                </Badge>
              )}
            </div>
          )}

          {/* MÉTRICAS */}
          <div className="space-y-4">
            <p className="text-xs font-bold uppercase text-[#00B6E8]">
              Métricas globales
            </p>

            <div className="grid gap-4 sm:grid-cols-2">
              <Item
                icon={Database}
                label="Total histórico de ofertas"
                value={formatValue(global?.offers_total)}
                hint={
                  global?.history_age
                    ? `Histórico de ${global.history_age}`
                    : undefined
                }
              />

              <Item
                icon={CalendarClock}
                label="Nuevas ofertas del mes"
                value={
                  global?.offers_new_month
                    ? `+${formatValue(global.offers_new_month)}`
                    : "Sin datos"
                }
              />
            </div>
          </div>

          {/* PERIODO */}
          {period && (
            <div className="space-y-4">
              <p className="text-xs font-bold uppercase text-[#00B6E8]">
                Periodo analizado
              </p>

              <div className="grid gap-4 sm:grid-cols-2">
                <Item
                  icon={Activity}
                  label="Ofertas analizadas"
                  value={formatValue(period.offers_analysed)}
                  hint={`${period.date_range.from} → ${period.date_range.to}`}
                />

                <Item
                  icon={TrendingUp}
                  label="Promedio diario"
                  value={`${Math.round(period.avg_per_day)} ofertas/día`}
                  hint={`${period.days_covered} días`}
                />
              </div>
            </div>
          )}
        </div>

        {/* FOOTER */}
        <div className="border-t px-6 py-3 text-xs text-slate-500">
          Datos del mercado laboral y estado del sistema.
        </div>
      </DialogContent>
    </Dialog>
  );
}

