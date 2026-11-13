import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import ResourceTable from '@/components/shared/ResourceTable';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { PageHeader } from '@/lib/page-kit';

type Activity = { id: number; created_at: string; log_name?: string; event?: string; description: string; causer?: { name: string } | null; subject_type?: string };

export default function ActivityLogIndex({ activities, staffUsers, logNames, events, filters }: { activities: { data: Activity[] }; staffUsers: Array<{ id: string; name: string }>; logNames: string[]; events: string[]; subjectTypes: string[]; filters: Record<string, string> }) {
    return <div className="space-y-6">
        <PageHeader title="Activity log" description="Review auditable staff and system actions." />
        <form className="grid gap-2 rounded-lg border p-4 md:grid-cols-6" onSubmit={(event) => {
            event.preventDefault();
            router.get(window.location.pathname, Object.fromEntries(new FormData(event.currentTarget).entries()), { preserveState: true });
        }}>
            <select name="causer_id" defaultValue={filters.causer_id} className="border-input rounded-md border px-3"><option value="">All staff</option>{staffUsers.map((user) => <option value={user.id} key={user.id}>{user.name}</option>)}</select>
            <select name="log_name" defaultValue={filters.log_name} className="border-input rounded-md border px-3"><option value="">All logs</option>{logNames.map((item) => <option key={item}>{item}</option>)}</select>
            <select name="event" defaultValue={filters.event} className="border-input rounded-md border px-3"><option value="">All events</option>{events.map((item) => <option key={item}>{item}</option>)}</select>
            <Input name="from" type="date" defaultValue={filters.from} /><Input name="to" type="date" defaultValue={filters.to} /><Button type="submit">Filter</Button>
        </form>
        <ResourceTable rows={activities.data} columns={[
            { label: 'When', render: (item) => new Date(item.created_at).toLocaleString() },
            { label: 'Staff', render: (item) => item.causer?.name ?? 'System' },
            { label: 'Log', render: (item) => item.log_name ?? 'default' },
            { label: 'Event', render: (item) => item.event ?? '—' },
            { label: 'Description', render: (item) => item.description },
        ]} />
    </div>;
}

ActivityLogIndex.layout = (page: ReactNode) => <AppLayout title="Activity log">{page}</AppLayout>;
