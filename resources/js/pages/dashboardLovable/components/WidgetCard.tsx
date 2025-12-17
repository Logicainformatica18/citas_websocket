interface Props {
  title?: string;
  subtitle?: string;
  insight?: string;
  span?: string;
  children: React.ReactNode;
}

export default function WidgetCard({
  title,
  subtitle,
  insight,
  span = "col-span-12",
  children,
}: Props) {
  return (
    <div
      className={`
        ${span}
        bg-white dark:bg-gray-900
        border border-[#A7E5F6] dark:border-gray-700
        rounded-xl p-5
        flex flex-col gap-4
      `}
    >
      {/* Header */}
      {(title || subtitle) && (
        <div>
          {title && (
            <h3 className="text-base font-semibold text-gray-900 dark:text-white">
              {title}
            </h3>
          )}
          {subtitle && (
            <p className="text-sm text-gray-500 dark:text-gray-400">
              {subtitle}
            </p>
          )}
        </div>
      )}

      {/* Contenido */}
      <div className="flex-1">
        {children}
      </div>

      {/* Insight VERA */}
      {insight && (
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
          <span>{insight}</span>
        </div>
      )}
    </div>
  );
}
