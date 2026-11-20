import { Form, Link, router, usePage } from '@inertiajs/react';
import { EyeIcon, PencilIcon, PlusIcon, WrenchIcon, Trash2Icon } from 'lucide-react';
import { useMemo, useState, type FormEvent, type ReactNode } from 'react';

import DownloadButton from '@/components/shared/DownloadButton';
import RowActions from '@/components/shared/RowActions';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { notify } from '@/lib/notify';
import { PageHeader, PaginationLinks, withAppLayout } from '@/lib/page-kit';
import { route } from '@/lib/route';

type RecordValue = Record<string, unknown>;

type AuthProps = {
    user?: { permissions?: string[] } | null;
};

export type InventoryField = {
    name: string;
    label?: string;
    type?: 'text' | 'number' | 'date' | 'email' | 'textarea' | 'select' | 'asset' | 'stock' | 'user';
    required?: boolean;
    optionsFrom?: string;
    options?: Array<{ value: string; label: string }>;
    optionValue?: string;
    optionLabel?: string;
};

export type InventoryColumn = {
    key: string;
    label: string;
    type?: 'text' | 'date' | 'status' | 'relation';
};

export type InventoryPageConfig = {
    title: string;
    description?: string;
    collection?: string;
    record?: string;
    createRoute?: string;
    createPermission?: string;
    showRoute?: string;
    editRoute?: string;
    editPermission?: string;
    routeParam?: string;
    searchable?: boolean;
    numbered?: boolean;
    columns?: InventoryColumn[];
    exportRoutes?: Array<{ label: string; name: string; params?: RecordValue }>;
    form?: {
        route: string;
        method?: 'post' | 'put' | 'patch';
        param?: string;
        routeParam?: string;
        fields: InventoryField[];
        submitLabel?: string;
        useModal?: boolean;
        permission?: string;
        helpText?: string;
    };
    maintenanceActions?: boolean;
    disposalActions?: boolean;
};

