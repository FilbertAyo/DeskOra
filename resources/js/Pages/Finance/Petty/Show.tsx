import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import BackButton from '@/components/shared/BackButton';
import { type ApprovalLog, ApprovalTimeline, type FinanceItem, RequestPreview } from '@/Pages/Finance/components';
import { PageHeader } from '@/lib/page-kit';
import { route } from '@/lib/route';

export default function Show({ request, approvalLogs }: { request: FinanceItem; amountInWords: string; approvalLogs: ApprovalLog[] }) {
    return (
        <>
            <PageHeader title="Request Details" actions={<BackButton href={route('petty.index')} />} />
            <div className="space-y-6"><RequestPreview request={request} /><ApprovalTimeline logs={approvalLogs} /></div>
        </>
    );
}

Show.layout = (page: ReactNode) => <AppLayout title="Request Details">{page}</AppLayout>;
