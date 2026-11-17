import { Link } from '@inertiajs/react';
import {
    AlertTriangleIcon,
    ArrowRightIcon,
    BoxesIcon,
    ClipboardCheckIcon,
    PackageIcon,
    TruckIcon,
    WrenchIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader, PageShell, withAppLayout } from '@/lib/page-kit';
import { route } from '@/lib/route';
import { cn } from '@/lib/utils';

type Stats = {
    asset_count: number;
    stock_count: number;
    low_stock_count: number;
    pending_borrow_count: number;
    overdue_borrow_count: number;
    under_repair_count: number;
    pending_disposal_count: number;
    open_dispatch_count: number;
    overdue_dispatch_count: number;
    open_verification_count: number;
};

type AssetHealthRow = {
    status: string;
    label: string;
    count: number;
};

type LowStockItem = {
    id: number;
    name: string;
    unit: string | null;
    quantity_remaining: number;
    minimum_level: number;
};

type RecentReceipt = {
    id: number;
    receipt_number: string;
    source_type: string;
    source_label: string;
    received_at: string | null;
    partner: string;
};

type AttentionItem = {
    key: string;
    label: string;
    count: number;
    href: string;
    tone: 'warning' | 'danger' | 'neutral';
    detail: string;
};

type Capabilities = {
    create_assets: boolean;
    create_receipts: boolean;
    manage_stock: boolean;
    view_my_items: boolean;
};

type QuickLink = {
    key: string;
    title: string;
    description: string;
    href: string;
    icon: ReactNode;
};

function formatCount(value: number): string {
    return value.toLocaleString();
}

function attentionTone(tone: AttentionItem['tone']): string {
    switch (tone) {
        case 'danger':
            return 'border-destructive/30 bg-destructive/5 text-destructive';
        case 'warning':
            return 'border-amber-500/30 bg-amber-500/5 text-amber-800 dark:text-amber-200';
        case 'neutral':
            return 'border-border bg-muted/30 text-foreground';
        default: {
            const _exhaustive: never = tone;
            return _exhaustive;
        }
    }
}

function MetricCard({
    title,
    value,
    detail,
    href,
}: {
    title: string;
    value: string;
    detail: ReactNode;
    href?: string;
}) {
    const body = (
        <Card className="h-full shadow-none">
            <CardContent className="pt-6">
                <p className="text-muted-foreground text-sm">{title}</p>
                <p className="mt-1 text-2xl font-semibold tracking-tight">{value}</p>
                <p className="text-muted-foreground mt-1 text-sm">{detail}</p>
            </CardContent>
        </Card>
    );

    if (!href) {
        return body;
    }

    return (
        <Link href={href} className="block transition-opacity hover:opacity-90">
            {body}
        </Link>
    );
}

