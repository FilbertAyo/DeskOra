import { Link, router, useForm } from '@inertiajs/react';
import {
    ArchiveIcon,
    CalendarClockIcon,
    CalendarDaysIcon,
    ClipboardListIcon,
    DownloadIcon,
    EyeIcon,
    InboxIcon,
    MapPinIcon,
    PencilIcon,
    PlusIcon,
    SearchIcon,
    UploadIcon,
    UsersIcon,
    VideoIcon,
    ZapIcon,
} from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import MeetingComposeDialog, { type ComposeMode } from '@/Pages/Meetings/MeetingComposeDialog';
import RowActions from '@/components/shared/RowActions';
import type { StaffUser } from '@/components/shared/StaffPicker';
import { Badge, statusBadgeVariant } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { EmptyState, PageHeader, PageShell, PaginationLinks } from '@/lib/page-kit';
import { cn } from '@/lib/utils';

type MeetingCard = {
    id: string;
    title: string;
    when_label: string;
    day_key: string;
    day_label: string;
    location?: string | null;
    join_url?: string | null;
    meeting_type: string;
    status: string;
    is_instant?: boolean;
    is_live?: boolean;
    organizer?: string | null;
    is_organizer: boolean;
    my_response?: string | null;
    rsvp: { accepted: number; declined: number; pending: number; total: number };
    show_url: string;
    call_url?: string;
};

type ArchivePeriod = {
    id: string;
    weekNumber: number;
    year: number;
    dateRange: string;
    isCurrent: boolean;
    minute: {
        showUrl: string;
        downloadUrl: string;
        editUrl: string;
        uploadedBy?: string | null;
        updatedAt?: string | null;
    } | null;
};

type Props = {
    tab: string;
    meetings: { data: MeetingCard[]; links?: Array<{ url: string | null; label: string; active: boolean }> };
    archive?: { data: ArchivePeriod[]; links?: Array<{ url: string | null; label: string; active: boolean }> };
    years?: number[];
    selectedYear?: string | number;
    pendingRsvpCount: number;
    canManageMinutes: boolean;
    staffUsers?: StaffUser[];
    workPeriods?: Array<{ id: string; label: string }>;
    urls: { index: string; store: string; archive: string; minutesStore: string };
};

const TABS = [
    { key: 'upcoming', label: 'Upcoming', icon: CalendarDaysIcon },
    { key: 'organized', label: 'Organized by me', icon: ClipboardListIcon },
    { key: 'awaiting', label: 'Awaiting response', icon: InboxIcon },
    { key: 'past', label: 'Past', icon: CalendarClockIcon },
    { key: 'archive', label: 'Weekly minutes archive', icon: ArchiveIcon },
] as const;

function responseBadge(response?: string | null) {
    if (!response || response === 'pending') {
        return <Badge variant="warning">Pending</Badge>;
    }
    if (response === 'accepted') {
        return <Badge variant="success">Accepted</Badge>;
    }
    if (response === 'declined') {
        return <Badge variant="destructive">Declined</Badge>;
    }
    return <Badge variant="outline">{response}</Badge>;
}

