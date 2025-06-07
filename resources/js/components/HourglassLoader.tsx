// components/HourglassLoader.tsx
import { Loader2 } from 'lucide-react';

export default function HourglassLoader() {
  return (
    <div className="flex justify-center items-center gap-2 text-sm text-gray-600">
      <Loader2 className="animate-spin h-5 w-5" />
      Cargando...
    </div>
  );
}
