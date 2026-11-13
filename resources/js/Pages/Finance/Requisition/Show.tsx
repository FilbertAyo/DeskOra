import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { type ApprovalLog, ApprovalTimeline, type FinanceItem, RequestPreview } from '@/Pages/Finance/components';
import { PageHeader } from '@/lib/page-kit';
import { route } from '@/lib/route';

export default function Show({ request, approvalLogs }: { request: FinanceItem; approvalLogs: ApprovalLog[] }) {
    const remove = () => window.confirm('Delete this draft requisition?') && router.delete(route('requisition.destroy', { petty: request.id }));
    return (
        <>
            <PageHeader title="Requisition Details" actions={<><Button variant="outline" render={<a href={route('requisition.index')} />}>Back</Button>{['draft', 'resubmission'].includes(request.status) && <Button render={<a href={route('requisition.edit', { petty: request.id })} />}>{request.status === 'draft' ? 'Continue Draft' : 'Edit'}</Button>}{request.status === 'draft' && <Button variant="destructive" onClick={remove}>Delete Draft</Button>}</>} />
            {request.status === 'draft' && <Alert className="mb-6"><AlertTitle>Draft</AlertTitle><AlertDescription>This requisition has not been submitted for approval yet.</AlertDescription></Alert>}
            <div className="space-y-6"><RequestPreview request={request} /><ApprovalTimeline logs={approvalLogs} /></div>
        </>
    );
}

Show.layout = (page: ReactNode) => <AppLayout title="Requisition Details">{page}</AppLayout>;
