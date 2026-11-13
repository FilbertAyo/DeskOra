import { Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PageHeader } from '@/lib/page-kit';
import { route } from '@/lib/route';

type Petty = {
    id: string | number;
    code?: string | null;
    amount?: number | string;
    status?: string;
    request_type?: string;
    paid_at?: string | null;
    user?: { name?: string } | null;
};

export default function ReplenishmentsList({
    petties,
    filters,
    createUrl,
    indexUrl,
}: {
    petties: Petty[];
    filters: { from?: string; to?: string };
    createUrl: string;
    indexUrl: string;
}) {
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                title="Paid petty cash"
                description="Select a date range to create a replenishment."
                actions={
                    <Button variant="outline" render={<Link href={indexUrl} />}>
                        Back
                    </Button>
                }
            />
            <Card>
                <CardContent className="flex flex-wrap items-end gap-3 pt-6">
                    <label className="text-sm">
                        <span className="mb-1 block font-medium">From</span>
                        <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                    </label>
                    <label className="text-sm">
                        <span className="mb-1 block font-medium">To</span>
                        <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                    </label>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => router.get(route('replenishment.pettycash'), { from, to }, { preserveState: true })}
                    >
                        Filter
                    </Button>
                    {from && to && (
                        <Button render={<Link href={`${createUrl}?from=${from}&to=${to}`} />}>Create replenishment</Button>
                    )}
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Paid items</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                    {petties.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No paid petty cash in this range.</p>
                    ) : (
                        petties.map((item) => (
                            <div key={item.id} className="rounded-lg border px-3 py-2 text-sm">
                                {item.code} · {item.user?.name} · {item.amount} · {item.paid_at}
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

ReplenishmentsList.layout = (page: ReactNode) => <AppLayout title="Paid petty cash">{page}</AppLayout>;
