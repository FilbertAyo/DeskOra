import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Minute = { storedName: string; uploadedBy?: string | null; updatedAt?: string | null; period?: { weekNumber: number; year: number; dateRange: string } | null; viewUrl: string; downloadUrl: string; editUrl: string };
type Props = { meetingMinute: Minute; canManageMinutes: boolean; backUrl: string };

export default function MeetingMinutesShow({ meetingMinute, canManageMinutes, backUrl }: Props) {
    const title = meetingMinute.period ? `Week ${meetingMinute.period.weekNumber} of ${meetingMinute.period.year}` : 'Meeting Minutes';
    return <div className="flex flex-col gap-6"><div className="flex flex-wrap items-center justify-between gap-3"><div><h1 className="text-2xl font-semibold">{title}</h1><p className="text-muted-foreground">{meetingMinute.period?.dateRange}</p></div>
        <div className="flex gap-2"><Button variant="outline" render={<a href={backUrl} />}>Back</Button><Button variant="outline" render={<a href={meetingMinute.downloadUrl} />}>Download</Button>{canManageMinutes && <Button render={<a href={meetingMinute.editUrl} />}>Edit</Button>}</div></div>
        <Card><CardHeader><CardTitle>{meetingMinute.storedName}</CardTitle><p className="text-muted-foreground text-sm">Uploaded by {meetingMinute.uploadedBy ?? 'N/A'} on {meetingMinute.updatedAt}</p></CardHeader><CardContent className="p-0"><iframe src={meetingMinute.viewUrl} className="h-[800px] w-full border-0" title="Meeting minutes PDF" /></CardContent></Card>
    </div>;
}

MeetingMinutesShow.layout = (page: ReactNode) => <AppLayout title="Meeting Minutes">{page}</AppLayout>;
