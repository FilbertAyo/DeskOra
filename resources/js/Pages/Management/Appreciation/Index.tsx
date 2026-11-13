import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Stats = { eligible: number; responded: number; nominated: number; skipped: number; rate: number };
type Period = { id: string; label: string; opensAt: string; closesAt: string; isWindowOpen: boolean; showUrl: string };
type Detail = { stats: Stats; tallies: Tally[]; reasons: Reason[] };
type Tally = { user: { name: string; title: string | null; department: string | null; avatar: string }; votes: number };
type Reason = { id: string; nomineeName: string; reason: string; createdAt: string | null };
type Props = { periods: Array<Period & { stats: Stats }>; currentPeriod: Period | null; previousPeriod: Period | null; currentDetail: Detail | null; previousStats: Stats | null };

export default function AppreciationIndex({ periods, currentPeriod, previousPeriod, currentDetail, previousStats }: Props) {
    return <div className="flex flex-col gap-6">
        <div><h1 className="text-2xl font-semibold">Staff appreciation</h1><p className="text-muted-foreground text-sm">Participation and nomination results.</p></div>
        {currentPeriod && currentDetail && <><div><h2 className="text-lg font-semibold">{currentPeriod.label} {currentPeriod.isWindowOpen && <Badge className="ml-2">Open</Badge>}</h2><p className="text-muted-foreground text-sm">{currentPeriod.opensAt} – {currentPeriod.closesAt}</p></div><StatsGrid stats={currentDetail.stats} />
            <div className="grid gap-6 lg:grid-cols-2"><TalliesCard tallies={currentDetail.tallies} /><ReasonsCard reasons={currentDetail.reasons} /></div></>}
        {previousPeriod && previousStats && <Card><CardHeader><CardTitle>Previous period: <a href={previousPeriod.showUrl}>{previousPeriod.label}</a></CardTitle></CardHeader><CardContent><p>{previousStats.rate}% participation · {previousStats.responded} of {previousStats.eligible} responded</p></CardContent></Card>}
        <Card><CardHeader><CardTitle>Period history</CardTitle></CardHeader><CardContent className="divide-y">{periods.map((period) => <a key={period.id} href={period.showUrl} className="flex items-center justify-between gap-3 py-3"><span><span className="font-medium">{period.label}</span><span className="text-muted-foreground block text-sm">{period.opensAt} – {period.closesAt}</span></span><span>{period.stats.rate}%</span></a>)}</CardContent></Card>
    </div>;
}

function StatsGrid({ stats }: { stats: Stats }) { return <div className="grid gap-3 sm:grid-cols-5">{Object.entries(stats).map(([label, value]) => <Card key={label}><CardContent className="pt-6"><p className="text-muted-foreground text-sm capitalize">{label}</p><p className="text-2xl font-semibold">{value}{label === 'rate' ? '%' : ''}</p></CardContent></Card>)}</div>; }
function TalliesCard({ tallies }: { tallies: Tally[] }) { return <Card><CardHeader><CardTitle>Nominee tallies</CardTitle></CardHeader><CardContent className="space-y-3">{tallies.map((row) => <div key={row.user.name} className="flex items-center gap-3"><img src={row.user.avatar} alt="" className="size-10 rounded-full object-cover" /><div className="flex-1"><p className="font-medium">{row.user.name}</p><p className="text-muted-foreground text-xs">{[row.user.title, row.user.department].filter(Boolean).join(' · ')}</p></div><Badge>{row.votes} votes</Badge></div>)}{tallies.length === 0 && <p className="text-muted-foreground">No nominations yet.</p>}</CardContent></Card>; }
function ReasonsCard({ reasons }: { reasons: Reason[] }) { return <Card><CardHeader><CardTitle>Appreciation reasons</CardTitle></CardHeader><CardContent className="space-y-4">{reasons.map((row) => <blockquote key={row.id} className="border-l-2 pl-3"><p>{row.reason}</p><footer className="text-muted-foreground mt-1 text-xs">{row.nomineeName} · {row.createdAt}</footer></blockquote>)}{reasons.length === 0 && <p className="text-muted-foreground">No reasons submitted yet.</p>}</CardContent></Card>; }

AppreciationIndex.layout = (page: ReactNode) => <AppLayout title="Staff Appreciation">{page}</AppLayout>;
