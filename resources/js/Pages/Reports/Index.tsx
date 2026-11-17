import { router, useForm } from '@inertiajs/react';
import { DownloadIcon, EyeIcon, Trash2Icon, UploadIcon } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { notify } from '@/lib/notify';
import { PageHeader, PageShell } from '@/lib/page-kit';

type Report = {
    id: string;
    name: string;
    stored_name: string;
    size: number;
    uploader: string | null;
    created_at: string;
    view_url: string;
    download_url: string;
    update_url: string;
    destroy_url: string;
};

export default function ReportsIndex({
    organizationReports,
    search,
    canUpload,
    storeUrl,
}: {
    organizationReports: { data: Report[] };
    search: string;
    canUpload: boolean;
    storeUrl: string;
}) {
    const [uploadOpen, setUploadOpen] = useState(false);
    const upload = useForm<{ name: string; file: File | null }>({ name: '', file: null });

    const submitUpload = (event: FormEvent) => {
        event.preventDefault();
        upload.post(storeUrl, {
            forceFormData: true,
            onSuccess: () => {
                upload.reset();
                setUploadOpen(false);
                notify('success', 'Document uploaded.');
            },
        });
    };

    return (
        <PageShell>
            <PageHeader
                title="Organization Reports/Documents"
                description="Search, view, and download shared organization files."
                actions={
                    canUpload ? (
                        <Button size="sm" onClick={() => setUploadOpen(true)}>
                            <UploadIcon />
                            Upload
                        </Button>
                    ) : undefined
                }
            />
            <form
                className="flex gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    router.get(window.location.pathname, {
                        search: (event.currentTarget.elements.namedItem('search') as HTMLInputElement).value,
                    });
                }}
            >
                <Input name="search" defaultValue={search} placeholder="Search by name, file name, or uploader" />
                <Button type="submit" size="sm">
                    Search
                </Button>
            </form>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {organizationReports.data.map((report) => (
                    <Card key={report.id} className="shadow-none">
                        <CardHeader className="flex flex-row items-start justify-between gap-2 space-y-0">
                            <div className="min-w-0">
                                <CardTitle className="truncate text-sm" title={report.name}>
                                    {report.name}
                                </CardTitle>
                                <CardDescription className="text-xs">
                                    {report.stored_name}
                                    <br />
                                    By {report.uploader ?? 'Unknown'}
                                </CardDescription>
                            </div>
                            <RowActions
                                items={[
                                    {
                                        label: 'View',
                                        href: report.view_url,
                                        icon: <EyeIcon />,
                                        external: true,
                                    },
                                    {
                                        label: 'Download',
                                        href: report.download_url,
                                        icon: <DownloadIcon />,
                                        external: true,
                                    },
                                    {
                                        label: 'Delete',
                                        icon: <Trash2Icon />,
                                        destructive: true,
                                        hidden: !canUpload,
                                        onClick: () => {
                                            if (window.confirm('Delete this document?')) {
                                                router.delete(report.destroy_url, {
                                                    onSuccess: () => notify('success', 'Document deleted.'),
                                                });
                                            }
                                        },
                                    },
                                ]}
                            />
                        </CardHeader>
                    </Card>
                ))}
            </div>

            {canUpload && (
                <Dialog
                    open={uploadOpen}
                    onOpenChange={(open) => {
                        setUploadOpen(open);
                        if (!open) {
                            upload.reset();
                            upload.clearErrors();
                        }
                    }}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Upload document</DialogTitle>
                            <DialogDescription>
                                PDF, DOC, DOCX, XLS, XLSX, PPT, or PPTX. Maximum 20MB.
                            </DialogDescription>
                        </DialogHeader>
                        <form className="space-y-4" onSubmit={submitUpload}>
                            <div className="space-y-2">
                                <Label htmlFor="report-name">Document name</Label>
                                <Input
                                    id="report-name"
                                    name="name"
                                    placeholder="Document name"
                                    value={upload.data.name}
                                    onChange={(event) => upload.setData('name', event.target.value)}
                                    required
                                />
                                {upload.errors.name && (
                                    <p className="text-destructive text-xs">{upload.errors.name}</p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="report-file">File</Label>
                                <Input
                                    id="report-file"
                                    name="file"
                                    type="file"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                                    onChange={(event) => upload.setData('file', event.target.files?.[0] ?? null)}
                                    required
                                />
                                {upload.errors.file && (
                                    <p className="text-destructive text-xs">{upload.errors.file}</p>
                                )}
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" size="sm" onClick={() => setUploadOpen(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" size="sm" disabled={upload.processing} loading={upload.processing}>
                                    Upload
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            )}
        </PageShell>
    );
}

ReportsIndex.layout = (page: ReactNode) => <AppLayout title="Reports">{page}</AppLayout>;
