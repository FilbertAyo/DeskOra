import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import DocumentsShow from '@/Pages/Documents/Show';

export default function WeeklyReportActionTasks(props: Record<string, unknown>) {
    return <DocumentsShow {...props} />;
}

WeeklyReportActionTasks.layout = (page: ReactNode) => <AppLayout title="Weekly report tasks">{page}</AppLayout>;
