import { Form, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';

import RowActions from '@/components/shared/RowActions';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { notify } from '@/lib/notify';
import { PageHeader, PaginationLinks, withAppLayout } from '@/lib/page-kit';
import { route } from '@/lib/route';

type Named = { id: string; name?: string | null; asset_code?: string | null; quantity_remaining?: number; unit?: string | null };
type DispatchItem = {
    id: string;
    item_type: string;
    quantity?: number | null;
    asset?: Named | null;
    stock_item?: Named | null;
};
type DispatchRecord = {
    id: string;
    dispatch_number: string;
    title: string;
    destination: string;
    purpose: string;
    status: string;
    dispatch_date?: string | null;
    expected_return_date?: string | null;
    notes?: string | null;
    items?: DispatchItem[];
    responsible_users?: Named[];
};
type Paginator<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};
type PageProps = {
    dispatch: DispatchRecord;
    statusOptions?: Record<string, string>;
    picker?: string;
    pickerAssets?: Paginator<Named> | null;
    pickerStockItems?: Paginator<Named> | null;
    assetFilters?: { search?: string };
    stockFilters?: { stock_search?: string };
    auth?: { user?: { permissions?: string[] } | null };
};

function canManage(permissions: string[] | undefined): boolean {
    return Boolean(permissions?.includes('manage inventory dispatches'));
}

