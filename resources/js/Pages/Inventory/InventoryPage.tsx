import { Form, Link, usePage } from '@inertiajs/react';
import { EyeIcon, PencilIcon } from 'lucide-react';
import { useMemo, type ReactNode } from 'react';

import RowActions from '@/components/shared/RowActions';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { notify } from '@/lib/notify';
import { PageHeader, withAppLayout } from '@/lib/page-kit';
import { route } from '@/lib/route';

type RecordValue = Record<string, unknown>;

export type InventoryField = {
    name: string;
    label?: string;
    type?: 'text' | 'number' | 'date' | 'email' | 'textarea';
    required?: boolean;
};

export type InventoryPageConfig = {
    title: string;
    description?: string;
    collection?: string;
    record?: string;
    createRoute?: string;
    showRoute?: string;
    editRoute?: string;
    routeParam?: string;
    exportRoutes?: Array<{ label: string; name: string; params?: RecordValue }>;
    form?: {
        route: string;
        method?: 'post' | 'put' | 'patch';
        param?: string;
        routeParam?: string;
        fields: InventoryField[];
        submitLabel?: string;
    };
};

function isRecord(value: unknown): value is RecordValue {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function humanize(value: string): string {
    return value
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function displayValue(value: unknown): ReactNode {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (typeof value === 'string' || typeof value === 'number') return String(value);
    if (Array.isArray(value)) return `${value.length} item${value.length === 1 ? '' : 's'}`;
    if (isRecord(value)) {
        const preferred = value.name ?? value.label ?? value.title ?? value.asset_code ?? value.request_number ?? value.record_number;
        return preferred ? String(preferred) : 'View details';
    }
    return String(value);
}

function rowsFrom(value: unknown): RecordValue[] {
    if (Array.isArray(value)) return value.filter(isRecord);
    if (isRecord(value) && Array.isArray(value.data)) return value.data.filter(isRecord);
    return [];
}

function importantColumns(rows: RecordValue[]): string[] {
    const hidden = new Set(['pivot', 'permissions', 'roles', 'created_at', 'updated_at', 'deleted_at']);
    return Array.from(new Set(rows.flatMap((row) => Object.keys(row))))
        .filter((key) => !hidden.has(key))
        .slice(0, 8);
}

function DataGrid({
    rows,
    showRoute,
    editRoute,
    routeParam = 'id',
}: {
    rows: RecordValue[];
    showRoute?: string;
    editRoute?: string;
    routeParam?: string;
}) {
    const columns = useMemo(() => importantColumns(rows), [rows]);

    if (rows.length === 0) {
        return <p className="text-muted-foreground py-8 text-center text-sm">No records found.</p>;
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left">
                        {columns.map((column) => <th className="px-3 py-2 font-medium" key={column}>{humanize(column)}</th>)}
                        {(showRoute || editRoute) && <th className="px-3 py-2 text-right font-medium">Actions</th>}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => (
                        <tr className="border-b last:border-0" key={String(row.id ?? index)}>
                            {columns.map((column) => <td className="max-w-64 px-3 py-3" key={column}>{displayValue(row[column])}</td>)}
                            {(showRoute || editRoute) && (
                                <td className="whitespace-nowrap px-3 py-3 text-right">
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
                                                    editRoute && row.id !== undefined
                                                        ? route(editRoute, { [routeParam]: row.id })
                                                        : undefined,
                                                icon: <PencilIcon />,
                                                hidden: !(editRoute && row.id !== undefined),
                                            },
                                        ]}
                                    />
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function DetailGrid({ record }: { record: RecordValue }) {
    return (
        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {Object.entries(record)
                .filter(([key]) => !['pivot', 'created_at', 'updated_at', 'deleted_at'].includes(key))
                .map(([key, value]) => (
                    <div key={key}>
                        <dt className="text-muted-foreground text-xs font-medium uppercase tracking-wide">{humanize(key)}</dt>
                        <dd className="mt-1 text-sm">{displayValue(value)}</dd>
                    </div>
                ))}
        </dl>
    );
}

function InventoryScreen({ config }: { config: InventoryPageConfig }) {
    const props = usePage<RecordValue>().props;
    const collectionRows = config.collection ? rowsFrom(props[config.collection]) : [];
    const record = config.record && isRecord(props[config.record]) ? props[config.record] : null;
    const formParam = config.form?.param && isRecord(props[config.form.param]) ? props[config.form.param] : null;
    const formAction = config.form
        ? route(
            config.form.route,
            formParam?.id === undefined ? {} : { [config.form.routeParam ?? config.form.param ?? 'id']: formParam.id },
        )
        : '';

    return (
        <>
            <PageHeader
                title={config.title}
                description={config.description}
                actions={
                    <>
                        {config.exportRoutes?.map((item) => (
                            <Button key={`${item.name}-${item.label}`} variant="outline" render={<a href={route(item.name, item.params)} />}>{item.label}</Button>
                        ))}
                        {config.createRoute && <Button render={<Link href={route(config.createRoute)} />}>New</Button>}
                    </>
                }
            />

            {config.form && (
                <Card className="mb-6">
                    <CardHeader><CardTitle>{config.form.submitLabel ?? config.title}</CardTitle></CardHeader>
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
                                        const defaultValue = formParam?.[field.name];
                                        return (
                                            <div className={field.type === 'textarea' ? 'sm:col-span-2' : ''} key={field.name}>
                                                <Label htmlFor={field.name}>{field.label ?? humanize(field.name)}</Label>
                                                {field.type === 'textarea' ? (
                                                    <Textarea id={field.name} name={field.name} required={field.required} defaultValue={defaultValue == null ? '' : String(defaultValue)} />
                                                ) : (
                                                    <Input id={field.name} name={field.name} type={field.type ?? 'text'} required={field.required} defaultValue={defaultValue == null ? '' : String(defaultValue)} />
                                                )}
                                                {errors[field.name] && <p className="text-destructive mt-1 text-xs">{errors[field.name]}</p>}
                                            </div>
                                        );
                                    })}
                                    <div className="sm:col-span-2">
                                        <Button disabled={processing} loading={processing} type="submit">{config.form?.submitLabel ?? 'Save'}</Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            )}

            {record && <Card className="mb-6"><CardContent className="pt-6"><DetailGrid record={record} /></CardContent></Card>}
            {config.collection && <Card><CardContent className="p-0"><DataGrid rows={collectionRows} showRoute={config.showRoute} editRoute={config.editRoute} routeParam={config.routeParam} /></CardContent></Card>}

            {!config.form && !record && !config.collection && (
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {Object.entries(props)
                        .filter(([key, value]) => !['auth', 'branding', 'nav', 'flash', 'overlays', 'notifications', 'currentPeriod', 'errors'].includes(key) && (Array.isArray(value) || isRecord(value)))
                        .map(([key, value]) => (
                            <Card key={key}>
                                <CardHeader><CardTitle>{humanize(key)}</CardTitle></CardHeader>
                                <CardContent>{Array.isArray(value) ? <DataGrid rows={rowsFrom(value)} /> : <DetailGrid record={value as RecordValue} />}</CardContent>
                            </Card>
                        ))}
                </div>
            )}
        </>
    );
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
        { name: 'name', required: true }, { name: 'serial_number' }, { name: 'category_id', type: 'number' },
        { name: 'source_type', required: true }, { name: 'condition', required: true }, { name: 'status', required: true },
        { name: 'supplier_id', type: 'number' }, { name: 'donor_id', type: 'number' }, { name: 'project_id', type: 'number' },
        { name: 'location_id', type: 'number' }, { name: 'date_received', type: 'date' },
        { name: 'purchase_value', type: 'number' }, { name: 'estimated_value', type: 'number' },
        { name: 'warranty_info' }, { name: 'description', type: 'textarea' }, { name: 'notes', type: 'textarea' },
    ],
    'Assets/Edit': [],
    'Assets/Assign': [
        { name: 'parent_department_id', type: 'number', required: true }, { name: 'sub_department_id', type: 'number' },
        { name: 'assigned_to_user_id', type: 'number' }, { name: 'assignment_notes', type: 'textarea' }, { name: 'return_url' },
    ],
    'Assets/Transfer': [
        { name: 'to_location_id', type: 'number', required: true }, { name: 'reason', type: 'textarea', required: true },
        { name: 'return_url' },
    ],
    'StockItems/Create': [
        { name: 'name', required: true }, { name: 'status', required: true }, { name: 'category_id', type: 'number' },
        { name: 'unit' }, { name: 'quantity_received', type: 'number', required: true }, { name: 'minimum_level', type: 'number', required: true },
        { name: 'supplier_id', type: 'number' }, { name: 'description', type: 'textarea' },
    ],
    'StockItems/Edit': [],
    'Receipts/Create': [
        { name: 'source_type', required: true }, { name: 'received_at', type: 'date', required: true },
        { name: 'supplier_id', type: 'number' }, { name: 'donor_id', type: 'number' },
        { name: 'project_id', type: 'number' }, { name: 'notes', type: 'textarea' },
        { name: 'lines[0][item_type]', required: true }, { name: 'lines[0][name]', required: true },
        { name: 'lines[0][quantity]', type: 'number', required: true },
    ],
    'Receipts/AddItems': [
        { name: 'lines[0][item_type]', required: true }, { name: 'lines[0][name]', required: true },
        { name: 'lines[0][quantity]', type: 'number', required: true }, { name: 'lines[0][stock_item_id]', type: 'number' },
    ],
    'Borrowing/Create': [
        { name: 'item_type', required: true }, { name: 'asset_id', type: 'number' }, { name: 'stock_item_id', type: 'number' },
        { name: 'quantity', type: 'number', required: true }, { name: 'borrower_user_id', type: 'number', required: true },
        { name: 'purpose', type: 'textarea', required: true }, { name: 'expected_return_date', type: 'date' },
    ],
    'Dispatches/Create': [
        { name: 'title', required: true }, { name: 'destination', required: true }, { name: 'purpose', type: 'textarea', required: true },
        { name: 'dispatch_date', type: 'date', required: true }, { name: 'expected_return_date', type: 'date', required: true },
        { name: 'responsible_user_ids[]' }, { name: 'notes', type: 'textarea' },
    ],
    'Dispatches/Edit': [],
    'Maintenance/Create': [
        { name: 'asset_id', type: 'number', required: true }, { name: 'problem_description', type: 'textarea', required: true },
    ],
    'Maintenance/Complete': [
        { name: 'repair_provider', required: true }, { name: 'repaired_on', type: 'date', required: true },
        { name: 'repair_cost', type: 'number' }, { name: 'condition_after', required: true }, { name: 'repair_notes', type: 'textarea' },
    ],
    'Disposal/Create': [
        { name: 'asset_id', type: 'number', required: true }, { name: 'method', required: true },
        { name: 'reason', type: 'textarea', required: true },
    ],
    'Verification/Create': [
        { name: 'name', required: true }, { name: 'department_id', type: 'number' }, { name: 'notes', type: 'textarea' },
    ],
    'Categories/Index': [
        { name: 'name', required: true }, { name: 'description', type: 'textarea' }, { name: 'status', required: true },
    ],
    'Locations/Index': [
        { name: 'name', required: true }, { name: 'status', required: true }, { name: 'parent_id', type: 'number' },
        { name: 'description', type: 'textarea' }, { name: 'return_tab' },
    ],
    'Suppliers/Index': [
        { name: 'name', required: true }, { name: 'status', required: true }, { name: 'contact_name' },
        { name: 'phone' }, { name: 'email', type: 'email' }, { name: 'address', type: 'textarea' }, { name: 'notes', type: 'textarea' },
    ],
    'Donors/Index': [
        { name: 'name', required: true }, { name: 'status', required: true }, { name: 'contact_name' },
        { name: 'phone' }, { name: 'email', type: 'email' }, { name: 'address', type: 'textarea' }, { name: 'notes', type: 'textarea' },
    ],
    'Projects/Index': [
        { name: 'name', required: true }, { name: 'status', required: true }, { name: 'donor_id', type: 'number' },
        { name: 'description', type: 'textarea' },
    ],
    'Reports/Index': [{ name: 'user_id', type: 'number' }],
};

