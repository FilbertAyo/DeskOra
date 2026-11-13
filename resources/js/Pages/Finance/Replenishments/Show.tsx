import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader } from '@/lib/page-kit';

export default function ReplenishmentsShow({
    replenishment,
    amountInWords,
    petties,
    initiator,
    verifier,
    approver,
    downloadUrl,
    indexUrl,
}: {
    replenishment: {
        from?: string | null;
        to?: string | null;
        total_amount?: number | string;
        status?: string;
        description?: string | null;
    };
    amountInWords: string;
    petties: Array<{ id?: string | number; code?: string; amount?: number | string; user?: string }>;
    initiator: { user?: { name?: string } | null; created_at?: string | null } | null;
    verifier: { user?: { name?: string } | null; created_at?: string | null } | null;
    approver: { user?: { name?: string } | null; created_at?: string | null } | null;
    downloadUrl: string;
    indexUrl: string;
}) {
    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                title="Replenishment"
                description={`${replenishment.from} → ${replenishment.to}`}
                actions={
                    <>
                        <Button variant="outline" render={<Link href={indexUrl} />}>
                            Back
                        </Button>
                        <Button variant="outline" render={<a href={downloadUrl} />}>
                            Download PDF
                        </Button>
                    </>
                }
            />
            <Card>
                <CardHeader>
                    <CardTitle>Summary</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2 text-sm">
                    <div>Amount: {replenishment.total_amount}</div>
                    <div>{amountInWords}</div>
                    <div>Status: {replenishment.status}</div>
                    <div>{replenishment.description}</div>
                    <div>Initiator: {initiator?.user?.name || '—'}</div>
                    <div>Verifier: {verifier?.user?.name || '—'}</div>
                    <div>Approver: {approver?.user?.name || '—'}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Included petty cash</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                    {petties.map((item, index) => (
                        <div key={String(item.id ?? index)} className="rounded-lg border px-3 py-2 text-sm">
                            {item.code} · {item.user} · {item.amount}
                        </div>
                    ))}
                </CardContent>
            </Card>
        </div>
    );
}

ReplenishmentsShow.layout = (page: ReactNode) => <AppLayout title="Replenishment">{page}</AppLayout>;
