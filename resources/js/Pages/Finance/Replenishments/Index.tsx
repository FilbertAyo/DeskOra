import { Link } from '@inertiajs/react';
import { EyeIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader, PageShell } from '@/lib/page-kit';

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
        <PageShell>
            <PageHeader
                title="Cash replenishments"
                description="Review petty cash replenishment batches."
                actions={<Button render={<Link href={createListUrl} />}>New from paid petty cash</Button>}
            />
            <Card className="shadow-none">
                <CardHeader className="border-b bg-muted/20">
                    <CardTitle className="text-base">Batches</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2 pt-6">
                    {replenishments.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No replenishments yet.</p>
                    ) : (
                        replenishments.map((item) => (
                            <div
                                key={item.id}
                                className="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm"
                            >
                                <div>
                                    <div className="font-medium">
                                        {item.from} → {item.to} · {item.total_amount}
                                    </div>
                                    <div className="text-muted-foreground">{item.status}</div>
                                </div>
                                <RowActions
                                    items={[{ label: 'Open', href: item.showUrl, icon: <EyeIcon /> }]}
                                />
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>
        </PageShell>
    );
}

ReplenishmentsIndex.layout = (page: ReactNode) => <AppLayout title="Replenishments">{page}</AppLayout>;
