import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

export type Stats = { eligible: number; responded: number; rate: number; nominated: number; skipped: number };
export type Detail = {
    stats: Stats;
    tallies: Array<{ user: { name: string; title?: string | null; department?: string | null; avatar: string }; votes: number }>;
    reasons: Array<{ id: string; nomineeName: string; reason: string; createdAt: string | null }>;
};

export default function PeriodDetail({ detail }: { detail: Detail }) {
    const maximum = Math.max(1, ...detail.tallies.map((row) => row.votes));
    return <div className="space-y-6">
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Stat label="Eligible staff" value={detail.stats.eligible} />
            <Stat label="Responded" value={`${detail.stats.responded} / ${detail.stats.eligible}`} suffix={`${detail.stats.rate}% participation`} />
            <Stat label="Nominations" value={detail.stats.nominated} />
            <Stat label="Skipped" value={detail.stats.skipped} />
        </div>
        <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
            <Card><CardHeader><CardTitle>Most appreciated colleagues</CardTitle></CardHeader><CardContent><Table><TableHeader><TableRow><TableHead>#</TableHead><TableHead>Colleague</TableHead><TableHead>Nominations</TableHead></TableRow></TableHeader><TableBody>{detail.tallies.map((row, index) => <TableRow key={`${row.user.name}-${index}`}><TableCell>{index + 1}</TableCell><TableCell><div className="flex items-center gap-2"><img src={row.user.avatar} alt="" className="size-9 rounded-full object-cover" /><div><p className="font-medium">{row.user.name}</p><p className="text-muted-foreground text-xs">{[row.user.title, row.user.department].filter(Boolean).join(' • ')}</p></div></div></TableCell><TableCell><div className="flex items-center gap-2"><div className="bg-muted h-2 flex-1 rounded"><div className="bg-primary h-2 rounded" style={{ width: `${(row.votes / maximum) * 100}%` }} /></div><Badge variant="outline">{row.votes}</Badge></div></TableCell></TableRow>)}{detail.tallies.length === 0 && <TableRow><TableCell colSpan={3} className="py-8 text-center text-muted-foreground">No nominations recorded yet.</TableCell></TableRow>}</TableBody></Table></CardContent></Card>
            <Card><CardHeader><CardTitle>Anonymous reasons</CardTitle></CardHeader><CardContent className="max-h-[520px] space-y-2 overflow-y-auto">{detail.reasons.map((reason) => <div key={reason.id} className="bg-muted rounded-lg p-3"><div className="flex justify-between gap-2 text-sm font-medium"><span>{reason.nomineeName}</span><span className="text-muted-foreground">{reason.createdAt}</span></div><p className="mt-1 whitespace-pre-wrap text-sm">{reason.reason}</p></div>)}{detail.reasons.length === 0 && <p className="text-muted-foreground py-8 text-center">No written reasons submitted.</p>}</CardContent></Card>
        </div>
    </div>;
}

function Stat({ label, value, suffix }: { label: string; value: string | number; suffix?: string }) {
    return <Card><CardContent className="pt-6"><p className="text-muted-foreground text-xs uppercase">{label}</p><p className="text-2xl font-semibold">{value}</p>{suffix && <Badge variant="outline" className="mt-2">{suffix}</Badge>}</CardContent></Card>;
}
