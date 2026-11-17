import { Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    EyeIcon,
    FileTextIcon,
    GitBranchIcon,
    PencilIcon,
    PlusIcon,
    Trash2Icon,
    UsersIcon,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import AppLink from '@/components/shared/AppLink';
import RowActions from '@/components/shared/RowActions';
import { Badge } from '@/components/ui/badge';
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
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { PageHeader, PageShell } from '@/lib/page-kit';
import { notify } from '@/lib/notify';
import { route } from '@/lib/route';
import { cn } from '@/lib/utils';

type SubDepartment = {
    id: string | number;
    name: string;
    status: string;
    plan_section_number?: number | null;
    head_user_id?: string | null;
    users_count?: number;
};

type DepartmentUser = {
    id: string | number;
    name: string;
    email: string;
    phone?: string | null;
    status: string;
};

type Option = { id: string; name: string };

type DepartmentPayload = {
    id: string | number;
    name: string;
    status: string;
    plan_section_number?: number | null;
    users_count?: number;
    children_count?: number;
    parent?: { id?: string | number; name: string } | null;
    head?: { id?: string | number; name: string } | null;
    children: SubDepartment[];
};

function statusLabel(status: string): string {
    if (!status) {
        return '—';
    }

    return status.charAt(0).toUpperCase() + status.slice(1);
}

function StatusPill({ status }: { status: string }) {
    const active = status.toLowerCase() === 'active';

    return (
        <Badge
            variant="outline"
            className={cn(
                active
                    ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                    : 'border-border text-muted-foreground',
            )}
        >
            {statusLabel(status)}
        </Badge>
    );
}

export default function DepartmentsShow({
    department,
    users,
    departmentHeads,
    indexUrl,
    storeUrl,
}: {
    department: DepartmentPayload;
    users: DepartmentUser[];
    departmentHeads: Option[];
    indexUrl: string;
    storeUrl: string;
}) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editingId, setEditingId] = useState<string | number | null>(null);

    const form = useForm({
        department_id: '',
        name: '',
        status: 'active',
        parent_id: String(department.id),
        head_user_id: '',
        plan_section_number: '',
    });

    const closeModal = () => {
        setModalOpen(false);
        setEditingId(null);
        form.reset();
        form.setData({
            department_id: '',
            name: '',
            status: 'active',
            parent_id: String(department.id),
            head_user_id: '',
            plan_section_number: '',
        });
        form.clearErrors();
    };

    const openCreate = () => {
        setEditingId(null);
        form.setData({
            department_id: '',
            name: '',
            status: 'active',
            parent_id: String(department.id),
            head_user_id: '',
            plan_section_number: '',
        });
        form.clearErrors();
        setModalOpen(true);
    };

    const openEdit = (child: SubDepartment) => {
        setEditingId(child.id);
        form.setData({
            department_id: String(child.id),
            name: child.name,
            status: child.status || 'active',
            parent_id: String(department.id),
            head_user_id: child.head_user_id ? String(child.head_user_id) : '',
            plan_section_number:
                child.plan_section_number != null ? String(child.plan_section_number) : '',
        });
        form.clearErrors();
        setModalOpen(true);
    };

    const deleteChild = (child: SubDepartment) => {
        if (!window.confirm(`Delete "${child.name}"?`)) {
            return;
        }

        router.delete(route('department.destroy', { department: child.id }), {
            preserveScroll: true,
            onSuccess: () => notify('success', 'Sub-department deleted.'),
        });
    };

    return (
        <PageShell>
            <div className="space-y-1">
                <PageHeader
                    title="Department Details"
                    description={undefined}
                    actions={
                        <>
                            <Button type="button" onClick={openCreate}>
                                <PlusIcon className="size-4" />
                                Add Sub-Department
                            </Button>
                            <Button variant="outline" render={<Link href={indexUrl} />}>
                                <ArrowLeftIcon className="size-4" />
                                Back
                            </Button>
                        </>
                    }
                />
                <nav className="text-muted-foreground -mt-2 mb-2 flex flex-wrap items-center gap-1 text-xs">
                    <AppLink href={route('dashboard')} className="hover:text-foreground">
                        Home
                    </AppLink>
                    <span>/</span>
                    <AppLink href={indexUrl} className="hover:text-foreground">
                        Departments
                    </AppLink>
                    <span>/</span>
                    <span className="text-foreground">{department.name}</span>
                </nav>
            </div>

            <div className="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
                <Card className="shadow-none">
                    <CardHeader className="border-b">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <FileTextIcon className="text-muted-foreground size-4" />
                            Summary
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 pt-6 text-sm">
                        <SummaryRow label="Name" value={department.name} />
                        <SummaryRow label="Parent" value={department.parent?.name || 'Top-Level'} />
                        <SummaryRow label="Head" value={department.head?.name || '—'} />
                        <SummaryRow label="Users" value={String(department.users_count ?? users.length)} />
                        <SummaryRow
                            label="Sub-Departments"
                            value={String(department.children_count ?? department.children.length)}
                        />
                    </CardContent>
                </Card>

                <Card className="shadow-none">
                    <CardHeader className="border-b">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <GitBranchIcon className="text-muted-foreground size-4" />
                            Sub-Departments
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Code</TableHead>
                                    <TableHead>Users</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Action</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {department.children.map((child) => (
                                    <TableRow key={child.id}>
                                        <TableCell className="font-medium">{child.name}</TableCell>
                                        <TableCell className="tabular-nums">
                                            {child.plan_section_number ?? '—'}
                                        </TableCell>
                                        <TableCell className="tabular-nums">{child.users_count ?? 0}</TableCell>
                                        <TableCell>
                                            <StatusPill status={child.status} />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <RowActions
                                                items={[
                                                    {
                                                        label: 'View',
                                                        href: route('department.show', { department: child.id }),
                                                        icon: <EyeIcon />,
                                                    },
                                                    {
                                                        label: 'Edit',
                                                        icon: <PencilIcon />,
                                                        onClick: () => openEdit(child),
                                                    },
                                                    {
                                                        label: 'Delete',
                                                        icon: <Trash2Icon />,
                                                        destructive: true,
                                                        onClick: () => deleteChild(child),
                                                    },
                                                ]}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {department.children.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-muted-foreground py-10 text-center">
                                            No sub-departments yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <Card className="shadow-none">
                <CardHeader className="border-b">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <UsersIcon className="text-muted-foreground size-4" />
                        Users in This Department
                    </CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Phone</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell className="font-medium">{user.name}</TableCell>
                                    <TableCell>{user.email}</TableCell>
                                    <TableCell>{user.phone || '—'}</TableCell>
                                    <TableCell>
                                        <StatusPill status={user.status} />
                                    </TableCell>
                                </TableRow>
                            ))}
                            {users.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={4} className="text-muted-foreground py-10 text-center">
                                        No users in this department.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <Dialog
                open={modalOpen}
                onOpenChange={(next) => {
                    if (!next) {
                        closeModal();
                    } else {
                        setModalOpen(true);
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingId ? 'Edit Sub-Department' : 'Add Sub-Department'}</DialogTitle>
                    </DialogHeader>
                    <form
                        className="grid gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(storeUrl, {
                                preserveScroll: true,
                                onSuccess: () => {
                                    notify(
                                        'success',
                                        editingId ? 'Sub-department updated.' : 'Sub-department saved.',
                                    );
                                    closeModal();
                                },
                            });
                        }}
                    >
                        <div className="space-y-2">
                            <Label htmlFor="sub_dept_name">
                                Department Name <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="sub_dept_name"
                                name="name"
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                required
                            />
                            {form.errors.name && <p className="text-destructive text-sm">{form.errors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="sub_dept_status">
                                Status <span className="text-destructive">*</span>
                            </Label>
                            <select
                                id="sub_dept_status"
                                name="status"
                                className="border-input bg-background h-9 w-full rounded-md border px-3"
                                value={form.data.status}
                                onChange={(event) => form.setData('status', event.target.value)}
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            {form.errors.status && (
                                <p className="text-destructive text-sm">{form.errors.status}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="sub_dept_head">Head of Department</Label>
                            <select
                                id="sub_dept_head"
                                name="head_user_id"
                                className="border-input bg-background h-9 w-full rounded-md border px-3"
                                value={form.data.head_user_id}
                                onChange={(event) => form.setData('head_user_id', event.target.value)}
                            >
                                <option value="">--Select Head (Optional)--</option>
                                {departmentHeads.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                    </option>
                                ))}
                            </select>
                            {form.errors.head_user_id && (
                                <p className="text-destructive text-sm">{form.errors.head_user_id}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="sub_dept_plan">Year plan section #</Label>
                            <Input
                                id="sub_dept_plan"
                                name="plan_section_number"
                                type="number"
                                min={1}
                                max={999}
                                placeholder="e.g. 4 for codes 4.1, 4.2"
                                value={form.data.plan_section_number}
                                onChange={(event) => form.setData('plan_section_number', event.target.value)}
                            />
                            <p className="text-muted-foreground text-xs">Optional; used on the Year Plan panel.</p>
                            {form.errors.plan_section_number && (
                                <p className="text-destructive text-sm">{form.errors.plan_section_number}</p>
                            )}
                        </div>

                        {form.errors.parent_id && (
                            <p className="text-destructive text-sm">{form.errors.parent_id}</p>
                        )}

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeModal}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing} loading={form.processing}>
                                {editingId ? 'Save Changes' : 'Save Sub-Department'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </PageShell>
    );
}

function SummaryRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-start justify-between gap-3">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value}</span>
        </div>
    );
}

DepartmentsShow.layout = (page: ReactNode) => <AppLayout title="Department Details">{page}</AppLayout>;