function InventoryIndex({
    stats,
    assetHealth,
    lowStockItems,
    recentReceipts,
    can,
}: {
    stats: Stats;
    assetHealth: AssetHealthRow[];
    lowStockItems: LowStockItem[];
    recentReceipts: RecentReceipt[];
    can: Capabilities;
}) {
    const attentionTotal =
        stats.low_stock_count +
        stats.pending_borrow_count +
        stats.overdue_borrow_count +
        stats.under_repair_count +
        stats.pending_disposal_count +
        stats.open_dispatch_count +
        stats.overdue_dispatch_count +
        stats.open_verification_count;

    const attentionItems: AttentionItem[] = [
        {
            key: 'low-stock',
            label: 'Low stock items',
            count: stats.low_stock_count,
            href: route('inventory.stock-items.index'),
            tone: 'warning',
            detail: 'At or below minimum level',
        },
        {
            key: 'pending-borrow',
            label: 'Borrow requests pending',
            count: stats.pending_borrow_count,
            href: route('inventory.borrowing.index'),
            tone: 'neutral',
            detail: 'Awaiting review',
        },
        {
            key: 'overdue-borrow',
            label: 'Overdue borrows',
            count: stats.overdue_borrow_count,
            href: route('inventory.borrowing.index'),
            tone: 'danger',
            detail: 'Past expected return date',
        },
        {
            key: 'open-dispatch',
            label: 'Open dispatches',
            count: stats.open_dispatch_count,
            href: route('inventory.dispatches.index'),
            tone: 'neutral',
            detail: 'Active movements in the field',
        },
        {
            key: 'overdue-dispatch',
            label: 'Overdue dispatches',
            count: stats.overdue_dispatch_count,
            href: route('inventory.dispatches.index'),
            tone: 'danger',
            detail: 'Return date has passed',
        },
        {
            key: 'under-repair',
            label: 'Assets under repair',
            count: stats.under_repair_count,
            href: route('inventory.maintenance.index'),
            tone: 'warning',
            detail: 'Open maintenance records',
        },
        {
            key: 'pending-disposal',
            label: 'Disposal requests',
            count: stats.pending_disposal_count,
            href: route('inventory.disposal.index'),
            tone: 'neutral',
            detail: 'Awaiting decision',
        },
        {
            key: 'open-verification',
            label: 'Open verifications',
            count: stats.open_verification_count,
            href: route('inventory.verification.index'),
            tone: 'neutral',
            detail: 'Physical checks in progress',
        },
    ].filter((item) => item.count > 0);

    const quickLinks: QuickLink[] = [
        {
            key: 'assets',
            title: 'Assets',
            description: 'Register, assign, and track fixed assets',
            href: route('inventory.assets.index'),
            icon: <PackageIcon className="size-4" />,
        },
        {
            key: 'stock',
            title: 'Stock',
            description: 'Consumables, levels, and adjustments',
            href: route('inventory.stock-items.index'),
            icon: <BoxesIcon className="size-4" />,
        },
        {
            key: 'receipts',
            title: 'Receipts',
            description: 'Record incoming purchases and donations',
            href: route('inventory.receipts.index'),
            icon: <ClipboardCheckIcon className="size-4" />,
        },
        {
            key: 'borrowing',
            title: 'Borrowing',
            description: 'Requests, issues, and returns',
            href: route('inventory.borrowing.index'),
            icon: <ArrowRightIcon className="size-4" />,
        },
        {
            key: 'dispatches',
            title: 'Dispatch',
            description: 'Field movements and return cycles',
            href: route('inventory.dispatches.index'),
            icon: <TruckIcon className="size-4" />,
        },
        {
            key: 'maintenance',
            title: 'Maintenance',
            description: 'Repairs and condition follow-up',
            href: route('inventory.maintenance.index'),
            icon: <WrenchIcon className="size-4" />,
        },
        {
            key: 'reports',
            title: 'Reports',
            description: 'Exports and assignment notifications',
            href: route('inventory.reports.index'),
            icon: <ClipboardCheckIcon className="size-4" />,
        },
        ...(can.view_my_items
            ? [
                {
                    key: 'my-items',
                    title: 'My items',
                    description: 'Assets and stock in your custody',
                    href: route('inventory.my-items.index'),
                    icon: <PackageIcon className="size-4" />,
                } satisfies QuickLink,
            ]
            : []),
    ];

    const healthyAssetCount = assetHealth
        .filter((row) => row.status === 'available' || row.status === 'assigned')
        .reduce((sum, row) => sum + row.count, 0);

    const attentionAssetCount = assetHealth
        .filter((row) => ['under_repair', 'missing', 'borrowed', 'dispatched'].includes(row.status))
        .reduce((sum, row) => sum + row.count, 0);

    return (
        <PageShell>
            <PageHeader
                title="Inventory"
                description="Overview of assets, stock health, and operational attention across inventory modules."
                actions={
                    <>
                        {can.create_receipts && (
                            <Button variant="outline" render={<Link href={route('inventory.receipts.create')} />}>
                                New receipt
                            </Button>
                        )}
                        {can.create_assets && (
                            <Button render={<Link href={route('inventory.assets.create')} />}>New asset</Button>
                        )}
                        {!can.create_assets && !can.create_receipts && (
                            <Button variant="outline" render={<Link href={route('inventory.reports.index')} />}>
                                Reports
                            </Button>
                        )}
                    </>
                }
            />

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                    title="Assets"
                    value={formatCount(stats.asset_count)}
                    detail={`${formatCount(healthyAssetCount)} available/assigned`}
                    href={route('inventory.assets.index')}
                />
                <MetricCard
                    title="Stock items"
                    value={formatCount(stats.stock_count)}
                    detail={
                        stats.low_stock_count > 0
                            ? `${formatCount(stats.low_stock_count)} below minimum`
                            : 'All above minimum levels'
                    }
                    href={route('inventory.stock-items.index')}
                />
                <MetricCard
                    title="Needs attention"
                    value={formatCount(attentionTotal)}
                    detail="Borrows, dispatches, repairs, and checks"
                />
                <MetricCard
                    title="Open movements"
                    value={formatCount(stats.open_dispatch_count + stats.overdue_borrow_count)}
                    detail={
                        stats.overdue_dispatch_count + stats.overdue_borrow_count > 0
                            ? `${formatCount(stats.overdue_dispatch_count + stats.overdue_borrow_count)} overdue`
                            : 'No overdue movements'
                    }
                    href={route('inventory.dispatches.index')}
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-[1.35fr_1fr]">
                <Card className="shadow-none">
                    <CardHeader className="border-b bg-muted/20">
                        <CardTitle>Attention</CardTitle>
                        <CardDescription>
                            {attentionItems.length === 0
                                ? 'Nothing needs follow-up right now.'
                                : 'Items that usually need a decision or follow-up.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-6">
                        {attentionItems.length === 0 ? (
                            <div className="text-muted-foreground flex items-start gap-3 rounded-lg border border-dashed p-4 text-sm">
                                <AlertTriangleIcon className="mt-0.5 size-4 shrink-0 opacity-60" />
                                <p>No low stock, overdue returns, open repairs, or pending reviews.</p>
                            </div>
                        ) : (
                            <ul className="space-y-3">
                                {attentionItems.map((item) => (
                                    <li key={item.key}>
                                        <Link
                                            href={item.href}
                                            className={cn(
                                                'flex items-center justify-between gap-3 rounded-lg border px-3 py-2.5 transition-colors hover:bg-muted/40',
                                                attentionTone(item.tone),
                                            )}
                                        >
                                            <div className="min-w-0">
                                                <p className="font-medium">{item.label}</p>
                                                <p className="text-muted-foreground text-xs">{item.detail}</p>
                                            </div>
                                            <Badge variant="outline" className="bg-background/80">
                                                {formatCount(item.count)}
                                            </Badge>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card className="shadow-none">
                    <CardHeader className="border-b bg-muted/20">
                        <CardTitle>Asset health</CardTitle>
                        <CardDescription>
                            {formatCount(attentionAssetCount)} currently borrowed, dispatched, repairing, or missing
                        </CardDescription>
                        <CardAction>
                            <Button size="sm" variant="outline" render={<Link href={route('inventory.assets.index')} />}>
                                View assets
                            </Button>
                        </CardAction>
                    </CardHeader>
                    <CardContent className="pt-6">
                        {stats.asset_count === 0 ? (
                            <p className="text-muted-foreground text-sm">No assets registered yet.</p>
                        ) : (
                            <ul className="space-y-2">
                                {assetHealth
                                    .filter((row) => row.count > 0)
                                    .map((row) => (
                                        <li
                                            key={row.status}
                                            className="flex items-center justify-between gap-3 border-b py-2 last:border-0"
                                        >
                                            <span className="text-sm">{row.label}</span>
                                            <span className="font-medium tabular-nums">{formatCount(row.count)}</span>
                                        </li>
                                    ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>

            <div className="grid gap-6 lg:grid-cols-[1.2fr_1fr]">
                <Card className="shadow-none">
                    <CardHeader className="border-b bg-muted/20">
                        <CardTitle>Low stock</CardTitle>
                        <CardDescription>Active items at or below their minimum level.</CardDescription>
                        <CardAction>
                            <Button size="sm" variant="outline" render={<Link href={route('inventory.stock-items.index')} />}>
                                Manage stock
                            </Button>
                        </CardAction>
                    </CardHeader>
                    <CardContent className="p-0">
                        {lowStockItems.length === 0 ? (
                            <p className="text-muted-foreground px-6 py-8 text-sm">No low-stock alerts.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left">
                                            <th className="text-muted-foreground px-6 py-2.5 text-xs font-medium">Item</th>
                                            <th className="text-muted-foreground px-3 py-2.5 text-xs font-medium">Remaining</th>
                                            <th className="text-muted-foreground px-6 py-2.5 text-right text-xs font-medium">Minimum</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {lowStockItems.map((item) => (
                                            <tr className="border-b last:border-0" key={item.id}>
                                                <td className="px-6 py-2.5">
                                                    {can.manage_stock ? (
                                                        <Link
                                                            href={route('inventory.stock-items.edit', { stockItem: item.id })}
                                                            className="font-medium hover:underline"
                                                        >
                                                            {item.name}
                                                        </Link>
                                                    ) : (
                                                        <span className="font-medium">{item.name}</span>
                                                    )}
                                                    {item.unit ? (
                                                        <span className="text-muted-foreground ml-1 text-xs">({item.unit})</span>
                                                    ) : null}
                                                </td>
                                                <td className="px-3 py-2.5 tabular-nums">{formatCount(item.quantity_remaining)}</td>
                                                <td className="px-6 py-2.5 text-right tabular-nums">{formatCount(item.minimum_level)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="shadow-none">
                    <CardHeader className="border-b bg-muted/20">
                        <CardTitle>Recent receipts</CardTitle>
                        <CardDescription>Latest incoming inventory records.</CardDescription>
                        <CardAction>
                            <Button size="sm" variant="outline" render={<Link href={route('inventory.receipts.index')} />}>
                                All receipts
                            </Button>
                        </CardAction>
                    </CardHeader>
                    <CardContent className="pt-6">
                        {recentReceipts.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No receipts recorded yet.</p>
                        ) : (
                            <ul className="space-y-3">
                                {recentReceipts.map((receipt) => (
                                    <li key={receipt.id} className="flex items-start justify-between gap-3 border-b pb-3 last:border-0 last:pb-0">
                                        <div className="min-w-0">
                                            <Link
                                                href={route('inventory.receipts.show', { receipt: receipt.id })}
                                                className="font-medium hover:underline"
                                            >
                                                {receipt.receipt_number}
                                            </Link>
                                            <p className="text-muted-foreground truncate text-xs">
                                                {receipt.source_label} · {receipt.partner}
                                            </p>
                                        </div>
                                        <span className="text-muted-foreground shrink-0 text-xs tabular-nums">
                                            {receipt.received_at ?? '—'}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card className="shadow-none">
                <CardHeader className="border-b bg-muted/20">
                    <CardTitle>Quick links</CardTitle>
                    <CardDescription>Jump into the main inventory modules.</CardDescription>
                </CardHeader>
                <CardContent className="pt-6">
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {quickLinks.map((link) => (
                            <Link
                                key={link.key}
                                href={link.href}
                                className="hover:bg-muted/40 flex items-start gap-3 rounded-lg border p-3 transition-colors"
                            >
                                <span className="bg-muted text-muted-foreground mt-0.5 rounded-md p-2">{link.icon}</span>
                                <span className="min-w-0">
                                    <span className="block font-medium">{link.title}</span>
                                    <span className="text-muted-foreground block text-xs">{link.description}</span>
                                </span>
                            </Link>
                        ))}
                    </div>
                </CardContent>
            </Card>
        </PageShell>
    );
}

InventoryIndex.layout = withAppLayout('Inventory');

export default InventoryIndex;
