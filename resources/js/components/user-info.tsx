import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { type User } from '@/types';

export function UserInfo({
  user,
  showEmail = false,
}: {
  user: User | null;
  showEmail?: boolean;
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

      <div className="grid flex-1 text-left text-sm leading-tight">
        <span className="truncate font-medium">{user.names}</span>
        {showEmail && (
          <span className="text-muted-foreground truncate text-xs">
            {user.email}
          </span>
        )}
      </div>
    </>
  );
}