function DispatchShowPage() {
    const {
        dispatch,
        statusOptions = {},
        picker = 'assets',
        pickerAssets,
        pickerStockItems,
        assetFilters = {},
        stockFilters = {},
        auth,
    } = usePage<PageProps>().props;
    const manage = canManage(auth?.user?.permissions);
    const canEditItems = manage && dispatch.status === 'draft';
    const [selectedAssets, setSelectedAssets] = useState<string[]>([]);

    const statusLabel = statusOptions[dispatch.status] ?? dispatch.status;
    const items = dispatch.items ?? [];

    const workflowActions = useMemo(() => {
        if (!manage) {
            return [];
        }

        const actions: Array<{ label: string; onClick: () => void; variant?: 'default' | 'outline' }> = [];

        if (dispatch.status === 'draft') {
            actions.push({
                label: 'Mark ready',
                onClick: () =>
                    router.post(route('inventory.dispatches.ready', { dispatch: dispatch.id }), {}, {
                        onSuccess: () => notify('success', 'Dispatch marked ready.'),
                        onError: () => notify('error', 'Could not mark ready. Add items first.'),
                    }),
            });
            actions.push({
                label: 'Confirm dispatch',
                onClick: () =>
                    router.post(route('inventory.dispatches.confirm', { dispatch: dispatch.id }), {}, {
                        onSuccess: () => notify('success', 'Dispatch confirmed.'),
                        onError: () => notify('error', 'Could not confirm dispatch.'),
                    }),
            });
        }

        if (dispatch.status === 'ready') {
            actions.push({
                label: 'Back to draft',
                variant: 'outline',
                onClick: () => router.post(route('inventory.dispatches.reopen', { dispatch: dispatch.id })),
            });
            actions.push({
                label: 'Confirm dispatch',
                onClick: () => router.post(route('inventory.dispatches.confirm', { dispatch: dispatch.id })),
            });
        }

        if (dispatch.status === 'dispatched') {
            actions.push({
                label: 'Start return',
                onClick: () => router.post(route('inventory.dispatches.start-return', { dispatch: dispatch.id })),
            });
        }

        if (dispatch.status === 'returning') {
            actions.push({
                label: 'Record return',
                onClick: () => router.get(route('inventory.dispatches.return', { dispatch: dispatch.id })),
            });
        }

        if (dispatch.status === 'returned') {
            actions.push({
                label: 'Close dispatch',
                onClick: () => router.post(route('inventory.dispatches.close', { dispatch: dispatch.id })),
            });
        }

        return actions;
    }, [dispatch.id, dispatch.status, manage]);

    const applyAssetSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const search = String(form.get('search') ?? '').trim();
        router.get(
            route('inventory.dispatches.show', { dispatch: dispatch.id }),
            { picker: 'assets', ...(search ? { search } : {}) },
            { preserveState: true, replace: true },
        );
    };

    const applyStockSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const search = String(form.get('stock_search') ?? '').trim();
        router.get(
            route('inventory.dispatches.show', { dispatch: dispatch.id }),
            { picker: 'stock', ...(search ? { stock_search: search } : {}) },
            { preserveState: true, replace: true },
        );
    };

    const toggleAsset = (id: string) => {
        setSelectedAssets((current) =>
            current.includes(id) ? current.filter((item) => item !== id) : [...current, id],
        );
    };

    const addSelectedAssets = () => {
        if (selectedAssets.length === 0) {
            notify('error', 'Select at least one asset.');
            return;
        }

        router.post(
            route('inventory.dispatches.assets.add', { dispatch: dispatch.id }),
            { asset_ids: selectedAssets, picker: 'assets' },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedAssets([]);
                    notify('success', 'Assets added.');
                },
                onError: () => notify('error', 'Could not add assets.'),
            },
        );
    };

    return (
        <>
            <PageHeader
                title={dispatch.title}
                description={`${dispatch.dispatch_number} · ${statusLabel} · ${dispatch.destination}`}
                actions={
                    <>
                        <Button variant="outline" render={<Link href={route('inventory.dispatches.index')} />}>
                            Back to list
                        </Button>
                        {manage && (dispatch.status === 'draft' || dispatch.status === 'ready') && (
                            <Button
                                variant="outline"
                                render={<Link href={route('inventory.dispatches.edit', { dispatch: dispatch.id })} />}
                            >
                                Edit details
                            </Button>
                        )}
                        {workflowActions.map((action) => (
                            <Button key={action.label} variant={action.variant ?? 'default'} type="button" onClick={action.onClick}>
                                {action.label}
                            </Button>
                        ))}
                    </>
                }
            />

            <div className="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                        <CardTitle className="text-base">Overview</CardTitle>
                        <span className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                            {statusLabel}
                        </span>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        <div>
                            <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                Dispatch #
                            </p>
                            <p className="mt-1">{dispatch.dispatch_number}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                Destination
                            </p>
                            <p className="mt-1">{dispatch.destination || '—'}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Purpose</p>
                            <p className="mt-1 break-words">{dispatch.purpose || '—'}</p>
                        </div>
                        {dispatch.notes ? (
                            <div>
                                <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                    Notes
                                </p>
                                <p className="mt-1 break-words">{dispatch.notes}</p>
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Dates</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        <div>
                            <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                Dispatch date
                            </p>
                            <p className="mt-1">{dispatch.dispatch_date ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                Expected return
                            </p>
                            <p className="mt-1">{dispatch.expected_return_date ?? '—'}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Responsible staff</CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm">
                        {(dispatch.responsible_users ?? []).map((user) => user.name).filter(Boolean).join(', ') || '—'}
                    </CardContent>
                </Card>
            </div>

            <Card className="mb-6">
                <CardHeader>
                    <CardTitle className="text-base">Items on this dispatch ({items.length})</CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                    <table className="w-full text-xs">
                        <thead>
                            <tr className="border-b text-left">
                                <th className="text-muted-foreground px-3 py-2">No.</th>
                                <th className="text-muted-foreground px-3 py-2">Type</th>
                                <th className="text-muted-foreground px-3 py-2">Item</th>
                                <th className="text-muted-foreground px-3 py-2">Qty</th>
                                {canEditItems && <th className="px-3 py-2 text-right">Actions</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {items.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="text-muted-foreground px-3 py-8 text-center">
                                        No items yet. {canEditItems ? 'Add assets or stock below.' : ''}
                                    </td>
                                </tr>
                            ) : (
                                items.map((item, index) => (
                                    <tr className="border-b last:border-0" key={item.id}>
                                        <td className="px-3 py-2 tabular-nums">{index + 1}</td>
                                        <td className="px-3 py-2 capitalize">{item.item_type}</td>
                                        <td className="px-3 py-2">
                                            {item.asset
                                                ? `${item.asset.asset_code ?? ''} ${item.asset.name ?? ''}`.trim()
                                                : item.stock_item?.name ?? '—'}
                                        </td>
                                        <td className="px-3 py-2">{item.quantity ?? 1}</td>
                                        {canEditItems && (
                                            <td className="px-3 py-2 text-right">
                                                <RowActions
                                                    items={[
                                                        {
                                                            label: 'Remove',
                                                            destructive: true,
                                                            onClick: () => {
                                                                if (!window.confirm('Remove this item from the dispatch?')) {
                                                                    return;
                                                                }
                                                                router.delete(
                                                                    route('inventory.dispatches.items.destroy', {
                                                                        dispatch: dispatch.id,
                                                                        item: item.id,
                                                                    }),
                                                                    {
                                                                        preserveScroll: true,
                                                                        onSuccess: () => notify('success', 'Item removed.'),
                                                                    },
                                                                );
                                                            },
                                                        },
                                                    ]}
                                                />
                                            </td>
                                        )}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            {canEditItems && (
                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
                        <CardTitle className="text-base">Add items</CardTitle>
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                variant={picker === 'assets' ? 'default' : 'outline'}
                                render={
                                    <Link
                                        href={route('inventory.dispatches.show', {
                                            dispatch: dispatch.id,
                                            picker: 'assets',
                                        })}
                                        preserveState
                                    />
                                }
                            >
                                Assets
                            </Button>
                            <Button
                                size="sm"
                                variant={picker === 'stock' ? 'default' : 'outline'}
                                render={
                                    <Link
                                        href={route('inventory.dispatches.show', {
                                            dispatch: dispatch.id,
                                            picker: 'stock',
                                        })}
                                        preserveState
                                    />
                                }
                            >
                                Stock
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {picker === 'assets' ? (
                            <>
                                <form onSubmit={applyAssetSearch} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                                    <div className="min-w-[12rem] flex-1 space-y-1">
                                        <Label htmlFor="dispatch-asset-search">Search assets</Label>
                                        <Input
                                            id="dispatch-asset-search"
                                            name="search"
                                            defaultValue={assetFilters.search ?? ''}
                                            placeholder="Name, code, or serial…"
                                        />
                                    </div>
                                    <Button type="submit" variant="outline">
                                        Search
                                    </Button>
                                    <Button type="button" onClick={addSelectedAssets} disabled={selectedAssets.length === 0}>
                                        Add selected ({selectedAssets.length})
                                    </Button>
                                </form>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-xs">
                                        <thead>
                                            <tr className="border-b text-left">
                                                <th className="w-10 px-2 py-1.5" />
                                                <th className="text-muted-foreground px-2 py-1.5">Code</th>
                                                <th className="text-muted-foreground px-2 py-1.5">Name</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {(pickerAssets?.data ?? []).map((asset) => (
                                                <tr className="border-b last:border-0" key={asset.id}>
                                                    <td className="px-2 py-1.5">
                                                        <input
                                                            type="checkbox"
                                                            checked={selectedAssets.includes(asset.id)}
                                                            onChange={() => toggleAsset(asset.id)}
                                                        />
                                                    </td>
                                                    <td className="px-2 py-1.5">{asset.asset_code ?? '—'}</td>
                                                    <td className="px-2 py-1.5">{asset.name ?? '—'}</td>
                                                </tr>
                                            ))}
                                            {(pickerAssets?.data ?? []).length === 0 && (
                                                <tr>
                                                    <td colSpan={3} className="text-muted-foreground px-2 py-6 text-center">
                                                        No matching assets available to add.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                                {pickerAssets?.links && pickerAssets.links.length > 3 && (
                                    <PaginationLinks links={pickerAssets.links} />
                                )}
                            </>
                        ) : (
                            <>
                                <form onSubmit={applyStockSearch} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                                    <div className="min-w-[12rem] flex-1 space-y-1">
                                        <Label htmlFor="dispatch-stock-search">Search stock</Label>
                                        <Input
                                            id="dispatch-stock-search"
                                            name="stock_search"
                                            defaultValue={stockFilters.stock_search ?? ''}
                                            placeholder="Stock item name…"
                                        />
                                    </div>
                                    <Button type="submit" variant="outline">
                                        Search
                                    </Button>
                                </form>
                                <div className="space-y-3">
                                    {(pickerStockItems?.data ?? []).map((stock) => (
                                        <Form
                                            key={stock.id}
                                            action={route('inventory.dispatches.stock.add', { dispatch: dispatch.id })}
                                            method="post"
                                            className="flex flex-wrap items-end gap-3 border-b pb-3 last:border-0"
                                            onSuccess={() => notify('success', 'Stock added.')}
                                            onError={() => notify('error', 'Could not add stock.')}
                                        >
                                            {({ processing }) => (
                                                <>
                                                    <input type="hidden" name="stock_item_id" value={stock.id} />
                                                    <input type="hidden" name="picker" value="stock" />
                                                    <div className="min-w-[10rem] flex-1">
                                                        <p className="text-sm font-medium">{stock.name}</p>
                                                        <p className="text-muted-foreground text-xs">
                                                            {stock.quantity_remaining ?? 0} {stock.unit ?? ''} available
                                                        </p>
                                                    </div>
                                                    <div className="w-28 space-y-1">
                                                        <Label htmlFor={`qty-${stock.id}`}>Quantity</Label>
                                                        <Input
                                                            id={`qty-${stock.id}`}
                                                            name="quantity"
                                                            type="number"
                                                            min={1}
                                                            defaultValue={1}
                                                            required
                                                        />
                                                    </div>
                                                    <Button type="submit" disabled={processing} loading={processing}>
                                                        Add
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    ))}
                                    {(pickerStockItems?.data ?? []).length === 0 && (
                                        <p className="text-muted-foreground py-6 text-center text-sm">
                                            No matching stock items available.
                                        </p>
                                    )}
                                </div>
                                {pickerStockItems?.links && pickerStockItems.links.length > 3 && (
                                    <PaginationLinks links={pickerStockItems.links} />
                                )}
                            </>
                        )}
                    </CardContent>
                </Card>
            )}
        </>
    );
}

DispatchShowPage.layout = withAppLayout('Dispatch');

export default DispatchShowPage;
