import { Dialog } from "@headlessui/react";
import { useEffect, useState } from "react";
import axios from "axios";
import { X, ExternalLink } from "lucide-react";
import JobOfferCard from "./JobOfferCard";
type Job = {
    id: number;
    title: string;
    company: string;
    location?: string;
    modality?: string;
    source?: string;
    url?: string;
};

type Props = {
    open: boolean;
    onClose: () => void;
    certificationId: number | null;
    certificationName?: string;
};

export default function CertificationJobsModal({
    open,
    onClose,
    certificationId,
    certificationName,
}: Props) {
    const [jobs, setJobs] = useState<Job[]>([]);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [loading, setLoading] = useState(false);

    const fetchJobs = async (pageNumber: number) => {
        if (!certificationId) return;

        setLoading(true);

        const res = await axios.get(
            `/dashboard/ranking-certificaciones/${certificationId}/jobs`,
            {
                params: { page: pageNumber, per_page: 10 },
            }
        );

        setJobs(res.data.data);
        setPage(res.data.current_page);
        setLastPage(res.data.last_page);

        setLoading(false);
    };

    useEffect(() => {
        if (open && certificationId) {
            fetchJobs(1);
        }
    }, [open, certificationId]);

    return (
        <Dialog open={open} onClose={onClose} className="relative z-50">
            {/* BACKDROP */}
            <div className="fixed inset-0 bg-black/40" />

            {/* WRAPPER */}
            <div className="fixed inset-0 flex items-center justify-center p-4">
                <Dialog.Panel
                    className="
            w-full
            max-w-6xl
            bg-white
            rounded-2xl
            shadow-xl
            flex
            flex-col
            max-h-[85vh]
          "
                >
                    {/* ================= HEADER ================= */}
                    <div className="flex items-center justify-between px-6 py-4 border-b shrink-0">
                        <div>
                            <Dialog.Title className="text-lg font-semibold">
                                Ofertas laborales
                            </Dialog.Title>
                            <p className="text-sm text-gray-500">
                                {certificationName}
                            </p>
                        </div>

                        <button onClick={onClose}>
                            <X />
                        </button>
                    </div>

                    {/* ================= BODY (SCROLL AQUÍ) ================= */}
                    <div
                        className="
              flex-1
              overflow-y-auto
              px-6
              py-4
            "
                    >
                        {loading ? (
                            <p className="text-center py-10 text-gray-500">
                                Cargando ofertas…
                            </p>
                        ) : (
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                {jobs.map((job, index) => (
                                    <JobOfferCard
                                        key={job.id}
                                        index={(page - 1) * 10 + index}
                                        job={job}
                                    />
                                ))}
                            </div>

                        )}
                    </div>

                    {/* ================= FOOTER ================= */}
                    <div className="flex items-center justify-between px-6 py-4 border-t shrink-0">
                        <button
                            disabled={page === 1}
                            onClick={() => fetchJobs(page - 1)}
                            className="px-4 py-2 border rounded disabled:opacity-40"
                        >
                            ← Anterior
                        </button>

                        <span className="text-sm text-gray-500">
                            Página {page} de {lastPage}
                        </span>

                        <button
                            disabled={page === lastPage}
                            onClick={() => fetchJobs(page + 1)}
                            className="px-4 py-2 border rounded disabled:opacity-40"
                        >
                            Siguiente →
                        </button>
                    </div>
                </Dialog.Panel>
            </div>
        </Dialog>
    );
}
