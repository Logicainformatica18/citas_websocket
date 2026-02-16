import { Badge } from "@/components/ui/badge";
import { TrendingUp, AlertTriangle, Zap } from "lucide-react";

interface Props {
  course: any;
}

export function CourseAlignmentInsightCard({ course }: Props) {
  return (
    <div className="rounded-2xl border bg-white dark:bg-[#102C3C] p-6 shadow-lg">

      <div className="flex justify-between items-start">
        <h3 className="text-lg font-bold text-[#0A2540] dark:text-white">
          {course.name}
        </h3>

        <Badge className="bg-[#00B6E8] text-white">
          Score {course.score}
        </Badge>
      </div>

      <div className="mt-4 grid grid-cols-3 gap-4 text-sm">

        <div className="flex items-center gap-2">
          <TrendingUp className="w-4 h-4 text-green-500" />
          <span>
            Coverage: <b>{course.coverage}</b>
          </span>
        </div>

        <div className="flex items-center gap-2">
          <Zap className="w-4 h-4 text-yellow-500" />
          <span>
            Intensidad: <b>{course.intensity}</b>
          </span>
        </div>

        <div className="flex items-center gap-2">
          <AlertTriangle className="w-4 h-4 text-red-500" />
          <span>
            Brecha: <b>{course.gap_count}</b>
          </span>
        </div>
      </div>

      <div className="mt-4 flex flex-wrap gap-2">
        {course.aligned_entities.map((e: any) => (
          <Badge
            key={e.id}
            variant="outline"
            className="text-xs border-[#00B6E8]"
          >
            {e.name}
          </Badge>
        ))}
      </div>
    </div>
  );
}
