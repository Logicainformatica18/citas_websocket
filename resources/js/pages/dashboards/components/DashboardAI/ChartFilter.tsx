import React from "react";

export default function ChartFilter({ labels, active, onToggle }) {
  if (!labels || labels.length === 0) return null;

  return (
    <div
      className="flex flex-wrap gap-2 p-2 mb-3"
      style={{
        background: "rgba(255,255,255,0.05)",
        border: "1px solid rgba(255,255,255,0.08)",
        borderRadius: "6px",
        maxHeight: "120px",
        overflowY: "auto"
      }}
    >
      {labels.map((label) => (
        <label
          key={label}
          className="flex items-center gap-1 text-xs cursor-pointer select-none"
          style={{ color: "#f1f5f9" }}
        >
          <input
            type="checkbox"
            checked={active.includes(label)}
            onChange={() => onToggle(label)}
            className="accent-blue-500"
          />
          {label}
        </label>
      ))}
    </div>
  );
}
