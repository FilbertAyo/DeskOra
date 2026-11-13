import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type Period = { id: string; weekNumber: number; year: number; dateRange: string; status: string };
type CardRow = { period: Period; matrixCount: number; subDepartmentCount: number; isCurrent: boolean; url: string };
type Props = { year: number; availableYears: number[]; periodCards: CardRow[] };

export default function WeeklyMatrixIndex({ year, availableYears, periodCards }: Props) {
    return <div className="flex flex-col gap-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div><h1 className="text-2xl font-semibold">Weekly Matrix</h1><p className="text-muted-foreground text-sm">Select a week to manage sub-department key activity matrices.</p></div>
            <select className="border-input bg-background h-9 rounded-md border px-3" value={year} onChange={(event) => router.get(window.location.pathname, { year: event.target.value })}>
                {availableYears.map((value) => <option key={value}>{value}</option>)}
            </select>
        </div>
        {periodCards.length === 0 ? <Card><CardContent className="text-muted-foreground py-10 text-center">No work periods found for {year}.</CardContent></Card> :
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">{periodCards.map((row) =>
                <a key={row.period.id} href={row.url}><Card className={row.isCurrent ? 'border-primary' : ''}>
                    <CardHeader><CardTitle>Week {row.period.weekNumber}</CardTitle><CardDescription>{row.period.dateRange}</CardDescription></CardHeader>
                    <CardContent className="space-y-2"><Badge variant="outline">{row.isCurrent ? 'Current' : row.period.status}</Badge>
                        <p className="text-muted-foreground text-sm">{row.subDepartmentCount ? `${row.matrixCount} / ${row.subDepartmentCount} sub-depts` : 'No sub-depts'}</p>
                    </CardContent>
                </Card></a>)}</div>}
    </div>;
}

WeeklyMatrixIndex.layout = (page: ReactNode) => <AppLayout title="Weekly Matrix">{page}</AppLayout>;
