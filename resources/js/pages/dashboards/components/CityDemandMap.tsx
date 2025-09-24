import { Card, CardContent } from "@/components/ui/card";

export default function CityDemandMap() {
  return (
    <Card className="bg-gray-800 text-white h-[300px] flex flex-col">
      <CardContent className="p-4 flex-1 flex flex-col">
        <h2 className="text-lg font-bold mb-2">Demanda potencial por ciudad</h2>

        {/* Contenedor de mapa ficticio */}
        <div className="flex-1 bg-black rounded-lg relative overflow-hidden">
          {/* Imagen estática como mockup */}
          <img
            src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/80/World_map_-_low_resolution.svg/1200px-World_map_-_low_resolution.svg.png"
            alt="Mapa de demanda"
            className="w-full h-full object-cover opacity-60"
          />

          {/* Leyenda abajo */}
          <div className="absolute bottom-2 left-2 text-xs bg-gray-900/70 px-2 py-1 rounded">
            <span className="text-orange-400 font-bold">High demand</span> →
            <span className="text-gray-300 ml-1">Low demand</span>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
