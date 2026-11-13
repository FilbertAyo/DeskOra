import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { PageHeader } from '@/lib/page-kit';
import { notify } from '@/lib/notify';

export default function ReplenishmentsCreate({
    from,
    to,
    totalAmount,
    storeUrl,
    indexUrl,
}: {
    from: string;
    to: string;
    totalAmount: number | string;
    storeUrl: string;
    indexUrl: string;
}) {
    const form = useForm({
        from,
        to,
        total_amount: totalAmount,
        description: '',
    });

    return (
        <div className="mx-auto flex max-w-2xl flex-col gap-6">
            <PageHeader
                title="Create replenishment"
                actions={
                    <Button variant="outline" render={<Link href={indexUrl} />}>
                        Back
                    </Button>
                }
            />
            <Card>
                <CardHeader>
                    <CardTitle>
                        {from} → {to}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(storeUrl, { onSuccess: () => notify('success', 'Replenishment created.') });
                        }}
                    >
                        <div className="text-sm">Total amount: {totalAmount}</div>
                        <label className="block text-sm">
                            <span className="mb-1 block font-medium">Description</span>
                            <Textarea
                                rows={5}
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                                required
                            />
                        </label>
                        {Object.values(form.errors).map((error) => (
                            <p key={error} className="text-destructive text-sm">
                                {error}
                            </p>
                        ))}
                        <Button type="submit" disabled={form.processing} loading={form.processing}>Submit
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

ReplenishmentsCreate.layout = (page: ReactNode) => <AppLayout title="Create replenishment">{page}</AppLayout>;
