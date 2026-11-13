import { router, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PageHeader } from '@/lib/page-kit';
import { notify } from '@/lib/notify';

type Report = { id: string; name: string; stored_name: string; size: number; uploader: string | null; created_at: string; view_url: string; download_url: string; update_url: string; destroy_url: string };

export default function ReportsIndex({ organizationReports, search, canUpload, storeUrl }: { organizationReports: { data: Report[] }; search: string; canUpload: boolean; storeUrl: string }) {
    const upload = useForm<{ name: string; file: File | null }>({ name: '', file: null });
    return <div className="space-y-6">
        <PageHeader title="Organization Reports/Documents" description="Search, view, and download shared organization files." />
        <form className="flex gap-2" onSubmit={(event) => { event.preventDefault(); router.get(window.location.pathname, { search: (event.currentTarget.elements.namedItem('search') as HTMLInputElement).value }); }}>
            <Input name="search" defaultValue={search} placeholder="Search by name, file name, or uploader" /><Button type="submit">Search</Button>
        </form>
        {canUpload && <Card><CardHeader><CardTitle>Upload document</CardTitle><CardDescription>PDF, DOC, DOCX, XLS, XLSX, PPT, or PPTX. Maximum 20MB.</CardDescription></CardHeader><CardContent>
            <form className="grid gap-3 md:grid-cols-[1fr_1fr_auto]" onSubmit={(event) => {
                event.preventDefault();
                upload.post(storeUrl, { forceFormData: true, onSuccess: () => { upload.reset(); notify('success', 'Document uploaded.'); } });
            }}><Input name="name" placeholder="Document name" value={upload.data.name} onChange={(event) => upload.setData('name', event.target.value)} required /><Input name="file" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" onChange={(event) => upload.setData('file', event.target.files?.[0] ?? null)} required /><Button type="submit" disabled={upload.processing} loading={upload.processing}>Upload</Button></form>
        </CardContent></Card>}
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {organizationReports.data.map((report) => <Card key={report.id}><CardHeader><CardTitle className="truncate" title={report.name}>{report.name}</CardTitle><CardDescription>{report.stored_name}<br />By {report.uploader ?? 'Unknown'}</CardDescription></CardHeader><CardContent className="flex flex-wrap gap-2">
                <Button size="sm" variant="outline" render={<a href={report.view_url} target="_blank" rel="noreferrer" />}>View</Button>
                <Button size="sm" render={<a href={report.download_url} />}>Download</Button>
                {canUpload && <Button size="sm" variant="destructive" onClick={() => { if (window.confirm('Delete this document?')) router.delete(report.destroy_url, { onSuccess: () => notify('success', 'Document deleted.') }); }}>Delete</Button>}
            </CardContent></Card>)}
        </div>
    </div>;
}

ReportsIndex.layout = (page: ReactNode) => <AppLayout title="Reports">{page}</AppLayout>;
