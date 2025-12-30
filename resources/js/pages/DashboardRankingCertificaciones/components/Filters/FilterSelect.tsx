export default function FilterSelect({ label }: { label: string }) {
  return (
    <select className="border rounded-lg px-3 py-2 text-sm">
      <option>{label}</option>
    </select>
  );
}