Object.assign(formFields['Assets/Edit'], formFields['Assets/Create']);
Object.assign(formFields['StockItems/Edit'], formFields['StockItems/Create']);
Object.assign(formFields['Dispatches/Edit'], formFields['Dispatches/Create']);

const pageMeta: Record<string, Partial<InventoryPageConfig>> = {
    'MyItems/Index': { collection: 'assets', exportRoutes: [
        { label: 'Assets Excel', name: 'inventory.my-items.export', params: { type: 'assets', format: 'xlsx' } },
        { label: 'Assets PDF', name: 'inventory.my-items.export', params: { type: 'assets', format: 'pdf' } },
    ] },
    Index: {},
    'Assets/Index': { collection: 'assets', createRoute: 'inventory.assets.create', showRoute: 'inventory.assets.show', editRoute: 'inventory.assets.edit', routeParam: 'asset', exportRoutes: [
        { label: 'Export Excel', name: 'inventory.assets.export', params: { format: 'xlsx' } },
        { label: 'Export PDF', name: 'inventory.assets.export', params: { format: 'pdf' } },
    ] },
    'Assets/Show': { record: 'asset' }, 'Assets/Scan': { record: 'asset' }, 'Assets/Labels': { collection: 'labels' },
    'StockItems/Index': { collection: 'stockItems', createRoute: 'inventory.stock-items.create', editRoute: 'inventory.stock-items.edit', routeParam: 'stockItem', exportRoutes: [
        { label: 'Export Excel', name: 'inventory.stock-items.export', params: { format: 'xlsx' } },
        { label: 'Export PDF', name: 'inventory.stock-items.export', params: { format: 'pdf' } },
    ] },
    'Receipts/Index': { collection: 'receipts', createRoute: 'inventory.receipts.create', showRoute: 'inventory.receipts.show', routeParam: 'receipt' },
    'Receipts/Show': { record: 'receipt' },
    'Borrowing/Index': { collection: 'requests', createRoute: 'inventory.borrowing.create', showRoute: 'inventory.borrowing.show', routeParam: 'borrowRequest' },
    'Borrowing/Show': { record: 'borrowRequest' },
    'Dispatches/Index': { collection: 'dispatches', createRoute: 'inventory.dispatches.create', showRoute: 'inventory.dispatches.show', editRoute: 'inventory.dispatches.edit', routeParam: 'dispatch' },
    'Dispatches/Show': { record: 'dispatch' }, 'Dispatches/Return': { record: 'dispatch' },
    'Maintenance/Index': { collection: 'records', createRoute: 'inventory.maintenance.create', showRoute: 'inventory.maintenance.show', routeParam: 'maintenance' },
    'Maintenance/Show': { record: 'record' },
    'Disposal/Index': { collection: 'requests', createRoute: 'inventory.disposal.create', showRoute: 'inventory.disposal.show', routeParam: 'disposal' },
    'Disposal/Show': { record: 'request' },
    'Verification/Index': { collection: 'sessions', createRoute: 'inventory.verification.create', showRoute: 'inventory.verification.show', routeParam: 'verification' },
    'Verification/Show': { record: 'session' },
    'Categories/Index': { collection: 'categories' }, 'Locations/Index': { collection: 'sites' },
    'Suppliers/Index': { collection: 'suppliers' }, 'Donors/Index': { collection: 'donors' }, 'Projects/Index': { collection: 'projects' },
    'Reports/Index': { exportRoutes: [
        { label: 'Export assets', name: 'inventory.reports.export.assets' },
        { label: 'Export stock', name: 'inventory.reports.export.stock' },
    ] },
};

