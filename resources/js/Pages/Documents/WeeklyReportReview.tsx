import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import DocumentsShow from '@/Pages/Documents/Show';

export default function WeeklyReportReview(props: Record<string, unknown>) {
    return <DocumentsShow {...props} />;
}

WeeklyReportReview.layout = (page: ReactNode) => <AppLayout title="Weekly report review">{page}</AppLayout>;
