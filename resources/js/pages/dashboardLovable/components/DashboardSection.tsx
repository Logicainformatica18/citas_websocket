interface Props {
  title?: string;
  children: React.ReactNode;
}

export default function DashboardSection({ title, children }: Props) {
  return (
    <section className="space-y-4">
      {title && (
        <h2 className="text-lg font-semibold text-[#1CBCE8]">
          {title}
        </h2>
      )}

      <div className="grid grid-cols-12 gap-4">
        {children}
      </div>
    </section>
  );
}
