import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import ResourceTable from '@/components/shared/ResourceTable';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PageHeader } from '@/lib/page-kit';

type UsageRow = { id?: string; module?: string; feature?: string; label?: string; interactions?: number; count?: number; active_users?: number };

export default function FeatureUsageIndex(props: { from: string; to: string; activeUsers: number; totalInteractions: number; moduleRows: UsageRow[]; featureRows: UsageRow[]; modules: string[]; selectedModule?: string }) {
    const rows = props.featureRows.map((row, index) => ({ ...row, id: row.id ?? `${row.feature ?? row.label}-${index}` }));
    return <div className="space-y-6">
        <PageHeader title="Feature usage" description="Understand adoption across DeskOra modules." />
        <form className="flex flex-wrap gap-2 rounded-lg border p-4" onSubmit={(event) => { event.preventDefault(); router.get(window.location.pathname, Object.fromEntries(new FormData(event.currentTarget).entries()), { preserveState: true }); }}>
            <Input name="from" type="date" defaultValue={props.from} className="w-auto" /><Input name="to" type="date" defaultValue={props.to} className="w-auto" />
            <select name="module" defaultValue={props.selectedModule} className="border-input rounded-md border px-3"><option value="">All modules</option>{props.modules.map((module) => <option key={module}>{module}</option>)}</select><Button type="submit">Apply</Button>
        </form>
        <div className="grid gap-4 sm:grid-cols-2"><Metric title="Active users" value={props.activeUsers} /><Metric title="Interactions" value={props.totalInteractions} /></div>
        <ResourceTable rows={rows} columns={[
            { label: 'Feature', render: (row) => row.feature ?? row.label ?? '—' },
            { label: 'Module', render: (row) => row.module ?? '—' },
            { label: 'Interactions', render: (row) => row.interactions ?? row.count ?? 0 },
            { label: 'Active users', render: (row) => row.active_users ?? 0 },
        ]} />
    </div>;
}

function Metric({ title, value }: { title: string; value: number }) {
    return <Card><CardHeader><CardTitle className="text-sm text-muted-foreground">{title}</CardTitle></CardHeader><CardContent className="text-3xl font-semibold">{value}</CardContent></Card>;
}

FeatureUsageIndex.layout = (page: ReactNode) => <AppLayout title="Feature usage">{page}</AppLayout>;
