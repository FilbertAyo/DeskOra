import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PageHeader } from '@/lib/page-kit';
import { notify } from '@/lib/notify';

type Permission = { id: number | string; name: string };

export default function RolesEdit({
    role,
    permissions,
    selectedPermissions,
    isSystemManaged,
    updateUrl,
    indexUrl,
}: {
    role: { id: number | string; name: string };
    permissions: Permission[];
    selectedPermissions: string[];
    isSystemManaged: boolean;
    updateUrl: string;
    indexUrl: string;
}) {
    const form = useForm({ name: role.name, permissions: selectedPermissions });

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-6">
            <PageHeader
                title={`Edit ${role.name}`}
                actions={
                    <Button variant="outline" render={<Link href={indexUrl} />}>
                        Back
                    </Button>
                }
            />
            <Card>
                <CardHeader>
                    <CardTitle>Role details</CardTitle>
                </CardHeader>
                <CardContent>
                    {isSystemManaged ? (
                        <p className="text-muted-foreground text-sm">This role is system-managed and cannot be changed.</p>
                    ) : (
                        <form
                            className="space-y-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.put(updateUrl, { onSuccess: () => notify('success', 'Role updated.') });
                            }}
                        >
                            <label className="block text-sm">
                                <span className="mb-1 block font-medium">Role name</span>
                                <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                            </label>
                            <div className="flex items-center justify-between gap-2">
                                <h3 className="text-sm font-medium">Permissions</h3>
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => form.setData('permissions', permissions.map((item) => item.name))}
                                    >
                                        Select all
                                    </Button>
                                    <Button type="button" size="sm" variant="outline" onClick={() => form.setData('permissions', [])}>
                                        Clear
                                    </Button>
                                </div>
                            </div>
                            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {permissions.map((permission) => {
                                    const checked = form.data.permissions.includes(permission.name);
                                    return (
                                        <label key={permission.id} className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={checked}
                                                onChange={(e) => {
                                                    form.setData(
                                                        'permissions',
                                                        e.target.checked
                                                            ? [...form.data.permissions, permission.name]
                                                            : form.data.permissions.filter((name) => name !== permission.name),
                                                    );
                                                }}
                                            />
                                            {permission.name}
                                        </label>
                                    );
                                })}
                            </div>
                            {Object.values(form.errors).map((error) => (
                                <p key={error} className="text-destructive text-sm">
                                    {error}
                                </p>
                            ))}
                            <Button type="submit" disabled={form.processing} loading={form.processing}>Save role
                            </Button>
                        </form>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

RolesEdit.layout = (page: ReactNode) => <AppLayout title="Edit role">{page}</AppLayout>;
