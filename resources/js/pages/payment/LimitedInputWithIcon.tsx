import LimitedInput from "@/components/LimitedInput";

type Props = React.ComponentProps<typeof LimitedInput> & { Icon: any };

export default function LimitedInputWithIcon({ Icon, inputClassName = "", ...props }: Props) {
  return (
    <div className="relative">
      <Icon className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
      <LimitedInput {...props} inputClassName={`pl-9 pr-12 ${inputClassName}`} />
    </div>
  );
}
