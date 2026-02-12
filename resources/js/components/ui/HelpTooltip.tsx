import * as Tooltip from "@radix-ui/react-tooltip";
import { ReactNode } from "react";

interface Props {
  text: string;
  children: ReactNode;
}

export default function HelpTooltip({ text, children }: Props) {
  return (
    <Tooltip.Provider delayDuration={200}>
      <Tooltip.Root>
        <Tooltip.Trigger asChild>
          {children}
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
