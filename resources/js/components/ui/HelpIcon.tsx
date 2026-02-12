import * as Tooltip from "@radix-ui/react-tooltip";
import { Info } from "lucide-react";
import { useEffect, useState } from "react";

interface Props {
  text: string;
  pulseKey?: string;
}

const PULSE_DURATION = 30 * 60 * 1000; // 30 min

export default function HelpIcon({ text, pulseKey }: Props) {
  const [shouldPulse, setShouldPulse] = useState(false);

  useEffect(() => {
    if (!pulseKey || typeof window === "undefined") return;

    const storageKey = `helpPulse_${pulseKey}`;
    const now = Date.now();
    const storedTime = window.localStorage.getItem(storageKey);

    if (!storedTime) {
      window.localStorage.setItem(storageKey, now.toString());
      setShouldPulse(true);
      return;
    }

    const elapsed = now - parseInt(storedTime, 10);

    if (elapsed < PULSE_DURATION) {
      setShouldPulse(true);
    } else {
      setShouldPulse(false);
    }
  }, [pulseKey]);

  return (
    <Tooltip.Provider delayDuration={200}>
      <Tooltip.Root>
        <Tooltip.Trigger asChild>
          <span
            className={`
              inline-flex items-center justify-center
              w-5 h-5
              rounded-full
              bg-[#D5F3FB]
              text-[#0A4E61]
              hover:bg-[#1CBCE8]
              hover:text-white
              transition
              cursor-help
              opacity-80 hover:opacity-100
              ${shouldPulse ? "animate-bounce" : ""}
            `}
          >
            <Info size={12} />
          </span>
        </Tooltip.Trigger>

        <Tooltip.Content
          side="top"
          align="center"
          className="
            z-50
            max-w-xs
            bg-[#0A4E61]
            text-white
            text-xs
            px-3 py-2
            rounded-lg
            shadow-lg
          "
        >
          {text}
          <Tooltip.Arrow className="fill-[#0A4E61]" />
        </Tooltip.Content>
      </Tooltip.Root>
    </Tooltip.Provider>
  );
}
