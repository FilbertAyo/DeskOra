import { router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import { type ApprovalLog, ApprovalTimeline, type FinanceItem, PayButton, RequestPreview } from '@/Pages/Finance/components';
import { notify } from '@/lib/notify';
import { PageHeader } from '@/lib/page-kit';
import { route } from '@/lib/route';

export default function Show({ request, approvalLogs, returnStage, returnType, canActOnRequest, hasSignature, currentStage, currentStageLabel, approvalRoute }: { request: FinanceItem; approvalLogs: ApprovalLog[]; amountInWords: string; approval: string | null; returnStage: string; returnType: string | null; canActOnRequest: boolean; hasSignature: boolean; currentStage: string; currentStageLabel: string; approvalRoute: string }) {
    const [open, setOpen] = useState(false);
    const actionLabel = currentStage === 'review' ? 'Review' : currentStage === 'approve' ? 'Approve' : currentStage === 'authorize' ? 'Authorize' : 'Take Action';
    const form = useForm({ action: 'rejected', comment: '' });
    const reject = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('petty.reject', { petty: request.id }), { onError: () => notify('error', 'Choose an outcome and provide a comment.') });
    };
    const backUrl = route('petty.list', { stage: returnStage, ...(returnType ? { type: returnType } : {}) });
    return (
        <>
            <PageHeader title="Request Details" actions={<><Button variant="outline" render={<a href={backUrl} />}>Back</Button>{canActOnRequest && hasSignature && <Dialog open={open} onOpenChange={setOpen}><DialogTrigger render={<Button />}>{actionLabel} Request</DialogTrigger><DialogContent><DialogHeader><DialogTitle>Confirm action</DialogTitle><DialogDescription>{actionLabel} this {request.requestType.toLowerCase()} request, or return it with feedback.</DialogDescription></DialogHeader><div className="space-y-4"><Button className="w-full" data-action="confirm" onClick={() => router.post(approvalRoute)}>Confirm {actionLabel}</Button><form onSubmit={reject} className="space-y-4 border-t pt-4"><RadioGroup value={form.data.action} onValueChange={(value) => form.setData('action', value ?? 'rejected')}><div className="flex items-center gap-2"><RadioGroupItem value="rejected" id="rejected" /><Label htmlFor="rejected">Reject permanently</Label></div><div className="flex items-center gap-2"><RadioGroupItem value="resubmission" id="resubmission" /><Label htmlFor="resubmission">Request resubmission</Label></div></RadioGroup><Textarea name="comment" value={form.data.comment} onChange={(event) => form.setData('comment', event.target.value)} placeholder="Explain the reason or what needs to change…" required /><Button type="submit" variant="destructive" disabled={form.processing} loading={form.processing}>Submit feedback</Button></form></div></DialogContent></Dialog>}{request.canDownload && <Button variant="destructive" render={<a href={route('petty.requisition.download', { petty: request.id })} />}>Download</Button>}{request.canPay && <PayButton id={request.id} />}</>} />
            {canActOnRequest && !hasSignature && <Alert className="mb-6"><AlertTitle>Signature required to {actionLabel.toLowerCase()} request</AlertTitle><AlertDescription className="space-y-2"><p>Upload your signature in Profile Settings before you can review, approve, or authorize requests.</p><Button render={<a href={route('profile.edit')} />}>Upload Signature</Button></AlertDescription></Alert>}
            {!canActOnRequest && currentStage !== 'completed' && <Alert className="mb-6"><AlertTitle>View only</AlertTitle><AlertDescription>This request is currently awaiting <strong>{currentStageLabel.toLowerCase()}</strong>. You can review the details below, but you cannot take action at this stage.</AlertDescription></Alert>}
            <div className="space-y-6"><RequestPreview request={request} /><ApprovalTimeline logs={approvalLogs} /></div>
        </>
    );
}

Show.layout = (page: ReactNode) => <AppLayout title="Request Details">{page}</AppLayout>;
