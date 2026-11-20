import { Link, router } from '@inertiajs/react';
import { DownloadIcon, EyeIcon, FileTextIcon, PencilIcon, SearchIcon, ShieldCheckIcon, ShieldXIcon } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { notify } from '@/lib/notify';
import { cn } from '@/lib/utils';
import { PageHeader, PageShell, PaginationLinks } from '@/lib/page-kit';

type Letter = {
    id: number | string;
    reference_number: string;
    name?: string | null;
    subject?: string | null;
    department_code?: string | null;
    letter_date?: string | null;
    creator?: string | null;
    requested_by?: string | null;
    download_status?: string | null;
    is_shared?: boolean;
    can_edit?: boolean;
    can_download?: boolean;
    can_request_download?: boolean;
    can_authorize_download?: boolean;
    show_url: string;
    edit_url?: string;
    download_url?: string;
    request_download_url?: string;
    authorize_download_url?: string;
    reject_download_url?: string;
};

type Props = {
    letters: { data: Letter[]; links?: Array<{ url: string | null; label: string; active: boolean }> };
    departments: Record<string, string>;
    years: number[];
    canManage: boolean;
    canAuthorizeDownloads: boolean;
    tab: string;
    pendingAuthorizationCount: number;
    filters: { department_code?: string; year?: string | number; month?: string | number; search?: string };
    urls: { index: string; create?: string };
};

function downloadStatusBadge(status?: string | null) {
    if (status === 'pending') {
        return <Badge variant="warning">Pending</Badge>;
    }
    if (status === 'authorized') {
        return <Badge variant="success">Authorized</Badge>;
    }
    if (status === 'rejected') {
        return <Badge variant="destructive">Rejected</Badge>;
    }

    return <Badge variant="outline">No request</Badge>;
}

