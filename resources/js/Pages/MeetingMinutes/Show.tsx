import { DownloadIcon, PencilIcon } from 'lucide-react';

import AppLink from '@/components/shared/AppLink';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader, PageShell } from '@/lib/page-kit';

type Minute = {
    storedName: string;
    uploadedBy?: string | null;
    updatedAt?: string | null;
    period?: { weekNumber: number; year: number; dateRange: string } | null;
    viewUrl: string;
    downloadUrl: string;
    editUrl: string;
};
type Props = { meetingMinute: Minute; canManageMinutes: boolean; backUrl: string };

export default function MeetingMinutesShow({ meetingMinute, canManageMinutes, backUrl }: Props) {
    const title = meetingMinute.period
        ? `Week ${meetingMinute.period.weekNumber} of ${meetingMinute.period.year}`
        : 'Meeting Minutes';

    return (
        <PageShell>
            <PageHeader
                title={title}
                description={meetingMinute.period?.dateRange}
                actions={
                    <>
                        <Button variant="outline" render={<AppLink href={backUrl} />}>
                            Back
                        </Button>
                        <RowActions
                            items={[
                                { label: 'Download', href: meetingMinute.downloadUrl, icon: <DownloadIcon /> },
                                {
                                    label: 'Edit',
                                    href: meetingMinute.editUrl,
                                    icon: <PencilIcon />,
                                    hidden: !canManageMinutes,
                                },
                            ]}
                        />
                    </>
                }
            />
            <Card className="shadow-none">
                <CardHeader className="border-b bg-muted/20">
                    <CardTitle className="text-base">{meetingMinute.storedName}</CardTitle>
                    <p className="text-muted-foreground text-sm">
                        Uploaded by {meetingMinute.uploadedBy ?? 'N/A'} on {meetingMinute.updatedAt}
                    </p>
                </CardHeader>
                <CardContent className="p-0">
                    <iframe src={meetingMinute.viewUrl} className="h-[800px] w-full border-0" title="Meeting minutes PDF" />
                </CardContent>
            </Card>
        </PageShell>
    );
}

MeetingMinutesShow.layout = (page: ReactNode) => <AppLayout title="Meeting Minutes">{page}</AppLayout>;
