export function useLovableChartTheme() {
  const isDark = document.documentElement.classList.contains("dark");

  return isDark
    ? {
        cardBg: "#0f172a",
        text: "#e5e7eb",
        bar: "#22B8E8",
        grid: "#1e3a5f",
        border: "#1e293b",
        tooltipBg: "#020617",
      }
    : {
        cardBg: "#ffffff",
     text: "#0A4E61", // NO negro puro

        bar: "#22B8E8",
        grid: "#E6F7FD",
        border: "#CDEFFC",
        tooltipBg: "#ffffff",
      };
}
