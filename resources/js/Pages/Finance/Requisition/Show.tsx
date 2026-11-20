import { router } from '@inertiajs/react';

import { PencilIcon, Trash2Icon } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import BackButton from '@/components/shared/BackButton';
import RowActions from '@/components/shared/RowActions';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    ApprovalTimelineButton,
    type ApprovalLog,
    type FinanceItem,
    RequestPreview,
} from '@/Pages/Finance/components';
import { PageHeader, PageShell } from '@/lib/page-kit';
import { route } from '@/lib/route';

export default function Show({ request, approvalLogs }: { request: FinanceItem; approvalLogs: ApprovalLog[] }) {
    const remove = () =>
        window.confirm('Delete this draft requisition?') &&
        router.delete(route('requisition.destroy', { petty: request.id }));

    return (
        <PageShell>
            <PageHeader
                title="Requisition Details"
                actions={
                    <>
                        <BackButton href={route('requisition.index')} />
                        <ApprovalTimelineButton logs={approvalLogs} />
                        <RowActions
                            items={[
                                {
                                    label: request.status === 'draft' ? 'Continue Draft' : 'Edit',
                                    href: route('requisition.edit', { petty: request.id }),
                                    icon: <PencilIcon />,
                                    hidden: !['draft', 'resubmission'].includes(request.status),
                                },
                                {
                                    label: 'Delete Draft',
                                    icon: <Trash2Icon />,
                                    destructive: true,
                                    hidden: request.status !== 'draft',
                                    onClick: remove,
                                },
                            ]}
                        />
                    </>
                }
            />
            {request.status === 'draft' && (
                <Alert>
                    <AlertTitle>Draft</AlertTitle>
                    <AlertDescription>This requisition has not been submitted for approval yet.</AlertDescription>
                </Alert>
            )}
            <RequestPreview request={request} />
        </PageShell>
    );
}

Show.layout = (page: ReactNode) => <AppLayout title="Requisition Details">{page}</AppLayout>;
