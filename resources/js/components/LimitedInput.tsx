import { useState, useEffect } from 'react';
import { Input } from '@/components/ui/input';

interface Props {
  name: string;
  label?: string;
  value?: string; // ← Acepta string o undefined
  onChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
  maxLength?: number;
  inputClassName?: string;
}

export default function LimitedInput({
  name,
  label,
  value = '', // ← Valor por defecto si viene undefined
  onChange,
  maxLength = 100,
  inputClassName = '',
}: Props) {
  const [charCount, setCharCount] = useState(value.length);

  useEffect(() => {
    setCharCount(value.length);
  }, [value]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.value.length <= maxLength) {
      onChange(e);
    }
  };

  return (
    <div className="relative">
      <Input
        type="text"
        name={name}
        value={value}
        onChange={handleChange}
        className={`${inputClassName} pr-12`}
      />
      <span className="absolute right-2 top-1/2 transform -translate-y-1/2 text-xs text-gray-400 pointer-events-none">
        {charCount}/{maxLength}
      </span>
    </div>
  );
}
