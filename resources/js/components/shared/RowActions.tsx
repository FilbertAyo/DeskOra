import { MoreHorizontalIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export type RowActionItem = {
    label: string;
    href?: string;
    onClick?: () => void;
    icon?: ReactNode;
    destructive?: boolean;
    disabled?: boolean;
    hidden?: boolean;
};

type Props = {
    items: RowActionItem[];
    align?: 'start' | 'end';
    label?: string;
    className?: string;
};

export default function RowActions({ items, align = 'end', label = 'Actions', className }: Props) {
    const visible = items.filter((item) => !item.hidden);

    if (visible.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        className={cn('text-muted-foreground', className)}
                        aria-label={label}
                    />
                }
            >
                <MoreHorizontalIcon className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align={align} className="min-w-44">
                <DropdownMenuGroup>
                    {visible.map((item, index) => {
                        const previous = visible[index - 1];
                        const showSeparator = item.destructive && previous && !previous.destructive;

                        return (
                            <div key={`${item.label}-${index}`}>
                                {showSeparator ? <DropdownMenuSeparator /> : null}
                                <DropdownMenuItem
                                    variant={item.destructive ? 'destructive' : undefined}
                                    disabled={item.disabled}
                                    render={item.href ? <a href={item.href} /> : undefined}
                                    onClick={item.href ? undefined : item.onClick}
                                >
                                    {item.icon}
                                    <span>{item.label}</span>
                                </DropdownMenuItem>
                            </div>
                        );
                    })}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
