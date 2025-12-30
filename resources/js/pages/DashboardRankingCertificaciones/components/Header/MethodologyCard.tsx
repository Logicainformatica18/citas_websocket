export default function MethodologyCard() {
  return (
    <div className="border rounded-xl p-4 text-sm bg-[#F5FCFE]">
      <p className="font-semibold mb-2">Metodología de Cálculo</p>
      <ul className="space-y-1">
        <li>70% Demanda laboral</li>
        <li>30% Tendencias tecnológicas</li>
      </ul>
      <p className="mt-2 text-xs text-gray-500">
        Score = (0.7 × Laboral) + (0.3 × Tendencias)
      </p>
    </div>
  );
}