export default function MeetingsIndex({
    tab,
    meetings,
    archive,
    years = [],
    selectedYear,
    pendingRsvpCount,
    canManageMinutes,
    staffUsers = [],
    workPeriods = [],
    urls,
}: Props) {
    const isArchive = tab === 'archive';
    const [search, setSearch] = useState('');
    const [uploadPeriod, setUploadPeriod] = useState<ArchivePeriod | null>(null);
    const [composeMode, setComposeMode] = useState<ComposeMode | null>(null);
    const form = useForm<{ period_id: string; file: File | null }>({
        period_id: '',
        file: null,
    });

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const compose = params.get('compose');
        if (compose === 'schedule' || compose === 'instant') {
            setComposeMode(compose === 'instant' ? 'instant' : 'schedule');
            params.delete('compose');
            const next = `${window.location.pathname}${params.toString() ? `?${params}` : ''}`;
            window.history.replaceState({}, '', next);
        }
    }, []);

    const grouped = useMemo(() => {
        const map = new Map<string, { label: string; items: MeetingCard[] }>();
        for (const meeting of meetings.data) {
            const existing = map.get(meeting.day_key);
            if (existing) {
                existing.items.push(meeting);
            } else {
                map.set(meeting.day_key, { label: meeting.day_label, items: [meeting] });
            }
        }
        return Array.from(map.entries());
    }, [meetings.data]);

    const filteredArchive = useMemo(() => {
        const rows = archive?.data ?? [];
        const query = search.trim().toLowerCase();
        if (!query) {
            return rows;
        }

        return rows.filter((period) => {
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
    }, [archive?.data, search]);

    const openUpload = (period: ArchivePeriod) => {
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
        form.post(urls.minutesStore, {
            forceFormData: true,
            onSuccess: () => {
                notify('success', 'Meeting minutes uploaded.');
                closeUpload();
            },
        });
    };

    const tabHref = (key: string) => {
        if (key === 'upcoming') {
            return urls.index;
        }
        if (key === 'archive') {
            return urls.archive;
        }
        return `${urls.index}?tab=${key}`;
    };

    return (
        <PageShell>
            <PageHeader
                title="In-house meetings"
                description="Start an instant voice call with colleagues, or schedule one — all inside the portal."
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Button onClick={() => setComposeMode('instant')}>
                            <ZapIcon className="size-4" />
                            Start now
                        </Button>
                        <Button variant="outline" onClick={() => setComposeMode('schedule')}>
                            <PlusIcon className="size-4" />
                            Schedule meeting
                        </Button>
                    </div>
                }
            />

            <div className="grid gap-4 lg:grid-cols-[240px_minmax(0,1fr)]">
                <Card className="h-fit shadow-none">
                    <CardHeader className="border-b bg-muted/20">
                        <CardTitle className="text-base">Browse</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-1 p-2">
                        {TABS.map((item) => {
                            const Icon = item.icon;
                            const active = tab === item.key;
                            return (
                                <Link
                                    key={item.key}
                                    href={tabHref(item.key)}
                                    className={cn(
                                        'hover:bg-muted flex items-center justify-between gap-2 rounded-md px-3 py-2 text-sm',
                                        active && 'bg-muted font-medium',
                                    )}
                                >
                                    <span className="inline-flex min-w-0 items-center gap-2">
                                        <Icon className="size-4 shrink-0" />
                                        <span className="truncate">{item.label}</span>
                                    </span>
                                    {item.key === 'awaiting' && pendingRsvpCount > 0 ? (
                                        <Badge variant="secondary">{pendingRsvpCount}</Badge>
                                    ) : null}
                                </Link>
                            );
                        })}
                    </CardContent>
                </Card>

                <div className="min-w-0 space-y-4">
                    {isArchive ? (
                        <Card className="shadow-none">
                            <CardHeader className="border-b bg-muted/20">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="relative max-w-sm flex-1">
                                        <SearchIcon className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                        <Input
                                            className="pl-9"
                                            value={search}
                                            onChange={(event) => setSearch(event.target.value)}
                                            placeholder="Search week, year, status, or uploader…"
                                            aria-label="Search weekly minutes"
                                        />
                                    </div>
                                    <select
                                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                        value={selectedYear ?? ''}
                                        onChange={(event) =>
                                            router.get(urls.archive, { year: event.target.value || undefined }, { preserveState: true })
                                        }
                                    >
                                        <option value="">All years</option>
                                        {years.map((year) => (
                                            <option key={year} value={year}>
                                                {year}
                                            </option>
                                        ))}
                                    </select>
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
                                        {filteredArchive.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={5} className="py-10 text-center">
                                                    No meeting minutes found.
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            filteredArchive.map((period) => (
                                                <TableRow key={period.id}>
                                                    <TableCell>
                                                        <strong>Week {period.weekNumber}</strong>
                                                        <span className="text-muted-foreground block text-xs">{period.dateRange}</span>
                                                        {period.isCurrent ? <Badge variant="outline">Current Week</Badge> : null}
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
                                <PaginationLinks links={archive?.links ?? []} className="mt-4" />
                            </CardContent>
                        </Card>
                    ) : meetings.data.length === 0 ? (
                        <EmptyState
                            title={tab === 'awaiting' ? 'No pending invitations' : 'No meetings here'}
                            description={
                                tab === 'organized'
                                    ? 'Schedule a meeting to invite colleagues.'
                                    : 'Your upcoming meetings and invitations will appear here.'
                            }
                            action={
                                <div className="flex flex-wrap gap-2">
                                    <Button variant="outline" onClick={() => setComposeMode('instant')}>
                                        <ZapIcon className="size-4" />
                                        Start now
                                    </Button>
                                    <Button onClick={() => setComposeMode('schedule')}>
                                        <PlusIcon className="size-4" />
                                        Schedule meeting
                                    </Button>
                                </div>
                            }
                        />
                    ) : (
                        <div className="space-y-6">
                            {grouped.map(([dayKey, group]) => (
                                <section key={dayKey} className="space-y-3">
                                    <h2 className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{group.label}</h2>
                                    <div className="space-y-3">
                                        {group.items.map((meeting) => (
                                            <Link
                                                key={meeting.id}
                                                href={meeting.is_live && meeting.call_url ? meeting.call_url : meeting.show_url}
                                                className="border-border/70 bg-card hover:border-primary/30 block rounded-xl border p-4 shadow-none transition-colors"
                                            >
                                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                    <div className="min-w-0 space-y-2">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <h3 className="truncate text-base font-semibold">{meeting.title}</h3>
                                                            <Badge variant={statusBadgeVariant(meeting.status)}>{meeting.status}</Badge>
                                                            {meeting.is_live ? <Badge variant="success">Live now</Badge> : null}
                                                            {meeting.is_instant && !meeting.is_live ? <Badge variant="info">Instant</Badge> : null}
                                                            {meeting.meeting_type === 'weekly' ? (
                                                                <Badge variant="info">Weekly</Badge>
                                                            ) : null}
                                                        </div>
                                                        <p className="text-muted-foreground text-sm">{meeting.when_label}</p>
                                                        <div className="text-muted-foreground flex flex-wrap gap-3 text-xs">
                                                            {meeting.location ? (
                                                                <span className="inline-flex items-center gap-1">
                                                                    <MapPinIcon className="size-3.5" />
                                                                    {meeting.location}
                                                                </span>
                                                            ) : null}
                                                            <span className="inline-flex items-center gap-1">
                                                                <VideoIcon className="size-3.5" />
                                                                Portal voice call
                                                            </span>
                                                            <span className="inline-flex items-center gap-1">
                                                                <UsersIcon className="size-3.5" />
                                                                {meeting.rsvp.accepted}/{meeting.rsvp.total} accepted
                                                            </span>
                                                            {meeting.organizer ? <span>Organizer: {meeting.organizer}</span> : null}
                                                        </div>
                                                    </div>
                                                    <div className="flex shrink-0 flex-col items-start gap-2 sm:items-end">
                                                        {meeting.is_organizer ? (
                                                            <Badge variant="outline">Organizer</Badge>
                                                        ) : (
                                                            responseBadge(meeting.my_response)
                                                        )}
                                                    </div>
                                                </div>
                                            </Link>
                                        ))}
                                    </div>
                                </section>
                            ))}
                            <PaginationLinks links={meetings.links ?? []} />
                        </div>
                    )}
                </div>
            </div>

            <MeetingComposeDialog
                open={composeMode !== null}
                mode={composeMode ?? 'schedule'}
                onOpenChange={(open) => {
                    if (!open) {
                        setComposeMode(null);
                    }
                }}
                staffUsers={staffUsers}
                workPeriods={workPeriods}
                storeUrl={urls.store}
            />

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

MeetingsIndex.layout = (page: ReactNode) => <AppLayout title="Meetings">{page}</AppLayout>;
