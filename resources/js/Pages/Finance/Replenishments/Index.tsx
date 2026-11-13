import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader } from '@/lib/page-kit';

type Row = {
    id: string | number;
    from?: string | null;
    to?: string | null;
    total_amount?: number | string;
    status?: string;
    description?: string | null;
    showUrl: string;
};

export default function ReplenishmentsIndex({
    replenishments,
    createListUrl,
}: {
    replenishments: Row[];
    createListUrl: string;
}) {
    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                title="Cash replenishments"
                description="Review petty cash replenishment batches."
                actions={
                    <Button render={<Link href={createListUrl} />}>New from paid petty cash</Button>
                }
            />
            <Card>
                <CardHeader>
                    <CardTitle>Batches</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                    {replenishments.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No replenishments yet.</p>
                    ) : (
                        replenishments.map((item) => (
                            <div key={item.id} className="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm">
                                <div>
                                    <div className="font-medium">
                                        {item.from} → {item.to} · {item.total_amount}
                                    </div>
                                    <div className="text-muted-foreground">{item.status}</div>
                                </div>
                                <Button size="sm" variant="outline" render={<Link href={item.showUrl} />}>
                                    Open
                                </Button>
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

ReplenishmentsIndex.layout = (page: ReactNode) => <AppLayout title="Replenishments">{page}</AppLayout>;
