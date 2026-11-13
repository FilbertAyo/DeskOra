import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import DocumentList, { type DocumentRow } from '@/Pages/Documents/DocumentList';

type Props = {
    documents: { data: DocumentRow[]; links?: Array<{ url: string | null; label: string; active: boolean }> };
    createUrl: string;
};

export default function MonthlyReports({ documents, createUrl }: Props) {
    return <DocumentList title="Monthly Reports" documents={documents} createUrl={createUrl} />;
}

MonthlyReports.layout = (page: ReactNode) => <AppLayout title="Monthly Reports">{page}</AppLayout>;
