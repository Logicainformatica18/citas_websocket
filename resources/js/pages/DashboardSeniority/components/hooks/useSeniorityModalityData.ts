import { useEffect, useState } from "react";
import axios from "axios";
import { usePage } from "@inertiajs/react";

export function useSeniorityModalityData() {
  const { filters } = usePage().props as any;

  const [data, setData] = useState({
    remote: 0,
    hybrid: 0,
    onsite: 0,
  });

  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);

    axios
      .get("/dashboard/indicators/seniority/modality", {
        params: {
          year: filters?.year,
          period: filters?.period,
        },
      })
      .then((res) => {
        setData(res.data.data);
      })
      .finally(() => setLoading(false));
  }, [filters?.year, filters?.period]);

  return { data, loading };
}
