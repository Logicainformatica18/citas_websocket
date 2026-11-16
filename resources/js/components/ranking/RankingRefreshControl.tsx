import { useEffect, useRef, useState } from "react";
import { router } from "@inertiajs/react";
import axios from "axios";
import Swal from "sweetalert2";
import { Clock, RefreshCw } from "lucide-react";
import { Badge } from "@/components/ui/badge";

interface RankingRefreshControlProps {
  /** p. ej. "/dashboard/ranking/languages" (usa {basePath}/refresh y {basePath}/refresh-status) */
  basePath: string;
  /** Fecha del último cálculo en hora de Perú ("12/11/2026 03:25 p. m."), o null */
  calculatedAtLabel?: string | null;
  /** Solo administradores ven el botón */
  canRefresh: boolean;
  /** Semestre que se está viendo: el botón recalcula ese semestre (ranking + evolución) */
  year: number;
  period: "s1" | "s2";
}

/**
 * Fecha de última actualización + botón "Recalcular ranking", compartido por los rankings.
 * POST encola el Job para el semestre que se está viendo; luego se consulta el estado
 * cada 3 s hasta que termine (done / failed).
 */
export function RankingRefreshControl({ basePath, calculatedAtLabel, canRefresh, year, period }: RankingRefreshControlProps) {
  const [refreshing, setRefreshing] = useState(false);
  const pollRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => () => {
    if (pollRef.current) clearTimeout(pollRef.current);
  }, []);

  const finishRefresh = () => {
    if (pollRef.current) clearTimeout(pollRef.current);
    pollRef.current = null;
    setRefreshing(false);
  };

  const pollRefreshStatus = (startedAt: number) => {
    pollRef.current = setTimeout(async () => {
      try {
        const { data } = await axios.get(`${basePath}/refresh-status`);

        if (data?.state === "done") {
          finishRefresh();
          router.reload();
          Swal.fire({
            icon: "success",
            title: "Ranking actualizado",
            timer: 1600,
            showConfirmButton: false,
          });
          return;
        }

        if (data?.state === "failed") {
          finishRefresh();
          router.reload();
          Swal.fire({
            icon: "error",
            title: "No se pudo recalcular",
            text: data?.message ?? "Revisa el log del servidor.",
          });
          return;
        }

        // Sigue en cola o corriendo. Si pasan 3 min, probablemente no hay worker.
        if (Date.now() - startedAt > 3 * 60 * 1000) {
          finishRefresh();
          Swal.fire({
            icon: "warning",
            title: "El recálculo sigue en cola",
            text: "Se ejecutará cuando el worker de colas lo tome. Recarga la página en unos minutos.",
          });
          return;
        }

        pollRefreshStatus(startedAt);
      } catch {
        finishRefresh();
        Swal.fire("Error", "No se pudo consultar el estado del recálculo.", "error");
      }
    }, 3000);
  };

  const refreshRanking = async () => {
    setRefreshing(true);

    try {
      await axios.post(`${basePath}/refresh`, { year, period });
      pollRefreshStatus(Date.now());
    } catch (error) {
      setRefreshing(false);
      const response = axios.isAxiosError(error) ? error.response : undefined;
      const status = response?.status;

      if (status === 409) {
        Swal.fire({
          icon: "info",
          title: "Ya hay un recálculo en curso",
          text: "Espera a que termine y recarga la página.",
        });
      } else if (status === 429) {
        Swal.fire({
          icon: "info",
          title: "Demasiados intentos",
          text: "Espera un minuto antes de volver a recalcular.",
        });
      } else {
        Swal.fire({
          icon: "error",
          title: "Error",
          text: response?.data?.message ?? "No se pudo iniciar el recálculo.",
        });
      }
    }
  };

  return (
    <>
      <Badge className="gap-1.5 bg-white text-[#0A2540] shadow">
        <Clock className="h-3 w-3 text-teal-400" />
        {calculatedAtLabel ? `Actualizado: ${calculatedAtLabel} (hora de Perú)` : "Sin calcular"}
      </Badge>

      {canRefresh && (
        <button
          onClick={refreshRanking}
          disabled={refreshing}
          className="
          flex items-center gap-2
          rounded-xl border border-teal-400/60 bg-white
          px-3 py-2
          text-sm font-semibold text-teal-600
          shadow transition
          hover:bg-teal-50
          disabled:cursor-not-allowed disabled:opacity-60
          "
        >
          <RefreshCw className={`h-4 w-4 ${refreshing ? "animate-spin" : ""}`} />
          {refreshing ? "Recalculando..." : "Recalcular ranking"}
        </button>
      )}
    </>
  );
}
