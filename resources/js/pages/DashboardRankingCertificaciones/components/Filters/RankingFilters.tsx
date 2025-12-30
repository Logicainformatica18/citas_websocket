import FilterSelect from "./FilterSelect";

export default function RankingFilters() {
  return (
    <div className="flex gap-4">
      <FilterSelect label="Área tecnológica" />
      <FilterSelect label="Carrera ISIL" />
    </div>
  );
}
