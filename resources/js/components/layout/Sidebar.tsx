import { Link, usePage } from '@inertiajs/react';
import { ChevronRightIcon, type LucideIcon } from 'lucide-react';
import {
    CalendarDays,
    CreditCard,
    FileText,
    FolderKanban,
    Layers,
    LayoutDashboard,
    Mail,
    Package,
    Settings,
    TrendingUp,
    Users,
} from 'lucide-react';

import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { route } from '@/lib/route';
import { cn } from '@/lib/utils';
import type { NavItem, SharedProps } from '@/types/inertia';

const icons: Record<string, LucideIcon> = {
    CalendarDays,
    CreditCard,
    FileText,
    FolderKanban,
    Layers,
    LayoutDashboard,
    Mail,
    Package,
    Settings,
    TrendingUp,
    Users,
};

function NavIcon({ name }: { name: string | null }) {
    if (!name || !icons[name]) {
        return null;
    }

    const Icon = icons[name];

    return <Icon />;
}

function Leaf({ item, nested = false }: { item: NavItem; nested?: boolean }) {
    if (!item.href) {
        return null;
    }

    const className = 'data-active:bg-primary/10! justify-between';

    if (nested) {
        return (
            <SidebarMenuSubItem>
                <SidebarMenuSubButton className={className} render={<Link href={item.href} />} isActive={item.active}>
                    <span className={cn('min-w-0 flex-1 truncate', item.badge && 'pr-14')}>{item.label}</span>
                    {item.badge && (
                        <SidebarMenuBadge className="bg-primary/10 max-w-24 truncate rounded-full px-1.5 font-normal">
                            {item.badge}
                        </SidebarMenuBadge>
                    )}
                </SidebarMenuSubButton>
            </SidebarMenuSubItem>
        );
    }

    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                tooltip={item.label}
                className="data-active:bg-primary/10!"
                render={<Link href={item.href} />}
                isActive={item.active}
            >
                <NavIcon name={item.icon} />
                <span className={cn('min-w-0 flex-1 truncate', item.badge && 'pr-14')}>{item.label}</span>
                {item.badge && (
                    <SidebarMenuBadge className="bg-primary/10 max-w-24 truncate rounded-full px-1.5 font-normal">
                        {item.badge}
                    </SidebarMenuBadge>
                )}
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}

function Branch({ item, nested = false }: { item: NavItem; nested?: boolean }) {
    const children = item.children ?? [];

    const trigger = (
        <>
            {!nested && <NavIcon name={item.icon} />}
            <span className={cn('min-w-0 flex-1 truncate', item.badge && 'pr-14')}>{item.label}</span>
            {item.badge && (
                <SidebarMenuBadge className="bg-primary/10 right-8 max-w-24 truncate rounded-full px-1.5 font-normal">
                    {item.badge}
                </SidebarMenuBadge>
            )}
            <ChevronRightIcon className="ml-auto transition-transform duration-200 group-data-open/collapsible:rotate-90" />
        </>
    );

    return (
        <Collapsible className="group/collapsible" defaultOpen={item.active}>
            {nested ? (
                <SidebarMenuSubItem>
                    <CollapsibleTrigger
                        nativeButton={false}
                        render={
                            <SidebarMenuSubButton className="data-active:bg-primary/10! justify-between" isActive={item.active} />
                        }
                    >
                        {trigger}
                    </CollapsibleTrigger>
                    <CollapsibleContent className="overflow-hidden">
                        <SidebarMenuSub className="mx-0">
                            {children.map((child) =>
                                child.children?.length ? (
                                    <Branch key={child.label} item={child} nested />
                                ) : (
                                    <Leaf key={child.label} item={child} nested />
                                ),
                            )}
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuSubItem>
            ) : (
                <SidebarMenuItem>
                    <CollapsibleTrigger
                        render={<SidebarMenuButton tooltip={item.label} isActive={item.active} className="data-active:bg-primary/5!" />}
                    >
                        {trigger}
                    </CollapsibleTrigger>
                    <CollapsibleContent className="overflow-hidden">
                        <SidebarMenuSub>
                            {children.map((child) =>
                                child.children?.length ? (
                                    <Branch key={child.label} item={child} nested />
                                ) : (
                                    <Leaf key={child.label} item={child} nested />
                                ),
                            )}
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuItem>
            )}
        </Collapsible>
    );
}

export default function AppSidebar() {
    const { branding, nav } = usePage<SharedProps>().props;

    return (
        <Sidebar collapsible="icon">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" className="gap-2.5" render={<Link href={route('dashboard')} />}>
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-white p-1 shadow-sm ring-1 ring-black/10">
                                <img src={branding.logoIcon} alt="" className="size-5 object-contain" />
                            </span>
                            <div className="flex flex-col items-start">
                                <span className="text-sm leading-tight font-semibold">{branding.appName}</span>
                                <span className="text-muted-foreground text-xs font-normal">{branding.productName}</span>
                            </div>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                {nav.groups.map((group, index) => (
                    <SidebarGroup key={group.label ?? index}>
                        {group.label && (
                            <SidebarGroupLabel className="text-sidebar-foreground/50">
                                {group.label}
                            </SidebarGroupLabel>
                        )}
                        <SidebarGroupContent>
                            <SidebarMenu>
                                {group.items.map((item) =>
                                    item.children?.length ? (
                                        <Branch key={item.label} item={item} />
                                    ) : (
                                        <Leaf key={item.label} item={item} />
                                    ),
                                )}
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                ))}
            </SidebarContent>
        </Sidebar>
    );
}
