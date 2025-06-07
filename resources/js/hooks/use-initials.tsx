import { useCallback } from 'react';

export function useInitials() {
  return useCallback((firstname?: string, lastname?: string): string => {
    const first = (firstname ?? '').trim().charAt(0);
    const last = (lastname ?? '').trim().charAt(0);

    if (!first && !last) return '?';

    return `${first}${last}`.toUpperCase();
  }, []);
}
