// resources/js/components/ui/textarea.tsx
import React from 'react';

export const Textarea = React.forwardRef<
  HTMLTextAreaElement,
  React.TextareaHTMLAttributes<HTMLTextAreaElement>
>(({ className = '', ...props }, ref) => (
  <textarea
    ref={ref}
    className={`w-full min-w-0 resize-y rounded-xl border border-[#dfe4ea] bg-white px-3 py-3 text-sm leading-6 text-[#1f2328] shadow-sm placeholder:text-[#7a8190] outline-none transition focus:border-[#ff5a36] focus:ring-2 focus:ring-[#ff5a36]/20 ${className}`}
    {...props}
  />
));

Textarea.displayName = 'Textarea';

export default Textarea;
