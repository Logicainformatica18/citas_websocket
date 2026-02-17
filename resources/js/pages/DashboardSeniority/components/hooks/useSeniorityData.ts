import { useEffect, useState } from "react";
import axios from "axios";
import { usePage } from "@inertiajs/react";

export interface CareerSeniority {
  career_id: number;
  career_name: string;
  distribution: {
    seniority: "junior" | "mid" | "senior";
    jobs: number;
    percentage: number;
  }[];
}

export function useSeniorityData() {
  const pageProps = usePage().props as any;
const filters = pageProps?.filters ?? {};

  const [data, setData] = useState<CareerSeniority[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);

    axios
      .get("/dashboard/indicators/seniority/distribution-by-career", {
        params: {
          year: filters?.year,
          period: filters?.period,
          career: filters?.career,
        },
      })
      .then((res) => {
        setData(res.data?.data ?? []);
      })
      .catch(() => {
        setData([]);
      })
      .finally(() => {
        setLoading(false);
      });
  }, [filters?.year, filters?.period, JSON.stringify(filters?.career)]);

  return { data, loading };
}
