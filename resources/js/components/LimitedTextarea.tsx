import { useState, useEffect } from 'react';
import { Textarea } from '@/components/ui/Textarea';

interface Props {
  name: string;
  label?: string;
  value: string;
  onChange: (e: React.ChangeEvent<HTMLTextAreaElement>) => void;
  maxLength?: number;
  textareaClassName?: string;
}

export default function LimitedTextarea({
  name,
  label,
  value,
  onChange,
  maxLength = 300,
  textareaClassName = '',
}: Props) {
  const [charCount, setCharCount] = useState(value.length);

  useEffect(() => {
    setCharCount(value.length);
  }, [value]);

  const handleChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
    if (e.target.value.length <= maxLength) {
      onChange(e);
    }
  };

  return (
    <div className="w-full">
      <Textarea
        name={name}
        value={value}
        onChange={handleChange}
        className={textareaClassName}
        rows={3}
      />
      <div className="text-xs text-gray-500 mt-1 text-right">
        {charCount}/{maxLength}
      </div>
    </div>
  );
}
