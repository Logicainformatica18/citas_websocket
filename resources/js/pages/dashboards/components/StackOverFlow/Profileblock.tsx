import { Card, CardContent } from "@/components/ui/card";
import ProfileWorkModeCard from "./ProfileWorkModeCard";
import ProfileEducationCard from "./ProfileEducationCard";
import ProfileAgeCard from "./ProfileAgeCard";

export default function ProfileBlock() {
  return (
    <Card className="bg-[#111] border border-gray-800 text-white rounded-xl shadow-md p-4">
      <CardContent>
        <h2 className="text-lg font-semibold mb-4 flex items-center gap-2">
          <span className="text-purple-400 text-xl">👥</span> PERFIL PROFESIONAL
        </h2>

       <div className="grid grid-cols-3 gap-4">
  {/* Ocupa 1 columna */}
  <div className="col-span-1">
    <ProfileWorkModeCard />
  </div>

  {/* Ocupa 2 columnas */}
  <div className="col-span-2">
    <ProfileEducationCard />
  </div>
</div>

           <div className="grid grid-cols-3 md:grid-cols-2 gap-4">
        
          {/* <ProfileAgeCard /> */}
        </div>
      </CardContent>
    </Card>
  );
}
