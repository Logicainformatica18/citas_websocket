import { useEffect, useState } from "react";
import { X, Briefcase, Sparkles, AlertTriangle, Bot } from "lucide-react";

import CourseEmploymentTab from "./tabs/CourseEmploymentTab";
import CourseTrendsTab from "./tabs/CourseTrendsTab";
import CourseGapsTab from "./tabs/CourseGapsTab";
import CourseAITab from "./tabs/CourseAITab";

interface Props {
  course: any;
  onClose: () => void;
}

export default function CourseDetailDrawer({ course, onClose }: Props) {
  const [activeTab, setActiveTab] = useState<
    "empleo" | "tendencias" | "gaps" | "ia"
  >("empleo");

  /* =========================
     Reset tab when course changes
  ========================= */
  useEffect(() => {
    setActiveTab("empleo");
  }, [course?.id]);

  /* =========================
     Bloquear scroll fondo
  ========================= */
  useEffect(() => {
    document.body.style.overflow = "hidden";
    return () => {
      document.body.style.overflow = "auto";
    };
  }, []);

  /* =========================
     Cerrar con ESC
  ========================= */
  useEffect(() => {
    const handleEsc = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };

    window.addEventListener("keydown", handleEsc);
    return () => window.removeEventListener("keydown", handleEsc);
  }, [onClose]);

  if (!course) return null;

  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      
      {/* ================= OVERLAY ================= */}
      <div
        onClick={onClose}
        className="absolute inset-0 bg-black/40 backdrop-blur-sm transition-opacity"
      />

      {/* ================= PANEL ================= */}
      <div className="relative w-[560px] h-full bg-white shadow-2xl flex flex-col animate-slide-in">

        {/* ================= HEADER ================= */}
        <div className="bg-[#0A2540] text-white p-6 flex justify-between items-start">
          <div>
            <h2 className="text-lg font-semibold leading-tight">
              {course.name}
            </h2>

            <div className="text-xs opacity-70 mt-1">
              Estado estratégico: {course.estado}
            </div>
          </div>

          <button
            onClick={onClose}
            className="hover:opacity-70 transition"
          >
            <X size={20} />
          </button>
        </div>

        {/* ================= TABS ================= */}
        <div className="flex border-b text-sm bg-muted/30">
          <TabButton
            icon={<Briefcase size={16} />}
            label="Empleo"
            active={activeTab === "empleo"}
            onClick={() => setActiveTab("empleo")}
          />

          <TabButton
            icon={<Sparkles size={16} />}
            label="Tendencias"
            active={activeTab === "tendencias"}
            onClick={() => setActiveTab("tendencias")}
          />

          <TabButton
            icon={<AlertTriangle size={16} />}
            label="Gaps"
            active={activeTab === "gaps"}
            onClick={() => setActiveTab("gaps")}
          />

          <TabButton
            icon={<Bot size={16} />}
            label="IA"
            active={activeTab === "ia"}
            onClick={() => setActiveTab("ia")}
          />
        </div>

        {/* ================= CONTENT ================= */}
        <div className="flex-1 overflow-y-auto p-6 text-sm">

          {activeTab === "empleo" && (
            <CourseEmploymentTab course={course} />
          )}

          {activeTab === "tendencias" && (
            <CourseTrendsTab course={course} />
          )}

          {activeTab === "gaps" && (
            <CourseGapsTab course={course} />
          )}

          {activeTab === "ia" && (
            <CourseAITab course={course} />
          )}

        </div>
      </div>
    </div>
  );
}

/* =========================
   TAB BUTTON
========================= */

function TabButton({
  icon,
  label,
  active,
  onClick,
}: {
  icon: React.ReactNode;
  label: string;
  active: boolean;
  onClick: () => void;
}) {
  return (
    <button
      onClick={onClick}
      className={`flex-1 flex items-center justify-center gap-2 py-3 transition-all
        ${
          active
            ? "border-b-2 border-[#1CBCE8] text-[#1CBCE8] font-medium bg-white"
            : "text-muted-foreground hover:bg-muted/40"
        }`}
    >
      {icon}
      {label}
    </button>
  );
}