function isRecord(value: unknown): value is RecordValue {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function humanize(value: string): string {
    return value
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function getByPath(row: RecordValue, key: string): unknown {
    if (key in row) {
        return row[key];
    }

    const camel = key.replace(/_([a-z])/g, (_, letter: string) => letter.toUpperCase());
    if (camel in row) {
        return row[camel];
    }

    return undefined;
}

function formatDate(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const raw = String(value);
    const date = new Date(raw);
    if (Number.isNaN(date.getTime())) {
        return raw;
    }

    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function displayValue(value: unknown, statusOptions?: RecordValue): ReactNode {
    if (value === null || value === undefined || value === '') {
        return '—';
    }
    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }
    if (typeof value === 'string' || typeof value === 'number') {
        const key = String(value);
        if (statusOptions && typeof statusOptions[key] === 'string') {
            return String(statusOptions[key]);
        }
        return key;
    }
    if (Array.isArray(value)) {
        return `${value.length} item${value.length === 1 ? '' : 's'}`;
    }
    if (isRecord(value)) {
        const preferred =
            value.name ??
            value.label ??
            value.title ??
            value.asset_code ??
            value.request_number ??
            value.record_number ??
            value.dispatch_number;
        return preferred ? String(preferred) : 'View details';
    }

    return String(value);
}

function rowsFrom(value: unknown): RecordValue[] {
    if (Array.isArray(value)) {
        return value.filter(isRecord);
    }
    if (isRecord(value) && Array.isArray(value.data)) {
        return value.data.filter(isRecord);
    }

    return [];
}

function paginatorMeta(value: unknown): {
    links: Array<{ url: string | null; label: string; active: boolean }>;
    currentPage: number;
    perPage: number;
} {
    if (!isRecord(value)) {
        return { links: [], currentPage: 1, perPage: rowsFrom(value).length || 20 };
    }

    const links = Array.isArray(value.links)
        ? value.links.filter(isRecord).map((link) => ({
              url: typeof link.url === 'string' ? link.url : null,
              label: String(link.label ?? ''),
              active: Boolean(link.active),
          }))
        : [];

    return {
        links,
        currentPage: Number(value.current_page ?? 1),
        perPage: Number(value.per_page ?? 20),
    };
}

function usePermissions(): { can: (permission?: string) => boolean; permissions: string[] } {
    const props = usePage<{ auth?: AuthProps }>().props;
    const permissions = props.auth?.user?.permissions ?? [];

    return {
        permissions,
        can: (permission?: string) => {
            if (!permission) {
                return true;
            }

            return permissions.includes(permission);
        },
    };
}

function optionList(props: RecordValue, field: InventoryField): Array<{ value: string; label: string }> {
    if (field.options && field.options.length > 0) {
        return field.options;
    }

    const sourceKey = field.optionsFrom;
    if (!sourceKey) {
        return [];
    }

    const source = props[sourceKey];

    if (Array.isArray(source)) {
        const valueKey = field.optionValue ?? 'id';
        const labelKey = field.optionLabel ?? 'name';

        return source
            .filter(isRecord)
            .map((item) => {
                const value = item[valueKey];
                const labelParts = [
                    item[labelKey],
                    item.asset_code,
                    item.quantity_remaining != null ? `(${item.quantity_remaining} left)` : null,
                ]
                    .filter((part) => part !== null && part !== undefined && part !== '')
                    .map(String);

                return {
                    value: value == null ? '' : String(value),
                    label: labelParts.join(' · ') || String(value ?? ''),
                };
            })
            .filter((item) => item.value !== '');
    }

    if (isRecord(source)) {
        return Object.entries(source).map(([value, label]) => ({
            value,
            label: String(label),
        }));
    }

    return [];
}

function FieldInput({
    field,
    props,
    defaultValue,
    idPrefix = '',
    error,
}: {
    field: InventoryField;
    props: RecordValue;
    defaultValue?: unknown;
    idPrefix?: string;
    error?: string;
}) {
    const id = `${idPrefix}${field.name}`;
    const label = field.label ?? humanize(field.name.replace(/\[.*?\]/g, ''));
    const stringDefault = defaultValue == null ? '' : String(defaultValue);

    if (field.type === 'textarea') {
        return (
            <div className={field.type === 'textarea' ? 'sm:col-span-2' : ''}>
                <Label htmlFor={id}>{label}</Label>
                <Textarea id={id} name={field.name} required={field.required} defaultValue={stringDefault} />
                {error && <p className="text-destructive mt-1 text-xs">{error}</p>}
            </div>
        );
    }

    if (field.type === 'select' || field.type === 'asset' || field.type === 'stock' || field.type === 'user') {
        const options = optionList(props, field);
        const optionsProp =
            field.type === 'asset'
                ? 'assets'
                : field.type === 'stock'
                  ? 'stockItems'
                  : field.type === 'user'
                    ? 'users'
                    : field.optionsFrom;
        const resolved = options.length > 0 ? options : optionList(props, { ...field, optionsFrom: optionsProp });

        return (
            <div>
                <Label htmlFor={id}>{label}</Label>
                <select
                    id={id}
                    name={field.name}
                    required={field.required}
                    defaultValue={stringDefault}
                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                >
                    <option value="">Select…</option>
                    {resolved.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
                {error && <p className="text-destructive mt-1 text-xs">{error}</p>}
            </div>
        );
    }

    return (
        <div>
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                name={field.name}
                type={field.type ?? 'text'}
                required={field.required}
                defaultValue={stringDefault}
            />
            {error && <p className="text-destructive mt-1 text-xs">{error}</p>}
        </div>
    );
}

function DataGrid({
    rows,
    columns,
    numbered,
    startNumber,
    showRoute,
    editRoute,
    editPermission,
    routeParam = 'id',
    statusOptions,
    can,
    maintenanceActions,
    disposalActions,
}: {
    rows: RecordValue[];
    columns: InventoryColumn[];
    numbered?: boolean;
    startNumber: number;
    showRoute?: string;
    editRoute?: string;
    editPermission?: string;
    routeParam?: string;
    statusOptions?: RecordValue;
    can: (permission?: string) => boolean;
    maintenanceActions?: boolean;
    disposalActions?: boolean;
}) {
    const showEdit = Boolean(editRoute && can(editPermission));
    const canCompleteMaintenance = can('manage inventory maintenance');
    const canRequestDisposal = can('manage inventory assets');
    const canReviewDisposal = can('approve inventory requests');
    const hasRowActions = Boolean(showRoute || showEdit || maintenanceActions || disposalActions);

    if (rows.length === 0) {
        return <p className="text-muted-foreground py-8 text-center text-sm">No records found.</p>;
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-xs">
                <thead>
                    <tr className="border-b text-left">
                        {numbered && <th className="text-muted-foreground w-12 px-2 py-1.5 text-xs font-medium">No.</th>}
                        {columns.map((column) => (
                            <th className="text-muted-foreground px-2 py-1.5 text-xs font-medium" key={column.key}>
                                {column.label}
                            </th>
                        ))}
                        {hasRowActions && (
                            <th className="px-3 py-2 text-right font-medium">Actions</th>
                        )}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => {
                        const rowStatus = String(row.status ?? getByPath(row, 'status') ?? '');
                        const isOpenMaintenance = rowStatus === 'reported';
                        const isPendingDisposal = rowStatus === 'requested';
                        const assetId = isRecord(getByPath(row, 'asset'))
                            ? String((getByPath(row, 'asset') as RecordValue).id ?? '')
                            : String(row.asset_id ?? '');

                        return (
                            <tr className="border-b last:border-0" key={String(row.id ?? index)}>
                                {numbered && (
                                    <td className="text-muted-foreground px-2 py-1.5 tabular-nums">
                                        {startNumber + index}
                                    </td>
                                )}
                                {columns.map((column) => {
                                    const value = getByPath(row, column.key);
                                    const rendered =
                                        column.type === 'date'
                                            ? formatDate(value)
                                            : column.type === 'status'
                                              ? displayValue(value, statusOptions)
                                              : displayValue(value, column.type === 'status' ? statusOptions : undefined);

                                    return (
                                        <td className="max-w-64 px-2 py-1.5" key={column.key}>
                                            {rendered}
                                        </td>
                                    );
                                })}
                                {hasRowActions && (
                                    <td className="whitespace-nowrap px-2 py-1.5 text-right">
                                        <RowActions
                                            items={[
                                                {
                                                    label: 'View',
                                                    href:
                                                        showRoute && row.id !== undefined
                                                            ? route(showRoute, { [routeParam]: row.id })
                                                            : undefined,
                                                    icon: <EyeIcon />,
                                                    hidden: !(showRoute && row.id !== undefined),
                                                },
                                                {
                                                    label: 'Edit',
                                                    href:
                                                        showEdit && row.id !== undefined
                                                            ? route(editRoute!, { [routeParam]: row.id })
                                                            : undefined,
                                                    icon: <PencilIcon />,
                                                    hidden: !(showEdit && row.id !== undefined),
                                                },
                                                {
                                                    label: 'Record repair',
                                                    href:
                                                        maintenanceActions && canCompleteMaintenance && isOpenMaintenance && row.id
                                                            ? route('inventory.maintenance.complete.form', {
                                                                  maintenance: row.id,
                                                              })
                                                            : undefined,
                                                    icon: <WrenchIcon />,
                                                    hidden: !(
                                                        maintenanceActions &&
                                                        canCompleteMaintenance &&
                                                        isOpenMaintenance &&
                                                        row.id
                                                    ),
                                                },
                                                {
                                                    label: 'Request disposal',
                                                    href:
                                                        maintenanceActions && canRequestDisposal && isOpenMaintenance && assetId
                                                            ? route('inventory.disposal.create', { asset_id: assetId })
                                                            : undefined,
                                                    icon: <Trash2Icon />,
                                                    hidden: !(
                                                        maintenanceActions &&
                                                        canRequestDisposal &&
                                                        isOpenMaintenance &&
                                                        assetId
                                                    ),
                                                },
                                                {
                                                    label: 'Review & approve',
                                                    href:
                                                        disposalActions &&
                                                        canReviewDisposal &&
                                                        isPendingDisposal &&
                                                        row.id !== undefined
                                                            ? route('inventory.disposal.show', { disposal: row.id })
                                                            : undefined,
                                                    icon: <EyeIcon />,
                                                    hidden: !(
                                                        disposalActions &&
                                                        canReviewDisposal &&
                                                        isPendingDisposal &&
                                                        row.id !== undefined
                                                    ),
                                                },
                                            ]}
                                        />
                                    </td>
                                )}
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

function DetailGrid({
    record,
    statusOptions,
    hideKeys = [],
}: {
    record: RecordValue;
    statusOptions?: RecordValue;
    hideKeys?: string[];
}) {
    const hidden = new Set([
        'pivot',
        'created_at',
        'updated_at',
        'deleted_at',
        'permissions',
        'roles',
        ...hideKeys,
    ]);

    return (
        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {Object.entries(record)
                .filter(([key]) => !hidden.has(key) && !key.endsWith('_id'))
                .map(([key, value]) => (
                    <div key={key}>
                        <dt className="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                            {humanize(key)}
                        </dt>
                        <dd className="mt-1 text-sm">
                            {key.includes('at') || key.includes('on') || key.includes('date')
                                ? typeof value === 'string' && value.includes('T')
                                    ? formatDate(value)
                                    : displayValue(value, statusOptions)
                                : displayValue(value, key === 'status' ? statusOptions : undefined)}
                        </dd>
                    </div>
                ))}
        </dl>
    );
}

function SearchBar({ placeholder = 'Search…' }: { placeholder?: string }) {
    const props = usePage<RecordValue>().props;
    const filters = isRecord(props.filters) ? props.filters : {};
    const currentSearch = String(filters.search ?? props.search ?? '');

    const apply = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const search = String(form.get('search') ?? '').trim();
        const params: RecordValue = {};

        if (isRecord(props.tab) || typeof props.tab === 'string') {
            params.tab = props.tab;
        }

        for (const [key, value] of Object.entries(filters)) {
            if (key === 'search' || value === null || value === undefined || value === '') {
                continue;
            }
            params[key] = value;
        }

        if (search) {
            params.search = search;
        }

        router.get(window.location.pathname, params, { preserveState: true, replace: true });
    };

    return (
        <form onSubmit={apply} className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div className="min-w-[12rem] flex-1 space-y-1">
                <Label htmlFor="inventory-search">Search</Label>
                <Input id="inventory-search" name="search" defaultValue={currentSearch} placeholder={placeholder} />
            </div>
            <Button type="submit" variant="outline">
                Apply
            </Button>
        </form>
    );
}

function TabBar() {
    const props = usePage<RecordValue>().props;
    const counts = isRecord(props.counts) ? props.counts : null;
    const tab = typeof props.tab === 'string' ? props.tab : null;
    if (!tab) {
        return null;
    }

    const search =
        isRecord(props.filters) && props.filters.search != null && props.filters.search !== ''
            ? String(props.filters.search)
            : '';

    const entries = counts
        ? Object.entries(counts)
        : tab === 'locations' || tab === 'rooms'
          ? [
                ['locations', Array.isArray(props.sites) ? props.sites.length : rowsFrom(props.sites).length],
                ['rooms', Array.isArray(props.rooms) ? props.rooms.length : rowsFrom(props.rooms).length],
            ]
          : [];

    if (entries.length === 0) {
        return null;
    }

    return (
        <div className="mb-4 flex flex-wrap gap-2">
            {entries.map(([key, count]) => {
                const params = new URLSearchParams();
                params.set('tab', String(key));
                if (search) {
                    params.set('search', search);
                }

                return (
                    <Button
                        key={String(key)}
                        size="sm"
                        variant={tab === key ? 'default' : 'outline'}
                        render={<Link href={`${window.location.pathname}?${params.toString()}`} preserveState />}
                    >
                        {humanize(String(key))}
                        <span className="text-muted-foreground ml-1 tabular-nums">({Number(count)})</span>
                    </Button>
                );
            })}
        </div>
    );
}

function InventoryScreen({ config }: { config: InventoryPageConfig }) {
    const props = usePage<RecordValue>().props;
    const { can } = usePermissions();
    const collectionKey =
        config.collection === 'sites' && props.tab === 'rooms' ? 'rooms' : config.collection;
    const collectionValue = collectionKey ? props[collectionKey] : null;
    const collectionRows = collectionKey ? rowsFrom(collectionValue) : [];
    const meta = paginatorMeta(collectionValue);
    const record = config.record && isRecord(props[config.record]) ? props[config.record] : null;
    const statusOptions = isRecord(props.statusOptions) ? props.statusOptions : undefined;
    const formParam = config.form?.param && isRecord(props[config.form.param]) ? props[config.form.param] : null;
    const formAction = config.form
        ? route(
              config.form.route,
              formParam?.id === undefined
                  ? {}
                  : { [config.form.routeParam ?? config.form.param ?? 'id']: formParam.id },
          )
        : '';
    const useModalForm = Boolean(config.form?.useModal);
    const canUseForm = can(config.form?.permission ?? (useModalForm ? 'manage inventory settings' : undefined));
    const canCreate = Boolean(config.createRoute && can(config.createPermission));
    const [formOpen, setFormOpen] = useState(false);
    const columns =
        config.columns ??
        fallbackColumns(collectionRows);

    const prefillAssetId =
        typeof props.prefillAssetId === 'string' || typeof props.prefillAssetId === 'number'
            ? String(props.prefillAssetId)
            : isRecord(props.asset)
              ? String(props.asset.id ?? '')
              : '';

    return (
        <>
            <PageHeader
                title={config.title}
                description={config.description}
                actions={
                    <>
                        {config.exportRoutes?.map((item) => (
                            <DownloadButton
                                key={`${item.name}-${item.label}`}
                                href={route(item.name, item.params)}
                                size="default"
                            >
                                {item.label}
                            </DownloadButton>
                        ))}
                        {canCreate && (
                            <Button render={<Link href={route(config.createRoute!)} />}>New</Button>
                        )}
                        {useModalForm && canUseForm && (
                            <Button type="button" onClick={() => setFormOpen(true)}>
                                <PlusIcon className="size-4" />
                                Add
                            </Button>
                        )}
                        {config.maintenanceActions &&
                            record &&
                            String(record.status ?? '') === 'reported' &&
                            can('manage inventory maintenance') && (
                                <Button
                                    render={
                                        <Link
                                            href={route('inventory.maintenance.complete.form', {
                                                maintenance: record.id,
                                            })}
                                        />
                                    }
                                >
                                    Record repair
                                </Button>
                            )}
                        {config.maintenanceActions &&
                            record &&
                            String(record.status ?? '') === 'reported' &&
                            can('manage inventory assets') && (
                                <Button
                                    variant="outline"
                                    render={
                                        <Link
                                            href={route('inventory.disposal.create', {
                                                asset_id: isRecord(record.asset)
                                                    ? record.asset.id
                                                    : record.asset_id,
                                            })}
                                        />
                                    }
                                >
                                    Request disposal
                                </Button>
                            )}
                    </>
                }
            />

            {config.form && !useModalForm && canUseForm && (
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>{config.form.submitLabel ?? config.title}</CardTitle>
                        {config.form.helpText && (
                            <p className="text-muted-foreground text-sm">{config.form.helpText}</p>
                        )}
                    </CardHeader>
                    <CardContent>
                        <Form
                            action={formAction}
                            method={config.form.method ?? 'post'}
                            onError={() => notify('error', 'Please correct the highlighted fields.')}
                            onSuccess={() => notify('success', 'Inventory record saved.')}
                            className="grid gap-4 sm:grid-cols-2"
                        >
                            {({ errors, processing }) => (
                                <>
                                    {config.form?.fields.map((field) => {
                                        const defaultValue =
                                            field.name === 'asset_id' && prefillAssetId
                                                ? prefillAssetId
                                                : formParam?.[field.name] ??
                                                  (field.name === 'asset_id'
                                                      ? props.prefillAssetId
                                                      : field.name === 'stock_item_id'
                                                        ? props.prefillStockItemId
                                                        : undefined);

                                        return (
                                            <FieldInput
                                                key={field.name}
                                                field={field}
                                                props={props}
                                                defaultValue={defaultValue}
                                                error={errors[field.name]}
                                            />
                                        );
                                    })}
                                    <div className="sm:col-span-2">
                                        <Button disabled={processing} loading={processing} type="submit">
                                            {config.form?.submitLabel ?? 'Save'}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            )}

            {config.form && useModalForm && canUseForm && (
                <Dialog open={formOpen} onOpenChange={setFormOpen}>
                    <DialogContent className="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>{config.form.submitLabel ?? `Add ${config.title}`}</DialogTitle>
                        </DialogHeader>
                        <Form
                            action={formAction}
                            method={config.form.method ?? 'post'}
                            onError={() => notify('error', 'Please correct the highlighted fields.')}
                            onSuccess={() => {
                                notify('success', 'Inventory record saved.');
                                setFormOpen(false);
                            }}
                            className="grid gap-4 sm:grid-cols-2"
                        >
                            {({ errors, processing }) => (
                                <>
                                    {config.form?.fields.map((field) => (
                                        <FieldInput
                                            key={field.name}
                                            field={field}
                                            props={props}
                                            idPrefix="modal-"
                                            error={errors[field.name]}
                                        />
                                    ))}
                                    <DialogFooter className="sm:col-span-2">
                                        <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
                                            Cancel
                                        </Button>
                                        <Button disabled={processing} loading={processing} type="submit">
                                            {config.form?.submitLabel ?? 'Save'}
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            )}

            {collectionKey && (
                <>
                    <TabBar />
                    {config.searchable !== false && <SearchBar />}
                    <Card>
                        <CardContent className="p-0 pt-2">
                            <DataGrid
                                rows={collectionRows}
                                columns={columns}
                                numbered={config.numbered !== false}
                                startNumber={(meta.currentPage - 1) * meta.perPage + 1}
                                showRoute={config.showRoute}
                                editRoute={config.editRoute}
                                editPermission={config.editPermission}
                                routeParam={config.routeParam}
                                statusOptions={statusOptions}
                                can={can}
                                maintenanceActions={config.maintenanceActions}
                                disposalActions={config.disposalActions}
                            />
                            {meta.links.length > 3 && (
                                <div className="p-4">
                                    <PaginationLinks links={meta.links} />
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </>
            )}

            {record && (
                <Card className="mb-6">
                    <CardContent className="pt-6">
                        <DetailGrid record={record} statusOptions={statusOptions} />
                    </CardContent>
                </Card>
            )}

            {!config.form && !record && !config.collection && (
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {Object.entries(props)
                        .filter(
                            ([key, value]) =>
                                ![
                                    'auth',
                                    'branding',
                                    'nav',
                                    'flash',
                                    'overlays',
                                    'notifications',
                                    'headerPeriod',
                                    'currentPeriod',
                                    'errors',
                                ].includes(key) &&
                                (Array.isArray(value) || isRecord(value)),
                        )
                        .map(([key, value]) => (
                            <Card key={key}>
                                <CardHeader>
                                    <CardTitle>{humanize(key)}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {Array.isArray(value) ? (
                                        <DataGrid
                                            rows={rowsFrom(value)}
                                            columns={fallbackColumns(rowsFrom(value))}
                                            numbered
                                            startNumber={1}
                                            can={can}
                                        />
                                    ) : (
                                        <DetailGrid record={value as RecordValue} />
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                </div>
            )}
        </>
    );
}

function fallbackColumns(rows: RecordValue[]): InventoryColumn[] {
    const hidden = new Set([
        'pivot',
        'permissions',
        'roles',
        'created_at',
        'updated_at',
        'deleted_at',
        'id',
    ]);

    return Array.from(new Set(rows.flatMap((row) => Object.keys(row))))
        .filter((key) => !hidden.has(key) && !key.endsWith('_id'))
        .slice(0, 7)
        .map((key) => ({
            key,
            label: humanize(key),
            type: key.includes('at') || key.includes('date') ? 'date' : key === 'status' ? 'status' : 'text',
        }));
}

export function makeInventoryPage(config: InventoryPageConfig) {
    function InventoryConfiguredPage() {
        return <InventoryScreen config={config} />;
    }

    InventoryConfiguredPage.layout = withAppLayout(config.title);

    return InventoryConfiguredPage;
}

const formFields: Record<string, InventoryField[]> = {
    'Assets/Create': [
        { name: 'name', label: 'Name', required: true },
        { name: 'serial_number', label: 'Serial number' },
        { name: 'category_id', label: 'Category', type: 'select', optionsFrom: 'categories' },
        { name: 'source_type', label: 'Source', type: 'select', optionsFrom: 'sourceTypeOptions', required: true },
        { name: 'condition', label: 'Condition', type: 'select', optionsFrom: 'conditionOptions', required: true },
        { name: 'status', label: 'Status', type: 'select', optionsFrom: 'statusOptions', required: true },
        { name: 'supplier_id', label: 'Supplier', type: 'select', optionsFrom: 'suppliers' },
        { name: 'donor_id', label: 'Donor', type: 'select', optionsFrom: 'donors' },
        { name: 'project_id', label: 'Project', type: 'select', optionsFrom: 'projects' },
        { name: 'location_id', label: 'Location', type: 'select', optionsFrom: 'locations' },
        { name: 'date_received', label: 'Date received', type: 'date' },
        { name: 'purchase_value', label: 'Purchase value', type: 'number' },
        { name: 'estimated_value', label: 'Estimated value', type: 'number' },
        { name: 'warranty_info', label: 'Warranty' },
        { name: 'description', label: 'Description', type: 'textarea' },
        { name: 'notes', label: 'Notes', type: 'textarea' },
    ],
    'Assets/Edit': [],
    'Assets/Assign': [
        { name: 'parent_department_id', label: 'Department', type: 'number', required: true },
        { name: 'sub_department_id', label: 'Sub-department', type: 'number' },
        { name: 'assigned_to_user_id', label: 'Assign to', type: 'user' },
        { name: 'assignment_notes', label: 'Notes', type: 'textarea' },
        { name: 'return_url' },
    ],
    'Assets/Transfer': [
        { name: 'to_location_id', label: 'New location', type: 'select', optionsFrom: 'locations', required: true },
        { name: 'reason', label: 'Reason', type: 'textarea', required: true },
        { name: 'return_url' },
    ],
    'StockItems/Create': [
        { name: 'name', label: 'Name', required: true },
        {
            name: 'status',
            label: 'Status',
            type: 'select',
            required: true,
            options: [
                { value: 'active', label: 'Active' },
                { value: 'inactive', label: 'Inactive' },
            ],
        },
        { name: 'category_id', label: 'Category', type: 'select', optionsFrom: 'categories' },
        { name: 'unit', label: 'Unit' },
        { name: 'quantity_received', label: 'Quantity received', type: 'number', required: true },
        { name: 'minimum_level', label: 'Minimum level', type: 'number', required: true },
        { name: 'supplier_id', label: 'Supplier', type: 'select', optionsFrom: 'suppliers' },
        { name: 'description', label: 'Description', type: 'textarea' },
    ],
    'StockItems/Edit': [],
    'Receipts/Create': [
        { name: 'source_type', label: 'Source type', required: true },
        { name: 'received_at', label: 'Received on', type: 'date', required: true },
        { name: 'supplier_id', label: 'Supplier', type: 'select', optionsFrom: 'suppliers' },
        { name: 'donor_id', label: 'Donor', type: 'select', optionsFrom: 'donors' },
        { name: 'project_id', label: 'Project', type: 'select', optionsFrom: 'projects' },
        { name: 'notes', label: 'Notes', type: 'textarea' },
        { name: 'lines[0][item_type]', label: 'First item type', required: true },
        { name: 'lines[0][name]', label: 'First item name', required: true },
        { name: 'lines[0][quantity]', label: 'Quantity', type: 'number', required: true },
    ],
    'Receipts/AddItems': [
        { name: 'lines[0][item_type]', label: 'Item type', required: true },
        { name: 'lines[0][name]', label: 'Item name', required: true },
        { name: 'lines[0][quantity]', label: 'Quantity', type: 'number', required: true },
        { name: 'lines[0][stock_item_id]', label: 'Existing stock item', type: 'stock' },
    ],
    'Borrowing/Create': [
        {
            name: 'item_type',
            label: 'Item type',
            type: 'select',
            required: true,
            options: [
                { value: 'asset', label: 'Asset' },
                { value: 'stock', label: 'Stock item' },
            ],
        },
        { name: 'asset_id', label: 'Asset', type: 'asset' },
        { name: 'stock_item_id', label: 'Stock item', type: 'stock' },
        { name: 'quantity', label: 'Quantity', type: 'number', required: true },
        { name: 'borrower_user_id', label: 'Borrower', type: 'user', required: true },
        { name: 'purpose', label: 'Purpose', type: 'textarea', required: true },
        { name: 'expected_return_date', label: 'Expected return', type: 'date' },
    ],
    'Dispatches/Create': [
        { name: 'title', label: 'Title', required: true },
        { name: 'destination', label: 'Destination', required: true },
        { name: 'purpose', label: 'Purpose', type: 'textarea', required: true },
        { name: 'dispatch_date', label: 'Dispatch date', type: 'date', required: true },
        { name: 'expected_return_date', label: 'Expected return', type: 'date', required: true },
        { name: 'responsible_user_ids[]', label: 'Responsible staff', type: 'user' },
        { name: 'notes', label: 'Notes', type: 'textarea' },
    ],
    'Dispatches/Edit': [],
    'Maintenance/Create': [
        { name: 'asset_id', label: 'Asset', type: 'asset', required: true },
        { name: 'problem_description', label: 'Problem description', type: 'textarea', required: true },
    ],
    'Maintenance/Complete': [
        { name: 'repair_provider', label: 'Who repaired it', required: true },
        { name: 'repaired_on', label: 'Repaired on', type: 'date', required: true },
        { name: 'repair_cost', label: 'Repair cost', type: 'number' },
        {
            name: 'condition_after',
            label: 'Condition after repair',
            type: 'select',
            optionsFrom: 'conditionOptions',
            required: true,
        },
        { name: 'repair_notes', label: 'Notes', type: 'textarea' },
    ],
    'Disposal/Create': [
        { name: 'asset_id', label: 'Asset', type: 'asset', required: true },
        { name: 'method', label: 'Disposal method', type: 'select', optionsFrom: 'methodOptions', required: true },
        { name: 'reason', label: 'Reason', type: 'textarea', required: true },
    ],
    'Verification/Create': [
        { name: 'name', label: 'Session name', required: true },
        { name: 'department_id', label: 'Department', type: 'select', optionsFrom: 'departments' },
        { name: 'notes', label: 'Notes', type: 'textarea' },
    ],
    'Categories/Index': [
        { name: 'name', label: 'Name', required: true },
        { name: 'description', label: 'Description', type: 'textarea' },
        {
            name: 'status',
            label: 'Status',
            type: 'select',
            required: true,
            options: [
                { value: 'active', label: 'Active' },
                { value: 'inactive', label: 'Inactive' },
            ],
        },
    ],
    'Locations/Index': [
        { name: 'name', label: 'Name', required: true },
        {
            name: 'status',
            label: 'Status',
            type: 'select',
            required: true,
            options: [
                { value: 'active', label: 'Active' },
                { value: 'inactive', label: 'Inactive' },
            ],
        },
        { name: 'parent_id', label: 'Parent site', type: 'select', optionsFrom: 'parentLocations' },
        { name: 'description', label: 'Description', type: 'textarea' },
        { name: 'return_tab' },
    ],
    'Suppliers/Index': [
        { name: 'name', label: 'Name', required: true },
        {
            name: 'status',
            label: 'Status',
            type: 'select',
            required: true,
            options: [
                { value: 'active', label: 'Active' },
                { value: 'inactive', label: 'Inactive' },
            ],
        },
        { name: 'contact_name', label: 'Contact name' },
        { name: 'phone', label: 'Phone' },
        { name: 'email', label: 'Email', type: 'email' },
        { name: 'address', label: 'Address', type: 'textarea' },
        { name: 'notes', label: 'Notes', type: 'textarea' },
    ],
    'Donors/Index': [
        { name: 'name', label: 'Name', required: true },
        {
            name: 'status',
            label: 'Status',
            type: 'select',
            required: true,
            options: [
                { value: 'active', label: 'Active' },
                { value: 'inactive', label: 'Inactive' },
            ],
        },
        { name: 'contact_name', label: 'Contact name' },
        { name: 'phone', label: 'Phone' },
        { name: 'email', label: 'Email', type: 'email' },
        { name: 'address', label: 'Address', type: 'textarea' },
        { name: 'notes', label: 'Notes', type: 'textarea' },
    ],
    'Projects/Index': [
        { name: 'name', label: 'Name', required: true },
        {
            name: 'status',
            label: 'Status',
            type: 'select',
            required: true,
            options: [
                { value: 'active', label: 'Active' },
                { value: 'inactive', label: 'Inactive' },
            ],
        },
        { name: 'donor_id', label: 'Donor', type: 'select', optionsFrom: 'donors' },
        { name: 'description', label: 'Description', type: 'textarea' },
    ],
    'Reports/Index': [{ name: 'user_id', label: 'Staff member', type: 'user' }],
};

Object.assign(formFields['Assets/Edit'], formFields['Assets/Create']);
Object.assign(formFields['StockItems/Edit'], formFields['StockItems/Create']);
Object.assign(formFields['Dispatches/Edit'], formFields['Dispatches/Create']);

const pageColumns: Record<string, InventoryColumn[]> = {
    'Assets/Index': [
        { key: 'asset_code', label: 'Asset ID' },
        { key: 'name', label: 'Name' },
        { key: 'category', label: 'Category' },
        { key: 'location', label: 'Location' },
        { key: 'responsible_user', label: 'Responsible' },
        { key: 'condition', label: 'Condition', type: 'status' },
        { key: 'status', label: 'Status', type: 'status' },
    ],
    'StockItems/Index': [
        { key: 'name', label: 'Name' },
        { key: 'category', label: 'Category' },
        { key: 'quantity_remaining', label: 'Remaining' },
        { key: 'minimum_level', label: 'Minimum' },
        { key: 'unit', label: 'Unit' },
        { key: 'status', label: 'Status', type: 'status' },
    ],
    'Receipts/Index': [
        { key: 'receipt_number', label: 'Receipt #' },
        { key: 'source_type', label: 'Source' },
        { key: 'received_at', label: 'Received', type: 'date' },
        { key: 'supplier', label: 'Supplier' },
        { key: 'donor', label: 'Donor' },
        { key: 'received_by_user', label: 'Received by' },
    ],
    'Borrowing/Index': [
        { key: 'request_number', label: 'Request #' },
        { key: 'asset', label: 'Asset' },
        { key: 'stock_item', label: 'Stock item' },
        { key: 'borrower', label: 'Borrower' },
        { key: 'requested_by', label: 'Requested by' },
        { key: 'expected_return_date', label: 'Expected return', type: 'date' },
        { key: 'status', label: 'Status', type: 'status' },
    ],
    'Dispatches/Index': [
        { key: 'dispatch_number', label: 'Dispatch #' },
        { key: 'title', label: 'Title' },
        { key: 'destination', label: 'Destination' },
        { key: 'dispatch_date', label: 'Dispatch date', type: 'date' },
        { key: 'expected_return_date', label: 'Expected return', type: 'date' },
        { key: 'status', label: 'Status', type: 'status' },
        { key: 'created_by', label: 'Created by' },
    ],
    'Maintenance/Index': [
        { key: 'record_number', label: 'Record #' },
        { key: 'asset', label: 'Asset' },
        { key: 'problem_description', label: 'Problem' },
        { key: 'reported_by', label: 'Reported by' },
        { key: 'reported_at', label: 'Reported on', type: 'date' },
        { key: 'status', label: 'Status', type: 'status' },
    ],
    'Disposal/Index': [
        { key: 'request_number', label: 'Request #' },
        { key: 'asset', label: 'Asset' },
        { key: 'method', label: 'Method' },
        { key: 'requested_by', label: 'Requested by' },
        { key: 'created_at', label: 'Requested on', type: 'date' },
        { key: 'status', label: 'Status', type: 'status' },
    ],
    'Verification/Index': [
        { key: 'name', label: 'Session' },
        { key: 'department', label: 'Department' },
        { key: 'started_by', label: 'Started by' },
        { key: 'started_at', label: 'Started', type: 'date' },
        { key: 'status', label: 'Status', type: 'status' },
    ],
    'MyItems/Index': [
        { key: 'asset_code', label: 'Asset ID' },
        { key: 'name', label: 'Name' },
        { key: 'location', label: 'Location' },
        { key: 'condition', label: 'Condition', type: 'status' },
        { key: 'status', label: 'Status', type: 'status' },
    ],
    'Categories/Index': [
        { key: 'name', label: 'Name' },
        { key: 'description', label: 'Description' },
        { key: 'status', label: 'Status', type: 'status' },
    ],
    'Locations/Index': [
        { key: 'name', label: 'Name' },
        { key: 'description', label: 'Description' },
        { key: 'status', label: 'Status', type: 'status' },
    ],
    'Suppliers/Index': [
        { key: 'name', label: 'Name' },
        { key: 'contact_name', label: 'Contact' },
        { key: 'phone', label: 'Phone' },
        { key: 'email', label: 'Email' },
        { key: 'status', label: 'Status', type: 'status' },
    ],
    'Donors/Index': [
        { key: 'name', label: 'Name' },
        { key: 'contact_name', label: 'Contact' },
        { key: 'phone', label: 'Phone' },
        { key: 'email', label: 'Email' },
        { key: 'status', label: 'Status', type: 'status' },
    ],
    'Projects/Index': [
        { key: 'name', label: 'Name' },
        { key: 'donor', label: 'Donor' },
        { key: 'description', label: 'Description' },
        { key: 'status', label: 'Status', type: 'status' },
    ],
};

const pageMeta: Record<string, Partial<InventoryPageConfig>> = {
    'MyItems/Index': {
        collection: 'assets',
        searchable: true,
        numbered: true,
        columns: pageColumns['MyItems/Index'],
        exportRoutes: [
            { label: 'Assets Excel', name: 'inventory.my-items.export', params: { type: 'assets', format: 'xlsx' } },
            { label: 'Assets PDF', name: 'inventory.my-items.export', params: { type: 'assets', format: 'pdf' } },
        ],
    },
    'Assets/Index': {
        collection: 'assets',
        createRoute: 'inventory.assets.create',
        createPermission: 'manage inventory assets',
        showRoute: 'inventory.assets.show',
        editRoute: 'inventory.assets.edit',
        editPermission: 'manage inventory assets',
        routeParam: 'asset',
        searchable: true,
        numbered: true,
        columns: pageColumns['Assets/Index'],
        exportRoutes: [
            { label: 'Export Excel', name: 'inventory.assets.export', params: { format: 'xlsx' } },
            { label: 'Export PDF', name: 'inventory.assets.export', params: { format: 'pdf' } },
        ],
    },
    'Assets/Show': { record: 'asset' },
    'Assets/Scan': { record: 'asset' },
    'Assets/Labels': { collection: 'labels', numbered: true, searchable: false },
    'StockItems/Index': {
        collection: 'stockItems',
        createRoute: 'inventory.stock-items.create',
        createPermission: 'manage inventory stock',
        editRoute: 'inventory.stock-items.edit',
        editPermission: 'manage inventory stock',
        routeParam: 'stockItem',
        searchable: true,
        numbered: true,
        columns: pageColumns['StockItems/Index'],
        exportRoutes: [
            { label: 'Export Excel', name: 'inventory.stock-items.export', params: { format: 'xlsx' } },
            { label: 'Export PDF', name: 'inventory.stock-items.export', params: { format: 'pdf' } },
        ],
    },
    'Receipts/Index': {
        collection: 'receipts',
        createRoute: 'inventory.receipts.create',
        createPermission: 'manage inventory receipts',
        showRoute: 'inventory.receipts.show',
        routeParam: 'receipt',
        searchable: true,
        numbered: true,
        columns: pageColumns['Receipts/Index'],
    },
    'Receipts/Show': { record: 'receipt' },
    'Borrowing/Index': {
        collection: 'requests',
        createRoute: 'inventory.borrowing.create',
        createPermission: 'manage inventory borrowing',
        showRoute: 'inventory.borrowing.show',
        routeParam: 'borrowRequest',
        searchable: true,
        numbered: true,
        columns: pageColumns['Borrowing/Index'],
    },
    'Borrowing/Show': { record: 'borrowRequest' },
    'Dispatches/Index': {
        collection: 'dispatches',
        createRoute: 'inventory.dispatches.create',
        createPermission: 'manage inventory dispatches',
        showRoute: 'inventory.dispatches.show',
        editRoute: 'inventory.dispatches.edit',
        editPermission: 'manage inventory dispatches',
        routeParam: 'dispatch',
        searchable: true,
        numbered: true,
        columns: pageColumns['Dispatches/Index'],
    },
    'Dispatches/Show': { record: 'dispatch' },
    'Dispatches/Return': { record: 'dispatch' },
    'Maintenance/Index': {
        collection: 'records',
        createRoute: 'inventory.maintenance.create',
        createPermission: 'manage inventory maintenance',
        showRoute: 'inventory.maintenance.show',
        routeParam: 'maintenance',
        searchable: true,
        numbered: true,
        columns: pageColumns['Maintenance/Index'],
        maintenanceActions: true,
    },
    'Maintenance/Show': { record: 'record', maintenanceActions: true },
    'Disposal/Index': {
        collection: 'requests',
        createRoute: 'inventory.disposal.create',
        createPermission: 'manage inventory assets',
        showRoute: 'inventory.disposal.show',
        routeParam: 'disposal',
        searchable: true,
        numbered: true,
        columns: pageColumns['Disposal/Index'],
        disposalActions: true,
    },
    'Disposal/Show': { record: 'disposal' },
    'Verification/Index': {
        collection: 'sessions',
        createRoute: 'inventory.verification.create',
        createPermission: 'manage inventory assets',
        showRoute: 'inventory.verification.show',
        routeParam: 'verification',
        searchable: true,
        numbered: true,
        columns: pageColumns['Verification/Index'],
    },
    'Verification/Show': { record: 'session' },
    'Categories/Index': {
        collection: 'categories',
        searchable: true,
        numbered: true,
        columns: pageColumns['Categories/Index'],
    },
    'Locations/Index': {
        collection: 'sites',
        searchable: true,
        numbered: true,
        columns: pageColumns['Locations/Index'],
    },
    'Suppliers/Index': {
        collection: 'suppliers',
        searchable: true,
        numbered: true,
        columns: pageColumns['Suppliers/Index'],
    },
    'Donors/Index': {
        collection: 'donors',
        searchable: true,
        numbered: true,
        columns: pageColumns['Donors/Index'],
    },
    'Projects/Index': {
        collection: 'projects',
        searchable: true,
        numbered: true,
        columns: pageColumns['Projects/Index'],
    },
    'Reports/Index': {
        exportRoutes: [
            { label: 'Export assets', name: 'inventory.reports.export.assets' },
            { label: 'Export stock', name: 'inventory.reports.export.stock' },
        ],
    },
};

const formRoutes: Record<
    string,
    {
        route: string;
        method?: 'post' | 'put';
        param?: string;
        routeParam?: string;
        useModal?: boolean;
        permission?: string;
        submitLabel?: string;
        helpText?: string;
    }
> = {
    'Assets/Create': { route: 'inventory.assets.store', permission: 'manage inventory assets', submitLabel: 'Register asset' },
    'Assets/Edit': {
        route: 'inventory.assets.update',
        method: 'put',
        param: 'asset',
        permission: 'manage inventory assets',
        submitLabel: 'Save asset',
    },
    'Assets/Assign': { route: 'inventory.assets.assign', param: 'asset', permission: 'manage inventory assets' },
    'Assets/Transfer': { route: 'inventory.assets.transfer', param: 'asset', permission: 'manage inventory transfers' },
    'StockItems/Create': { route: 'inventory.stock-items.store', permission: 'manage inventory stock', submitLabel: 'Save stock item' },
    'StockItems/Edit': {
        route: 'inventory.stock-items.update',
        method: 'put',
        param: 'stockItem',
        permission: 'manage inventory stock',
    },
    'Receipts/Create': { route: 'inventory.receipts.store', permission: 'manage inventory receipts', submitLabel: 'Save receipt' },
    'Receipts/AddItems': {
        route: 'inventory.receipts.store-items',
        param: 'receipt',
        permission: 'manage inventory receipts',
        submitLabel: 'Add items',
    },
    'Borrowing/Create': {
        route: 'inventory.borrowing.store',
        permission: 'manage inventory borrowing',
        submitLabel: 'Submit borrow request',
        helpText: 'Choose the asset or stock item, who will borrow it, and why.',
    },
    'Dispatches/Create': {
        route: 'inventory.dispatches.store',
        permission: 'manage inventory dispatches',
        submitLabel: 'Create dispatch',
        helpText: 'Save the header first. You can add assets and stock on the next screen.',
    },
    'Dispatches/Edit': {
        route: 'inventory.dispatches.update',
        method: 'put',
        param: 'dispatch',
        permission: 'manage inventory dispatches',
    },
    'Maintenance/Create': {
        route: 'inventory.maintenance.store',
        permission: 'manage inventory maintenance',
        submitLabel: 'Report problem',
        helpText: 'This marks the asset as under repair until you record a fix or request disposal.',
    },
    'Maintenance/Complete': {
        route: 'inventory.maintenance.complete',
        param: 'record',
        routeParam: 'maintenance',
        permission: 'manage inventory maintenance',
        submitLabel: 'Mark as fixed',
        helpText: 'Record the repair details to return this asset to service.',
    },
    'Disposal/Create': {
        route: 'inventory.disposal.store',
        permission: 'manage inventory assets',
        submitLabel: 'Submit disposal request',
        helpText: 'This sends a disposal request for approval. The asset is not removed until approved.',
    },
    'Verification/Create': {
        route: 'inventory.verification.store',
        permission: 'manage inventory assets',
        submitLabel: 'Start verification',
    },
    'Categories/Index': {
        route: 'inventory.categories.store',
        useModal: true,
        permission: 'manage inventory settings',
        submitLabel: 'Save category',
    },
    'Locations/Index': {
        route: 'inventory.locations.store',
        useModal: true,
        permission: 'manage inventory settings',
        submitLabel: 'Save location',
    },
    'Suppliers/Index': {
        route: 'inventory.suppliers.store',
        useModal: true,
        permission: 'manage inventory settings',
        submitLabel: 'Save supplier',
    },
    'Donors/Index': {
        route: 'inventory.donors.store',
        useModal: true,
        permission: 'manage inventory settings',
        submitLabel: 'Save donor',
    },
    'Projects/Index': {
        route: 'inventory.projects.store',
        useModal: true,
        permission: 'manage inventory settings',
        submitLabel: 'Save project',
    },
    'Reports/Index': {
        route: 'inventory.reports.notify-assignments',
        permission: 'manage inventory assets',
        submitLabel: 'Email assignment report',
        helpText: 'Send a staff member their current asset assignment list.',
    },
};

export function inventoryPageConfig(component: string): InventoryPageConfig {
    const page = component.replace(/^Inventory\//, '');
    const title = page.split('/').map(humanize).reverse().join(' ');
    const fields = formFields[page];
    const formMeta = formRoutes[page];

    return {
        title,
        description: 'Manage inventory records and workflow.',
        ...pageMeta[page],
        ...(fields && formMeta
            ? {
                  form: {
                      route: formMeta.route,
                      method: formMeta.method,
                      param: formMeta.param,
                      routeParam: formMeta.routeParam,
                      fields,
                      submitLabel: formMeta.submitLabel,
                      useModal: formMeta.useModal,
                      permission: formMeta.permission,
                      helpText: formMeta.helpText,
                  },
              }
            : {}),
    };
}
