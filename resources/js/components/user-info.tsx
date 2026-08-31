import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { type User } from '@/types';

export function UserInfo({
  user,
  showEmail = false,
  variant = 'sidebar',
}: {
  user: User | null;
  showEmail?: boolean;
  variant?: 'sidebar' | 'dropdown';
}) {
  const getInitials = useInitials();

  // ✅ Guard clause
  if (!user) {
    return (
      <>
        <Avatar className="h-8 w-8">
          <AvatarFallback className="rounded-lg bg-neutral-200 dark:bg-neutral-700" />
        </Avatar>
        <div className="grid flex-1 text-left text-sm leading-tight">
          <span className="h-4 w-24 bg-neutral-200 dark:bg-neutral-700 rounded animate-pulse" />
        </div>
      </>
    );
  }

  return (
    <>
      <Avatar className="h-8 w-8 overflow-hidden rounded-full">
        <AvatarImage
          src={user.photo ?? '/img/avatar-default.png'}
          alt={`${user.firstname} ${user.lastname}`}
        />
        <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
          {getInitials(user.firstname, user.lastname)}
        </AvatarFallback>
      </Avatar>

      <div
        className={`grid flex-1 text-left text-sm leading-tight ${
          variant === 'sidebar'
            ? 'text-[#f4f8fb]'
            : 'text-slate-900 dark:text-slate-100'
        }`}
      >
        <span
          className={`truncate font-semibold ${
            variant === 'sidebar'
              ? 'text-[#f4f8fb]'
              : 'text-slate-900 dark:text-slate-100'
          }`}
        >
          {user.names}
        </span>
        {showEmail && (
          <span
            className={`truncate text-xs ${
              variant === 'sidebar'
                ? 'text-[#a9c4d2]'
                : 'text-slate-500 dark:text-slate-400'
            }`}
          >
            {user.email}
          </span>
        )}
      </div>
    </>
  );
}
