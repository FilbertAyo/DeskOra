import { router } from '@inertiajs/react';
import { DownloadIcon, EyeIcon, PencilIcon, UploadIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { PageHeader, PageShell } from '@/lib/page-kit';

type Minute = { showUrl: string; downloadUrl: string; editUrl: string; uploadedBy?: string | null; updatedAt?: string | null };
type Period = {
    id: string;
    weekNumber: number;
    year: number;
    dateRange: string;
    isCurrent: boolean;
    minute: Minute | null;
    uploadUrl: string;
};
type Props = {
    periods: { data: Period[]; links: Array<{ url: string | null; label: string; active: boolean }> };
    years: number[];
    selectedYear?: string;
    canManageMinutes: boolean;
    indexUrl: string;
};

export default function MeetingMinutesIndex({ periods, years, selectedYear, canManageMinutes, indexUrl }: Props) {
    return (
        <PageShell>
            <PageHeader
                title="Meeting Minutes"
                description="Weekly meeting minutes and PDF records."
                actions={
                    <select
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                        value={selectedYear ?? ''}
                        onChange={(event) => router.get(indexUrl, { year: event.target.value })}
                    >
                        <option value="">All years</option>
                        {years.map((year) => (
                            <option key={year}>{year}</option>
                        ))}
                    </select>
                }
            />
            <Card className="shadow-none">
                <CardHeader className="border-b bg-muted/20">
                    <CardTitle className="text-base">Weekly Meeting Minutes</CardTitle>
                </CardHeader>
                <CardContent className="pt-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Work Period</TableHead>
                                <TableHead>Year</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Created By</TableHead>
                                <TableHead className="w-12 text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {periods.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="py-10 text-center">
                                        No meeting minutes found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                periods.data.map((period) => (
                                    <TableRow key={period.id}>
                                        <TableCell>
                                            <strong>Week {period.weekNumber}</strong>
                                            <span className="text-muted-foreground block text-xs">{period.dateRange}</span>
                                            {period.isCurrent && <Badge variant="outline">Current Week</Badge>}
                                        </TableCell>
                                        <TableCell>{period.year}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{period.minute ? 'Available' : 'Not Available'}</Badge>
                                        </TableCell>
                                        <TableCell>{period.minute?.uploadedBy ?? '—'}</TableCell>
                                        <TableCell className="text-right">
                                            <RowActions
                                                items={
                                                    period.minute
                                                        ? [
                                                              { label: 'View', href: period.minute.showUrl, icon: <EyeIcon /> },
                                                              {
                                                                  label: 'Download',
                                                                  href: period.minute.downloadUrl,
                                                                  icon: <DownloadIcon />,
                                                              },
                                                              {
                                                                  label: 'Edit',
                                                                  href: period.minute.editUrl,
                                                                  icon: <PencilIcon />,
                                                                  hidden: !canManageMinutes,
                                                              },
                                                          ]
                                                        : [
                                                              {
                                                                  label: 'Upload',
                                                                  href: period.uploadUrl,
                                                                  icon: <UploadIcon />,
                                                                  hidden: !canManageMinutes,
                                                              },
                                                          ]
                                                }
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </PageShell>
    );
}

MeetingMinutesIndex.layout = (page: ReactNode) => <AppLayout title="Meeting Minutes">{page}</AppLayout>;