export default function OfficialLettersIndex({
    letters,
    departments,
    years,
    canManage,
    canAuthorizeDownloads,
    tab,
    pendingAuthorizationCount,
    filters,
    urls,
}: Props) {
    const isAuthorizations = tab === 'authorizations';
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        setSearch(filters.search ?? '');
    }, [filters.search]);

    useEffect(() => {
        const next = search.trim();
        const current = (filters.search ?? '').trim();
        if (next === current) {
            return;
        }

        const timeout = window.setTimeout(() => {
            applyFilters({ search: next || undefined });
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [search]);

    const applyFilters = (patch: Record<string, string | undefined>) => {
        router.get(
            urls.index,
            {
                tab: isAuthorizations ? 'authorizations' : undefined,
                department_code: filters.department_code || undefined,
                year: filters.year || undefined,
                month: filters.month || undefined,
                search: (filters.search ?? '').trim() || undefined,
                ...patch,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <PageShell>
            <PageHeader
                title="Official Letters"
                description="Create and track official correspondence."
                actions={
                    canManage && urls.create ? <Button render={<Link href={urls.create} />}>New Letter</Button> : null
                }
            />

            <div className="grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
                <Card className="h-fit shadow-none">
                    <CardHeader className="border-b bg-muted/20">
                        <CardTitle className="text-base">Browse</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-1 p-2">
                        <Link
                            href={urls.index}
                            className={cn(
                                'hover:bg-muted flex items-center gap-2 rounded-md px-3 py-2 text-sm',
                                !isAuthorizations && 'bg-muted font-medium',
                            )}
                        >
                            <FileTextIcon className="size-4" />
                            My letters
                        </Link>
                        {canAuthorizeDownloads && (
                            <Link
                                href={`${urls.index}?tab=authorizations`}
                                className={cn(
                                    'hover:bg-muted flex items-center justify-between gap-2 rounded-md px-3 py-2 text-sm',
                                    isAuthorizations && 'bg-muted font-medium',
                                )}
                            >
                                <span className="inline-flex items-center gap-2">
                                    <ShieldCheckIcon className="size-4" />
                                    Download authorization
                                </span>
                                {pendingAuthorizationCount > 0 && (
                                    <Badge variant="secondary">{pendingAuthorizationCount}</Badge>
                                )}
                            </Link>
                        )}
                    </CardContent>
                </Card>

                <div className="min-w-0 space-y-4">
                    {canAuthorizeDownloads && pendingAuthorizationCount > 0 && !isAuthorizations && (
                        <Card className="shadow-none">
                            <CardContent className="flex flex-wrap items-center justify-between gap-3 pt-6 text-sm">
                                <div>
                                    <strong>{pendingAuthorizationCount}</strong> letter
                                    {pendingAuthorizationCount === 1 ? '' : 's'} waiting for download authorization.
                                </div>
                                <Button size="sm" render={<Link href={`${urls.index}?tab=authorizations`} />}>
                                    Review now
                                </Button>
                            </CardContent>
                        </Card>
                    )}

                    <Card className="shadow-none">
                        <CardHeader className="flex flex-col gap-3 border-b bg-muted/20">
                            <CardTitle className="text-base">
                                {isAuthorizations ? 'Download authorizations' : 'My official letters'}
                            </CardTitle>
                            <div className="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                                <div className="relative min-w-[14rem] flex-1 sm:max-w-xs">
                                    <SearchIcon className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                    <Input
                                        className="pl-9"
                                        value={search}
                                        onChange={(event) => setSearch(event.target.value)}
                                        placeholder={
                                            isAuthorizations
                                                ? 'Search reference, subject, requester…'
                                                : 'Search reference, name, subject…'
                                        }
                                        aria-label="Search letters"
                                    />
                                </div>
                                <select
                                    className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                                    value={filters.department_code ?? ''}
                                    onChange={(e) => applyFilters({ department_code: e.target.value || undefined })}
                                >
                                    <option value="">All Departments</option>
                                    {Object.entries(departments).map(([code, label]) => (
                                        <option key={code} value={code}>
                                            {code} — {label}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                                    value={String(filters.year ?? '')}
                                    onChange={(e) => applyFilters({ year: e.target.value || undefined })}
                                >
                                    <option value="">All Years</option>
                                    {years.map((year) => (
                                        <option key={year} value={year}>
                                            {year}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    className="border-input bg-background h-9 rounded-md border px-2 text-sm"
                                    value={String(filters.month ?? '')}
                                    onChange={(e) => applyFilters({ month: e.target.value || undefined })}
                                >
                                    <option value="">All Months</option>
                                    {Array.from({ length: 12 }, (_, index) => index + 1).map((month) => (
                                        <option key={month} value={month}>
                                            {month}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-2 pt-4">
                            {letters.data.length === 0 ? (
                                <p className="text-muted-foreground py-8 text-center text-sm">No letters found.</p>
                            ) : (
                                letters.data.map((letter) => (
                                    <div
                                        key={letter.id}
                                        className="flex items-start justify-between gap-3 rounded-lg border px-3 py-2 text-sm"
                                    >
                                        <div className="min-w-0 flex-1 space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <code className="text-xs">{letter.reference_number}</code>
                                                {letter.is_shared && <Badge variant="secondary">Shared</Badge>}
                                                {downloadStatusBadge(letter.download_status)}
                                            </div>
                                            <div className="font-medium">{letter.name || letter.subject || 'Official letter'}</div>
                                            <div className="text-muted-foreground text-xs">
                                                {letter.subject}
                                                {letter.creator ? ` · ${letter.creator}` : ''}
                                                {isAuthorizations && letter.requested_by
                                                    ? ` · Requested by ${letter.requested_by}`
                                                    : ''}
                                            </div>
                                        </div>
                                        <div className="shrink-0 self-center">
                                            <RowActions
                                                items={[
                                                    { label: 'Open', href: letter.show_url, icon: <EyeIcon /> },
                                                    {
                                                        label: 'Edit',
                                                        href: letter.edit_url,
                                                        icon: <PencilIcon />,
                                                        hidden: !(letter.can_edit && letter.edit_url),
                                                    },
                                                    {
                                                        label: 'Authorize',
                                                        icon: <ShieldCheckIcon />,
                                                        hidden: !(
                                                            isAuthorizations &&
                                                            letter.can_authorize_download &&
                                                            letter.authorize_download_url
                                                        ),
                                                        onClick: () =>
                                                            router.post(
                                                                letter.authorize_download_url!,
                                                                {},
                                                                {
                                                                    onSuccess: () =>
                                                                        notify('success', 'Download authorized.'),
                                                                },
                                                            ),
                                                    },
                                                    {
                                                        label: 'Reject',
                                                        icon: <ShieldXIcon />,
                                                        destructive: true,
                                                        hidden: !(
                                                            isAuthorizations &&
                                                            letter.can_authorize_download &&
                                                            letter.reject_download_url
                                                        ),
                                                        onClick: () =>
                                                            router.post(
                                                                letter.reject_download_url!,
                                                                {},
                                                                {
                                                                    onSuccess: () =>
                                                                        notify('success', 'Request rejected.'),
                                                                },
                                                            ),
                                                    },
                                                    {
                                                        label: 'Request download',
                                                        hidden: !(
                                                            !isAuthorizations &&
                                                            letter.can_request_download &&
                                                            letter.request_download_url
                                                        ),
                                                        onClick: () =>
                                                            router.post(
                                                                letter.request_download_url!,
                                                                {},
                                                                {
                                                                    onSuccess: () =>
                                                                        notify(
                                                                            'success',
                                                                            'Download authorization requested.',
                                                                        ),
                                                                },
                                                            ),
                                                    },
                                                    {
                                                        label: 'Download PDF',
                                                        href: letter.download_url,
                                                        icon: <DownloadIcon />,
                                                        hidden: !(letter.can_download && letter.download_url),
                                                    },
                                                ]}
                                            />
                                        </div>
                                    </div>
                                ))
                            )}
                            <PaginationLinks className="mt-4" links={letters.links ?? []} />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </PageShell>
    );
}

OfficialLettersIndex.layout = (page: ReactNode) => <AppLayout title="Official Letters">{page}</AppLayout>;
