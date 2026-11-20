import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import BackButton from '@/components/shared/BackButton';
import { PageHeader, PageShell } from '@/lib/page-kit';

import LetterForm, { type LetterSignatory, type OfficialLetterInput } from './Form';

type Props = {
    letter?: OfficialLetterInput & { id?: string | number; show_url?: string };
    departments?: Record<string, string>;
    salutations?: string[];
    closings?: string[];
    signatories?: LetterSignatory[] | Record<string, LetterSignatory>;
    updateUrl?: string;
    indexUrl?: string;
};

export default function OfficialLettersEdit({
    letter = {},
    departments = {},
    salutations = [],
    closings = [],
    signatories = [],
    updateUrl = '',
    indexUrl = '/official-letters',
}: Props) {
    const cancelUrl = letter.show_url ?? (letter.id ? `/official-letters/${letter.id}` : indexUrl);

    return (
        <PageShell>
            <PageHeader
                title="Edit Official Letter"
                actions={<BackButton href={indexUrl} />}
            />
            <LetterForm
                letter={letter}
                departments={departments}
                salutations={salutations}
                closings={closings}
                signatories={Array.isArray(signatories) ? signatories : Object.values(signatories)}
                actionUrl={updateUrl}
                method="put"
                cancelUrl={cancelUrl}
            />
        </PageShell>
    );
}

OfficialLettersEdit.layout = (page: ReactNode) => <AppLayout title="Edit Official Letter">{page}</AppLayout>;
