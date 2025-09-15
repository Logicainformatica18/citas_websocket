type LimitedInputProps = React.InputHTMLAttributes<HTMLInputElement> & {
  inputClassName?: string;
};

export default function LimitedInput({
  inputClassName = "",
  ...props
}: LimitedInputProps) {
  return (
    <input
      {...props}
      className={`w-full rounded-md border border-gray-300 py-2 pl-3 pr-3 focus:border-[#054E5C] focus:ring-[#054E5C] disabled:opacity-60 placeholder-gray-400 text-sm ${inputClassName}`}
    />
  );
}
