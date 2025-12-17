export default function WidgetInsight({ children }: { children: React.ReactNode }) {
  return (
    <div
      className="
        flex items-start gap-2
        bg-[#ECFAFD] dark:bg-gray-800
        text-[#0A4E61] dark:text-gray-300
        text-sm
        rounded-lg px-4 py-3
      "
    >
      <span>🤖</span>
      <span>{children}</span>
    </div>
  );
}
