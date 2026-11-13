import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { PageHeader } from '@/lib/page-kit';

export default function OfficialLettersCreate(props: Record<string, unknown>) {
    const storeUrl = String(props.storeUrl ?? '/official-letters');
    const indexUrl = String(props.indexUrl ?? '/official-letters');
    const form = useForm({
        subject: '',
        body: '',
        recipient: '',
    });

    return (
        <div className="mx-auto flex max-w-2xl flex-col gap-6">
            <PageHeader
                title="New Official Letter"
                actions={
                    <Button variant="outline" render={<Link href={indexUrl} />}>
                        Back
                    </Button>
                }
            />
            <Card>
                <CardContent className="space-y-3 pt-6">
                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(storeUrl);
                        }}
                    >
                        <label className="block text-sm">
                            <span className="mb-1 block font-medium">Subject</span>
                            <Input value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} />
                        </label>
                        <label className="block text-sm">
                            <span className="mb-1 block font-medium">Recipient</span>
                            <Input value={form.data.recipient} onChange={(e) => form.setData('recipient', e.target.value)} />
                        </label>
                        <label className="block text-sm">
                            <span className="mb-1 block font-medium">Body</span>
                            <Textarea rows={8} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />
                        </label>
                        <Button type="submit" disabled={form.processing} loading={form.processing}>Create
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

OfficialLettersCreate.layout = (page: ReactNode) => <AppLayout title="New Official Letter">{page}</AppLayout>;
