import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import ResourceTable from '@/components/shared/ResourceTable';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PageHeader } from '@/lib/page-kit';
import { notify } from '@/lib/notify';

type Department = { id: string; name: string; status: string; users_count?: number; children_count?: number; head?: { name: string } | null };
type Option = { id: string; name: string };

export default function DepartmentsIndex({ departments, parentDepartments, departmentHeads, storeUrl }: { departments: Department[]; parentDepartments: Option[]; departmentHeads: Option[]; storeUrl: string }) {
    const form = useForm({ name: '', status: 'active', parent_id: '', head_user_id: '', plan_section_number: '' });
    return <div className="space-y-6">
        <PageHeader title="Departments" description="Manage the organization structure and department heads." />
        <Card>
            <CardHeader><CardTitle>Add department</CardTitle></CardHeader>
            <CardContent>
                <form className="grid gap-3 md:grid-cols-3" onSubmit={(event) => {
                    event.preventDefault();
                    form.post(storeUrl, { preserveScroll: true, onSuccess: () => { form.reset(); notify('success', 'Department saved.'); } });
                }}>
                    <Input name="name" placeholder="Department name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                    <select name="status" className="border-input bg-background h-9 rounded-md border px-3" value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}><option value="active">Active</option><option value="inactive">Inactive</option></select>
                    <select name="parent_id" className="border-input bg-background h-9 rounded-md border px-3" value={form.data.parent_id} onChange={(event) => form.setData('parent_id', event.target.value)}><option value="">Top-level department</option>{parentDepartments.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                    <select name="head_user_id" className="border-input bg-background h-9 rounded-md border px-3" value={form.data.head_user_id} onChange={(event) => form.setData('head_user_id', event.target.value)}><option value="">No department head</option>{departmentHeads.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                    <Input name="plan_section_number" type="number" min={1} max={999} placeholder="Plan section number" value={form.data.plan_section_number} onChange={(event) => form.setData('plan_section_number', event.target.value)} />
                    <Button type="submit" disabled={form.processing} loading={form.processing}>Save department</Button>
                </form>
                {Object.values(form.errors).map((error) => <p className="text-destructive mt-2 text-sm" key={error}>{error}</p>)}
            </CardContent>
        </Card>
        <ResourceTable rows={departments} columns={[
            { label: 'Department', render: (item) => item.name },
            { label: 'Head', render: (item) => item.head?.name ?? '—' },
            { label: 'Users', render: (item) => item.users_count ?? 0 },
            { label: 'Sub-departments', render: (item) => item.children_count ?? 0 },
            { label: 'Status', render: (item) => item.status },
        ]} />
    </div>;
}

DepartmentsIndex.layout = (page: ReactNode) => <AppLayout title="Departments">{page}</AppLayout>;
