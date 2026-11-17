import { router, useForm } from '@inertiajs/react';
import { DownloadIcon, EyeIcon, PencilIcon, SearchIcon, UploadIcon } from 'lucide-react';
import { useMemo, useState, type FormEvent, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Badge, statusBadgeVariant } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { notify } from '@/lib/notify';
import { PageHeader, PageShell } from '@/lib/page-kit';

type Minute = {
    showUrl: string;
    downloadUrl: string;
    editUrl: string;
    uploadedBy?: string | null;
    updatedAt?: string | null;
};

type Period = {
    id: string;
    weekNumber: number;
    year: number;
    dateRange: string;
    isCurrent: boolean;
    minute: Minute | null;
};

type Props = {
    periods: { data: Period[]; links: Array<{ url: string | null; label: string; active: boolean }> };
    years: number[];
    selectedYear?: string;
    canManageMinutes: boolean;
    indexUrl: string;
    storeUrl: string;
};

export default function MeetingMinutesIndex({
    periods,
    years,
    selectedYear,
    canManageMinutes,
    indexUrl,
    storeUrl,
}: Props) {
    const [search, setSearch] = useState('');
    const [uploadPeriod, setUploadPeriod] = useState<Period | null>(null);
    const form = useForm<{ period_id: string; file: File | null }>({
        period_id: '',
        file: null,
    });

    const filteredPeriods = useMemo(() => {
        const query = search.trim().toLowerCase();
        if (!query) {
            return periods.data;
        }

        return periods.data.filter((period) => {
            const haystack = [
                `week ${period.weekNumber}`,
                String(period.weekNumber),
                String(period.year),
                period.dateRange,
                period.minute ? 'available' : 'not available',
                period.minute?.uploadedBy ?? '',
                period.isCurrent ? 'current week' : '',
            ]
                .join(' ')
                .toLowerCase();

            return haystack.includes(query);
        });
    }, [periods.data, search]);

    const openUpload = (period: Period) => {
        form.setData({ period_id: period.id, file: null });
        form.clearErrors();
        setUploadPeriod(period);
    };

    const closeUpload = () => {
        setUploadPeriod(null);
        form.reset();
        form.clearErrors();
    };

    const submitUpload = (event: FormEvent) => {
        event.preventDefault();
        form.post(storeUrl, {
            forceFormData: true,
            onSuccess: () => {
                notify('success', 'Meeting minutes uploaded.');
                closeUpload();
            },
        });
    };

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
                    <div className="relative max-w-sm">
                        <SearchIcon className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            className="pl-9"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search week, year, status, or uploader…"
                            aria-label="Search meeting minutes"
                        />
                    </div>
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
                            {filteredPeriods.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="py-10 text-center">
                                        No meeting minutes found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                filteredPeriods.map((period) => (
                                    <TableRow key={period.id}>
                                        <TableCell>
                                            <strong>Week {period.weekNumber}</strong>
                                            <span className="text-muted-foreground block text-xs">{period.dateRange}</span>
                                            {period.isCurrent && <Badge variant="outline">Current Week</Badge>}
                                        </TableCell>
                                        <TableCell>{period.year}</TableCell>
                                        <TableCell>
                                            <Badge variant={statusBadgeVariant(period.minute ? 'available' : 'not available')}>
                                                {period.minute ? 'Available' : 'Not Available'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{period.minute?.uploadedBy ?? '—'}</TableCell>
                                        <TableCell className="text-right">
                                            <RowActions
                                                items={
                                                    period.minute
                                                        ? [
                                                              {
                                                                  label: 'View',
                                                                  href: period.minute.showUrl,
                                                                  icon: <EyeIcon />,
                                                              },
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
                                                                  icon: <UploadIcon />,
                                                                  hidden: !canManageMinutes,
                                                                  onClick: () => openUpload(period),
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

            <Dialog
                open={uploadPeriod !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        closeUpload();
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Upload meeting minutes</DialogTitle>
                        <DialogDescription>
                            {uploadPeriod
                                ? `Week ${uploadPeriod.weekNumber} of ${uploadPeriod.year} · ${uploadPeriod.dateRange}`
                                : 'Choose a PDF for this work period.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitUpload}>
                        <Field>
                            <FieldLabel htmlFor="meeting-minutes-file">PDF file</FieldLabel>
                            <Input
                                id="meeting-minutes-file"
                                name="file"
                                type="file"
                                accept="application/pdf,.pdf"
                                required
                                onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)}
                            />
                            <FieldError>{form.errors.file}</FieldError>
                            {form.errors.period_id ? <FieldError>{form.errors.period_id}</FieldError> : null}
                            <p className="text-muted-foreground text-xs">PDF only, maximum 10 MB.</p>
                        </Field>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeUpload}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing} loading={form.processing}>
                                Upload minutes
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </PageShell>
    );
}

MeetingMinutesIndex.layout = (page: ReactNode) => <AppLayout title="Meeting Minutes">{page}</AppLayout>;
