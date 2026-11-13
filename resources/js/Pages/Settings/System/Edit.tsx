import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/lib/page-kit';
import { notify } from '@/lib/notify';

export default function SystemEdit({ formsLogoUrl, usingDefaultLogo, updateUrl }: { formsLogoPath: string; formsLogoUrl: string; usingDefaultLogo: boolean; updateUrl: string }) {
    const form = useForm<{ forms_logo: File | null; remove_forms_logo: boolean }>({ forms_logo: null, remove_forms_logo: false });
    return <div className="space-y-6">
        <PageHeader title="System configuration" description="Settings used on printable and downloadable forms." />
        <Card>
            <CardHeader><CardTitle>Forms logo</CardTitle><CardDescription>{usingDefaultLogo ? 'Currently using the default application logo.' : 'A custom forms logo is active.'}</CardDescription></CardHeader>
            <CardContent>
                <form className="grid gap-6 md:grid-cols-[16rem_1fr]" onSubmit={(event) => {
                    event.preventDefault();
                    form.put(updateUrl, { forceFormData: true, preserveScroll: true, onSuccess: () => notify('success', 'System settings updated.') });
                }}>
                    <div className="rounded-md border bg-muted/30 p-4"><img src={formsLogoUrl} alt="Forms logo" className="mx-auto max-h-32" /></div>
                    <div className="space-y-4">
                        <div className="space-y-2"><Label htmlFor="forms_logo">Upload new logo</Label><Input id="forms_logo" name="forms_logo" type="file" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" onChange={(event) => form.setData('forms_logo', event.target.files?.[0] ?? null)} />{form.errors.forms_logo && <p className="text-destructive text-sm">{form.errors.forms_logo}</p>}</div>
                        {!usingDefaultLogo && <label className="flex items-center gap-2 text-sm"><Checkbox name="remove_forms_logo" checked={form.data.remove_forms_logo} onCheckedChange={(checked) => form.setData('remove_forms_logo', checked === true)} />Reset to default logo</label>}
                        <Button type="submit" disabled={form.processing} loading={form.processing}>Save changes</Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>;
}

SystemEdit.layout = (page: ReactNode) => <AppLayout title="System configuration">{page}</AppLayout>;
