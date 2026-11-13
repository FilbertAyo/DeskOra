import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Stats = { eligible: number; responded: number; nominated: number; skipped: number; rate: number };
type Tally = { user: { name: string; title: string | null; department: string | null; avatar: string }; votes: number };
type Reason = { id: string; nomineeName: string; reason: string; createdAt: string | null };
type Props = {
    period: { label: string; opensAt: string; closesAt: string; isWindowOpen: boolean; showUrl: string };
    detail: { stats: Stats; tallies: Tally[]; reasons: Reason[] }; indexUrl: string;
};

export default function AppreciationShow({ period, detail, indexUrl }: Props) {
    return <div className="flex flex-col gap-6">
        <div><a href={indexUrl} className="text-sm">← Staff appreciation</a><h1 className="mt-2 text-2xl font-semibold">{period.label} {period.isWindowOpen && <Badge className="ml-2">Open</Badge>}</h1><p className="text-muted-foreground text-sm">{period.opensAt} – {period.closesAt}</p></div>
        <div className="grid gap-3 sm:grid-cols-5">{Object.entries(detail.stats).map(([label, value]) => <Card key={label}><CardContent className="pt-6"><p className="text-muted-foreground text-sm capitalize">{label}</p><p className="text-2xl font-semibold">{value}{label === 'rate' ? '%' : ''}</p></CardContent></Card>)}</div>
        <div className="grid gap-6 lg:grid-cols-2">
            <Card><CardHeader><CardTitle>Nominee tallies</CardTitle></CardHeader><CardContent className="space-y-3">{detail.tallies.map((row) => <div key={row.user.name} className="flex items-center gap-3"><img src={row.user.avatar} alt="" className="size-10 rounded-full object-cover" /><div className="flex-1"><p className="font-medium">{row.user.name}</p><p className="text-muted-foreground text-xs">{[row.user.title, row.user.department].filter(Boolean).join(' · ')}</p></div><Badge>{row.votes} votes</Badge></div>)}{detail.tallies.length === 0 && <p className="text-muted-foreground">No nominations.</p>}</CardContent></Card>
            <Card><CardHeader><CardTitle>Reasons</CardTitle></CardHeader><CardContent className="space-y-4">{detail.reasons.map((row) => <blockquote key={row.id} className="border-l-2 pl-3"><p>{row.reason}</p><footer className="text-muted-foreground mt-1 text-xs">{row.nomineeName} · {row.createdAt}</footer></blockquote>)}{detail.reasons.length === 0 && <p className="text-muted-foreground">No reasons submitted.</p>}</CardContent></Card>
        </div>
    </div>;
}

AppreciationShow.layout = (page: ReactNode) => <AppLayout title="Staff Appreciation">{page}</AppLayout>;
