import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PageHeader } from '@/lib/page-kit';
import { notify } from '@/lib/notify';

type Option = { id: string | number; name: string };
type TopDepartment = { id: string | number; name: string; children?: Option[] };

export default function UsersCreate({
    departments,
    topDepartments,
    roles,
    storeUrl,
    indexUrl,
}: {
    departments: Option[];
    topDepartments: TopDepartment[];
    roles: Option[];
    storeUrl: string;
    indexUrl: string;
}) {
    const form = useForm({
        name: '',
        email: '',
        title: '',
        phone: '',
        password: '',
        password_confirmation: '',
        department_id: '',
        sub_department_ids: [] as string[],
        roles: [] as string[],
        is_department_head: false,
    });

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-6">
            <PageHeader
                title="Create user"
                actions={
                    <Button variant="outline" render={<Link href={indexUrl} />}>
                        Back
                    </Button>
                }
            />
            <Card>
                <CardHeader>
                    <CardTitle>User information</CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        className="grid gap-3 md:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(storeUrl, {
                                onSuccess: () => notify('success', 'User registered.'),
                            });
                        }}
                    >
                        <label className="text-sm">
                            <span className="mb-1 block font-medium">Full name</span>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                        </label>
                        <label className="text-sm">
                            <span className="mb-1 block font-medium">Email</span>
                            <Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required />
                        </label>
                        <label className="text-sm">
                            <span className="mb-1 block font-medium">Job title</span>
                            <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        </label>
                        <label className="text-sm">
                            <span className="mb-1 block font-medium">Phone</span>
                            <Input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                        </label>
                        <label className="text-sm">
                            <span className="mb-1 block font-medium">Password</span>
                            <Input type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} required />
                        </label>
                        <label className="text-sm">
                            <span className="mb-1 block font-medium">Confirm password</span>
                            <Input
                                type="password"
                                value={form.data.password_confirmation}
                                onChange={(e) => form.setData('password_confirmation', e.target.value)}
                                required
                            />
                        </label>
                        <label className="text-sm">
                            <span className="mb-1 block font-medium">Department</span>
                            <select
                                className="border-input bg-background h-9 w-full rounded-md border px-3"
                                value={form.data.department_id}
                                onChange={(e) => form.setData('department_id', e.target.value)}
                            >
                                <option value="">Select department</option>
                                {(topDepartments.length ? topDepartments : departments).map((item) => (
                                    <option key={item.id} value={String(item.id)}>
                                        {item.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="text-sm md:col-span-2">
                            <span className="mb-1 block font-medium">Roles</span>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {roles.map((role) => {
                                    const checked = form.data.roles.includes(role.name);
                                    return (
                                        <label key={role.id} className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={checked}
                                                onChange={(e) => {
                                                    form.setData(
                                                        'roles',
                                                        e.target.checked
                                                            ? [...form.data.roles, role.name]
                                                            : form.data.roles.filter((name) => name !== role.name),
                                                    );
                                                }}
                                            />
                                            {role.name}
                                        </label>
                                    );
                                })}
                            </div>
                        </label>
                        <label className="flex items-center gap-2 text-sm md:col-span-2">
                            <input
                                type="checkbox"
                                checked={form.data.is_department_head}
                                onChange={(e) => form.setData('is_department_head', e.target.checked)}
                            />
                            Department head
                        </label>
                        {Object.values(form.errors).map((error) => (
                            <p key={error} className="text-destructive text-sm md:col-span-2">
                                {error}
                            </p>
                        ))}
                        <div className="md:col-span-2">
                            <Button type="submit" disabled={form.processing} loading={form.processing}>Create user
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

UsersCreate.layout = (page: ReactNode) => <AppLayout title="Create user">{page}</AppLayout>;
