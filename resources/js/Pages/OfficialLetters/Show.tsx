import { Link, router, useForm } from '@inertiajs/react';
import { DownloadIcon, PencilIcon, ShieldCheckIcon, ShieldXIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { PageHeader, PageShell } from '@/lib/page-kit';
import { notify } from '@/lib/notify';

type Letter = {
    id: number | string;
    reference_number: string;
    name?: string | null;
    subject?: string | null;
    recipient?: string | null;
    salutation?: string | null;
    body?: string | null;
    closing?: string | null;
    department_code?: string | null;
    signatory_name?: string | null;
    signatory_title?: string | null;
    signature_mode?: string | null;
    letter_date?: string | null;
    creator?: { name?: string } | null;
    shared_users?: Array<{ id: string | number; name: string }>;
    latest_download_request?: {
        status?: string;
        request_note?: string | null;
        requester?: { name?: string } | null;
        decider?: { name?: string } | null;
    } | null;
};

type ShareableUser = { id: string | number; name: string };

export default function OfficialLettersShow({
    letter,
    shareableUsers = [],
    canShare,
    canUpdate,
    canDownload,
    canRequestDownload,
    canAuthorizeDownload,
    isSharedWithMe,
    isAuthorizerReview,
    urls,
}: {
    letter: Letter;
    shareableUsers?: ShareableUser[];
    canShare: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    canDownload: boolean;
    canRequestDownload: boolean;
    canAuthorizeDownload: boolean;
    isSharedWithMe: boolean;
    isAuthorizerReview: boolean;
    urls: {
        index: string;
        edit: string;
        download: string;
        share: string;
        destroy: string;
        requestDownload: string;
        authorizeDownload: string;
        rejectDownload: string;
    };
}) {
    const shareForm = useForm({
        shared_user_ids: (letter.shared_users ?? []).map((user) => String(user.id)),
    });
    const requestForm = useForm({ request_note: '' });
    const rejectForm = useForm({ decision_note: '' });
    const downloadStatus = letter.latest_download_request?.status ?? null;

    return (
        <PageShell>
            <PageHeader
                title={letter.name || 'Official Letter'}
                description={letter.reference_number}
                actions={
                    <>
                        <Button
                            variant="outline"
                            render={<Link href={isAuthorizerReview ? `${urls.index}?tab=authorizations` : urls.index} />}
                        >
                            Back
                        </Button>
                        <RowActions
                            items={[
                                {
                                    label: 'Authorize',
                                    icon: <ShieldCheckIcon />,
                                    hidden: !canAuthorizeDownload,
                                    onClick: () =>
                                        router.post(urls.authorizeDownload, {}, { onSuccess: () => notify('success', 'Download authorized.') }),
                                },
                                {
                                    label: 'Reject',
                                    icon: <ShieldXIcon />,
                                    destructive: true,
                                    hidden: !canAuthorizeDownload,
                                    onClick: () =>
                                        rejectForm.post(urls.rejectDownload, { onSuccess: () => notify('success', 'Request rejected.') }),
                                },
                                {
                                    label: 'Download PDF',
                                    href: urls.download,
                                    icon: <DownloadIcon />,
                                    hidden: !canDownload,
                                },
                                {
                                    label: 'Request download authorization',
                                    hidden: !(!canDownload && canRequestDownload),
                                    onClick: () =>
                                        requestForm.post(urls.requestDownload, {
                                            onSuccess: () => notify('success', 'Download authorization requested.'),
                                        }),
                                },
                                {
                                    label: 'Edit',
                                    href: urls.edit,
                                    icon: <PencilIcon />,
                                    hidden: !canUpdate,
                                },
                            ]}
                        />
                    </>
                }
            />

            {isSharedWithMe && (
                <Card>
                    <CardContent className="pt-6 text-sm">This letter was shared with you. You can view and edit it.</CardContent>
                </Card>
            )}

            {canAuthorizeDownload && (
                <Card>
                    <CardHeader>
                        <CardTitle>Download authorization needed</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        <p>
                            {letter.latest_download_request?.requester?.name ?? 'A staff member'} requested permission to download this
                            letter.
                        </p>
                        {letter.latest_download_request?.request_note && (
                            <p>
                                <strong>Note:</strong> {letter.latest_download_request.request_note}
                            </p>
                        )}
                        <label className="block">
                            <span className="mb-1 block font-medium">Reject reason (optional)</span>
                            <Textarea
                                rows={3}
                                value={rejectForm.data.decision_note}
                                onChange={(e) => rejectForm.setData('decision_note', e.target.value)}
                            />
                        </label>
                    </CardContent>
                </Card>
            )}

            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-3">
                        <CardTitle>Letter Content</CardTitle>
                        <div className="flex items-center gap-2">
                            <code className="text-xs">{letter.reference_number}</code>
                            {downloadStatus === 'pending' && <Badge>Pending</Badge>}
                            {downloadStatus === 'authorized' && <Badge>Authorized</Badge>}
                            {downloadStatus === 'rejected' && <Badge variant="destructive">Rejected</Badge>}
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm whitespace-pre-wrap">
                        <div>
                            <div className="text-muted-foreground text-xs">Recipient</div>
                            <div>{letter.recipient}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground text-xs">Salutation</div>
                            <div>{letter.salutation}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground text-xs">Subject</div>
                            <div className="font-semibold">RE: {letter.subject}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground text-xs">Body</div>
                            <div dangerouslySetInnerHTML={{ __html: letter.body || '' }} />
                        </div>
                        <div className="pt-4 text-center">
                            <div className="text-muted-foreground text-xs">{letter.closing}</div>
                            <div className="mt-3 font-semibold">{letter.signatory_name}</div>
                            <div>{letter.signatory_title}</div>
                        </div>
                    </CardContent>
                </Card>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div>
                                <div className="text-muted-foreground text-xs">Letter name</div>
                                <div>{letter.name || 'Not set'}</div>
                                {letter.name && <div className="text-muted-foreground text-xs">Portal only — not printed on the download.</div>}
                            </div>
                            <div>
                                <div className="text-muted-foreground text-xs">Department</div>
                                <div>{letter.department_code}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground text-xs">Created By</div>
                                <div>{letter.creator?.name || 'N/A'}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground text-xs">Shared With</div>
                                <div>{(letter.shared_users ?? []).map((user) => user.name).join(', ') || 'Nobody yet'}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground text-xs">Download</div>
                                <div className="flex flex-wrap gap-2">
                                    {downloadStatus === 'rejected' && <Badge variant="destructive">Rejected</Badge>}
                                    {downloadStatus === 'pending' && <Badge>Pending</Badge>}
                                    {downloadStatus === 'authorized' && <Badge>Authorized</Badge>}
                                    {!downloadStatus && <span className="text-muted-foreground">Authorization required</span>}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {canShare && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Share with Staff</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form
                                    className="space-y-3"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        shareForm.post(urls.share, { onSuccess: () => notify('success', 'Sharing updated.') });
                                    }}
                                >
                                    <div className="max-h-56 space-y-2 overflow-auto">
                                        {shareableUsers.map((user) => {
                                            const checked = shareForm.data.shared_user_ids.includes(String(user.id));
                                            return (
                                                <label key={user.id} className="flex items-center gap-2 text-sm">
                                                    <input
                                                        type="checkbox"
                                                        checked={checked}
                                                        onChange={(e) => {
                                                            shareForm.setData(
                                                                'shared_user_ids',
                                                                e.target.checked
                                                                    ? [...shareForm.data.shared_user_ids, String(user.id)]
                                                                    : shareForm.data.shared_user_ids.filter((id) => id !== String(user.id)),
                                                            );
                                                        }}
                                                    />
                                                    {user.name}
                                                </label>
                                            );
                                        })}
                                    </div>
                                    <Button type="submit" disabled={shareForm.processing} loading={shareForm.processing}>
                                        Update Sharing
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </PageShell>
    );
}

OfficialLettersShow.layout = (page: ReactNode) => <AppLayout title="Official Letter">{page}</AppLayout>;
