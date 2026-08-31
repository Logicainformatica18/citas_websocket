// resources/js/components/ui/textarea.tsx
import React from 'react';

export const Textarea = React.forwardRef<
  HTMLTextAreaElement,
  React.TextareaHTMLAttributes<HTMLTextAreaElement>
>(({ className = '', ...props }, ref) => (
  <textarea
    ref={ref}
    className={`border rounded px-3 py-2 text-sm ${className}`}
    {...props}
  />
));

Textarea.displayName = 'Textarea';

export default Textarea;
