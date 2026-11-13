import { Link, router } from '@inertiajs/react';
import { DownloadIcon, EyeIcon, PencilIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader, PageShell } from '@/lib/page-kit';

type Letter = {
    id: number | string;
    reference_number: string;
    name?: string | null;
    subject?: string | null;
    department_code?: string | null;
    letter_date?: string | null;
    creator?: string | null;
    requested_by?: string | null;
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
    filters: { department_code?: string; year?: string | number; month?: string | number };
    urls: { index: string; create?: string };
};

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
    const applyFilters = (patch: Record<string, string | undefined>) => {
        router.get(
            urls.index,
            {
                tab: tab === 'authorizations' ? 'authorizations' : undefined,
                department_code: filters.department_code || undefined,
                year: filters.year || undefined,
                month: filters.month || undefined,
                ...patch,
            },
            { preserveState: true },
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

            {canAuthorizeDownloads && pendingAuthorizationCount > 0 && tab !== 'authorizations' && (
                <Card>
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

            <Card>
                <CardHeader className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex flex-wrap gap-2">
                        {canAuthorizeDownloads ? (
                            <>
                                <Button
                                    size="sm"
                                    variant={tab !== 'authorizations' ? 'default' : 'outline'}
                                    render={<Link href={urls.index} />}
                                >
                                    My Letters
                                </Button>
                                <Button
                                    size="sm"
                                    variant={tab === 'authorizations' ? 'default' : 'outline'}
                                    render={<Link href={`${urls.index}?tab=authorizations`} />}
                                >
                                    Download authorizations
                                    {pendingAuthorizationCount > 0 ? ` (${pendingAuthorizationCount})` : ''}
                                </Button>
                            </>
                        ) : (
                            <CardTitle>My Official Letters</CardTitle>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
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
                <CardContent className="space-y-2">
                    {letters.data.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No letters found.</p>
                    ) : (
                        letters.data.map((letter) => (
                            <div key={letter.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm">
                                <div className="min-w-0 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <code className="text-xs">{letter.reference_number}</code>
                                        {letter.is_shared && <Badge variant="secondary">Shared</Badge>}
                                    </div>
                                    <div className="font-medium">{letter.name || letter.subject || 'Official letter'}</div>
                                    <div className="text-muted-foreground text-xs">
                                        {letter.subject}
                                        {letter.creator ? ` · ${letter.creator}` : ''}
                                        {tab === 'authorizations' && letter.requested_by ? ` · Requested by ${letter.requested_by}` : ''}
                                    </div>
                                </div>
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
                                            label: 'Download PDF',
                                            href: letter.download_url,
                                            icon: <DownloadIcon />,
                                            hidden: !(letter.can_download && letter.download_url),
                                        },
                                    ]}
                                />
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>
        </PageShell>
    );
}

OfficialLettersIndex.layout = (page: ReactNode) => <AppLayout title="Official Letters">{page}</AppLayout>;