const formRoutes: Record<string, { route: string; method?: 'post' | 'put'; param?: string; routeParam?: string }> = {
    'Assets/Create': { route: 'inventory.assets.store' }, 'Assets/Edit': { route: 'inventory.assets.update', method: 'put', param: 'asset' },
    'Assets/Assign': { route: 'inventory.assets.assign', param: 'asset' }, 'Assets/Transfer': { route: 'inventory.assets.transfer', param: 'asset' },
    'StockItems/Create': { route: 'inventory.stock-items.store' }, 'StockItems/Edit': { route: 'inventory.stock-items.update', method: 'put', param: 'stockItem' },
    'Receipts/Create': { route: 'inventory.receipts.store' }, 'Receipts/AddItems': { route: 'inventory.receipts.store-items', param: 'receipt' },
    'Borrowing/Create': { route: 'inventory.borrowing.store' }, 'Dispatches/Create': { route: 'inventory.dispatches.store' },
    'Dispatches/Edit': { route: 'inventory.dispatches.update', method: 'put', param: 'dispatch' },
    'Maintenance/Create': { route: 'inventory.maintenance.store' }, 'Maintenance/Complete': { route: 'inventory.maintenance.complete', param: 'record', routeParam: 'maintenance' },
    'Disposal/Create': { route: 'inventory.disposal.store' }, 'Verification/Create': { route: 'inventory.verification.store' },
    'Categories/Index': { route: 'inventory.categories.store' }, 'Locations/Index': { route: 'inventory.locations.store' },
    'Suppliers/Index': { route: 'inventory.suppliers.store' }, 'Donors/Index': { route: 'inventory.donors.store' },
    'Projects/Index': { route: 'inventory.projects.store' }, 'Reports/Index': { route: 'inventory.reports.notify-assignments' },
};

export function inventoryPageConfig(component: string): InventoryPageConfig {
    const page = component.replace(/^Inventory\//, '');
    const title = page.split('/').map(humanize).reverse().join(' ');
    const fields = formFields[page];

    return {
        title,
        description: 'Manage inventory records and workflow.',
        ...pageMeta[page],
        ...(fields ? { form: { ...formRoutes[page], fields } } : {}),
    };
}
