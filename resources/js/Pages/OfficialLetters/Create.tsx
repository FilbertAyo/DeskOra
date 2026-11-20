import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import BackButton from '@/components/shared/BackButton';
import { PageHeader, PageShell } from '@/lib/page-kit';

import LetterForm, { type LetterSignatory } from './Form';

type Props = {
    departments?: Record<string, string>;
    salutations?: string[];
    closings?: string[];
    signatories?: LetterSignatory[] | Record<string, LetterSignatory>;
    storeUrl?: string;
    indexUrl?: string;
};

export default function OfficialLettersCreate({
    departments = {},
    salutations = [],
    closings = [],
    signatories = [],
    storeUrl = '/official-letters',
    indexUrl = '/official-letters',
}: Props) {
    return (
        <PageShell>
            <PageHeader
                title="New Official Letter"
                actions={<BackButton href={indexUrl} />}
            />
            <LetterForm
                departments={departments}
                salutations={salutations}
                closings={closings}
                signatories={asList(signatories)}
                actionUrl={storeUrl}
                method="post"
                cancelUrl={indexUrl}
            />
        </PageShell>
    );
}

function asList(value: LetterSignatory[] | Record<string, LetterSignatory>): LetterSignatory[] {
    return Array.isArray(value) ? value : Object.values(value);
}

OfficialLettersCreate.layout = (page: ReactNode) => <AppLayout title="New Official Letter">{page}</AppLayout>;
