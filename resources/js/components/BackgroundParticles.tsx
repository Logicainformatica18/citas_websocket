import { useCallback } from "react";
import Particles from "react-tsparticles";
import { loadSlim } from "tsparticles-slim";

export default function BackgroundParticles() {
  const particlesInit = useCallback(async (engine: any) => {
    await loadSlim(engine);
  }, []);

  return (
    <Particles
      id="tsparticles"
      init={particlesInit}
      options={{
        fullScreen: { enable: false },
        background: { color: { value: "transparent" } },
        fpsLimit: 60,
        particles: {
          number: {
            value: 60, // 🔹 menos puntos, más limpio
            density: { enable: true, area: 800 },
          },
          color: { value: "#60a5fa" }, // azul claro
          links: {
            enable: true,
            distance: 150, // 🔹 distancia de conexión (más = más líneas visibles)
            color: "#60a5fa",
            opacity: 0.35, // 🔹 visibilidad de las líneas
            width: 1,
          },
          move: {
            enable: true,
            speed: 0.8,
            direction: "none",
            outModes: { default: "out" },
          },
          opacity: { value: 0.7 },
          shape: { type: "circle" },
          size: { value: { min: 1, max: 3 } },
        },
        detectRetina: true,
        interactivity: {
          detectsOn: "canvas",
          events: {
            onHover: { enable: true, mode: "repulse" },
            resize: true,
          },
          modes: {
            repulse: { distance: 100, duration: 0.4 },
          },
        },
      }}
      className="absolute inset-0 w-full h-full pointer-events-none z-0"
    />
  );
}
