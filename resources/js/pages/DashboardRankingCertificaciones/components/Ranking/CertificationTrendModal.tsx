import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Badge } from "@/components/ui/badge";
import {
  TrendingUp,
  ExternalLink,
  FileText,
  ChevronLeft,
  ChevronRight,
  Calendar,
} from "lucide-react";
import { useState } from "react";

interface CertificationTrendModalProps {
  open: boolean;
  certification: any;
  trends: any[];              // data plana (o paginator.data)
  pagination?: any;           // opcional
  onClose: () => void;
  onPageChange?: (page: number) => void;
}

export default function CertificationTrendModal({
  open,
  certification,
  trends,
  pagination,
  onClose,
  onPageChange,
}: CertificationTrendModalProps) {
  if (!certification) return null;

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-4xl">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <TrendingUp className="w-5 h-5 text-cyan-600" />
            Fuentes de tendencia – {certification.name}
          </DialogTitle>
        </DialogHeader>

        {/* LISTADO */}
        <div className="max-h-[55vh] overflow-y-auto pr-4 space-y-4">
          {trends.length === 0 ? (
            <div className="py-6 text-center text-sm text-muted-foreground">
              No se encontraron fuentes asociadas.
            </div>
          ) : (
            trends.map((item: any) => (
              <div
                key={item.id}
                className="rounded-xl border p-4 bg-white dark:bg-slate-900 hover:shadow-sm transition"
              >
                <div className="flex items-start justify-between gap-4">
                  <div className="flex gap-3">
                    <FileText className="w-5 h-5 text-cyan-600 mt-1" />

                    <div>
                      <h4 className="font-semibold text-slate-900 dark:text-slate-100">
                        {item.source_title ?? "Fuente sin título"}
                      </h4>

                      <div className="mt-1 flex items-center gap-2 text-xs text-slate-500">
                        <Calendar className="w-3 h-3" />
                        {item.created_at}
                      </div>
                    </div>
                  </div>

                  {item.trend_score && (
                    <Badge variant="secondary">
                      Score {Number(item.trend_score).toFixed(1)}
                    </Badge>
                  )}
                </div>

                <div className="mt-3 flex items-center justify-between">
                  <Badge variant="outline">
                    {item.source_type ?? "desconocido"}
                  </Badge>

                  {item.source_url && (
                    <a
                      href={item.source_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-1 text-sm text-cyan-600 hover:underline"
                    >
                      Ver fuente
                      <ExternalLink className="w-4 h-4" />
                    </a>
                  )}
                </div>
              </div>
            ))
          )}
        </div>

        {/* PAGINACIÓN */}
        {pagination && (
          <div className="mt-4 flex items-center justify-between border-t pt-3">
            <span className="text-xs text-slate-500">
              Página {pagination.current_page} de {pagination.last_page}
            </span>

            <div className="flex gap-2">
              <button
                disabled={!pagination.prev_page_url}
                onClick={() =>
                  onPageChange?.(pagination.current_page - 1)
                }
                className="flex items-center gap-1 rounded-md border px-2 py-1 text-sm disabled:opacity-40"
              >
                <ChevronLeft className="w-4 h-4" />
                Anterior
              </button>

              <button
                disabled={!pagination.next_page_url}
                onClick={() =>
                  onPageChange?.(pagination.current_page + 1)
                }
                className="flex items-center gap-1 rounded-md border px-2 py-1 text-sm disabled:opacity-40"
              >
                Siguiente
                <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
