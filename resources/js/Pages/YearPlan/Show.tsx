import { router, useForm } from '@inertiajs/react';
import { DownloadIcon, PencilIcon, PlusIcon, Trash2Icon } from 'lucide-react';
import { useState, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import RowActions from '@/components/shared/RowActions';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { PageHeader, PageShell } from '@/lib/page-kit';

type Activity = {
    id: string; code: string; departmentId: string; sortOrder: number; description: string; frequency: number;
    unitCount: number; unitValueTzs: number; totalCostTzs: number; plannedMonths: number[]; updateUrl: string; destroyUrl: string;
};
type Section = { department: { id: string; name: string; parentName: string | null; sectionNumber: number | null; pdfUrl: string; excelUrl: string }; activities: Activity[]; subtotal: number };
type Props = {
    yearPlan: { year: number; indexUrl: string; storeActivityUrl: string; pdfUrl: string; excelUrl: string };
    sections: Section[]; grandTotal: number; subDepartments: Array<{ id: string; name: string; parentName: string | null }>;
};
const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const emptyActivity = { department_id: '', description: '', frequency: 1, unit_count: 1, unit_value_tzs: 0, planned_months: [] as number[], sort_order: 1 };

export default function YearPlanShow({ yearPlan, sections, grandTotal, subDepartments }: Props) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Activity | null>(null);
    const form = useForm(emptyActivity);
    const startCreate = (departmentId = '') => { setEditing(null); form.setData({ ...emptyActivity, department_id: departmentId }); form.clearErrors(); setOpen(true); };
    const startEdit = (activity: Activity) => {
        setEditing(activity);
        form.setData({ department_id: activity.departmentId, description: activity.description, frequency: activity.frequency, unit_count: activity.unitCount, unit_value_tzs: activity.unitValueTzs, planned_months: activity.plannedMonths, sort_order: activity.sortOrder });
        form.clearErrors();
        setOpen(true);
    };
    const submit = () => editing ? form.put(editing.updateUrl, { preserveScroll: true, onSuccess: () => setOpen(false) }) : form.post(yearPlan.storeActivityUrl, { preserveScroll: true, onSuccess: () => setOpen(false) });
    return (
        <PageShell>
            <div>
                <a href={yearPlan.indexUrl} className="text-muted-foreground text-sm hover:text-foreground">
                    ← Year plans
                </a>
                <PageHeader
                    className="mb-0 mt-2"
                    title={`Year Plan ${yearPlan.year}`}
                    description="Total cost per line is frequency × units × unit value (TZS)."
                    actions={
                        <>
                            <Button variant="outline" render={<a href={yearPlan.pdfUrl} />}>
                                Download PDF
                            </Button>
                            <Button variant="outline" render={<a href={yearPlan.excelUrl} />}>
                                Download Excel
                            </Button>
                            <Button onClick={() => startCreate()}>Add activity</Button>
                        </>
                    }
                />
            </div>
            {sections.map((section) => (
                <Card key={section.department.id} className="shadow-none">
                    <CardHeader className="border-b bg-muted/20">
                        <CardTitle className="flex flex-wrap items-center justify-between gap-2 text-base">
                            <span>
                                {section.department.sectionNumber ? `${section.department.sectionNumber}. ` : ''}
                                {section.department.parentName ? `${section.department.parentName} — ` : ''}
                                {section.department.name}
                            </span>
                            <RowActions
                                items={[
                                    { label: 'Download PDF', href: section.department.pdfUrl, icon: <DownloadIcon /> },
                                    { label: 'Download Excel', href: section.department.excelUrl, icon: <DownloadIcon /> },
                                    {
                                        label: 'Add Activity',
                                        icon: <PlusIcon />,
                                        onClick: () => startCreate(section.department.id),
                                    },
                                ]}
                            />
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="pt-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Code</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead>Frequency</TableHead>
                                    <TableHead>Units</TableHead>
                                    <TableHead>Unit value</TableHead>
                                    <TableHead>Total</TableHead>
                                    <TableHead>Months</TableHead>
                                    <TableHead className="w-12 text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {section.activities.map((activity) => (
                                    <TableRow key={activity.id}>
                                        <TableCell>{activity.code}</TableCell>
                                        <TableCell className="max-w-md whitespace-normal">{activity.description}</TableCell>
                                        <TableCell>{activity.frequency}</TableCell>
                                        <TableCell>{activity.unitCount}</TableCell>
                                        <TableCell>{money(activity.unitValueTzs)}</TableCell>
                                        <TableCell>{money(activity.totalCostTzs)}</TableCell>
                                        <TableCell>
                                            {activity.plannedMonths.map((month) => months[month - 1]).join(', ') || '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <RowActions
                                                items={[
                                                    {
                                                        label: 'Edit',
                                                        icon: <PencilIcon />,
                                                        onClick: () => startEdit(activity),
                                                    },
                                                    {
                                                        label: 'Delete',
                                                        icon: <Trash2Icon />,
                                                        destructive: true,
                                                        onClick: () =>
                                                            window.confirm('Remove this activity?') &&
                                                            router.delete(activity.destroyUrl, { preserveScroll: true }),
                                                    },
                                                ]}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <p className="mt-3 text-right font-medium">Subtotal: TZS {money(section.subtotal)}</p>
                    </CardContent>
                </Card>
            ))}
            {sections.length === 0 && (
                <Card className="shadow-none">
                    <CardContent className="text-muted-foreground py-12 text-center">
                        No activities yet. Add a sub-department line to begin.
                    </CardContent>
                </Card>
            )}
            <Card className="shadow-none">
                <CardContent className="pt-6 text-right text-xl font-semibold">
                    Grand total: TZS {money(grandTotal)}
                </CardContent>
            </Card>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit' : 'Add'} activity</DialogTitle>
                    </DialogHeader>
                    <form
                        className="grid gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submit();
                        }}
                    >
                        <label className="grid gap-1 text-sm">
                            Sub-department
                            <select
                                name="department_id"
                                required
                                className="border-input bg-background h-9 rounded-md border px-3"
                                value={form.data.department_id}
                                onChange={(event) => form.setData('department_id', event.target.value)}
                            >
                                <option value="">Select…</option>
                                {subDepartments.map((department) => (
                                    <option key={department.id} value={department.id}>
                                        {department.parentName ? `${department.parentName} — ` : ''}
                                        {department.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        {editing && (
                            <label className="grid gap-1 text-sm">
                                Line number (code suffix)
                                <Input
                                    name="sort_order"
                                    type="number"
                                    min={1}
                                    max={9999}
                                    required
                                    value={form.data.sort_order}
                                    onChange={(event) => form.setData('sort_order', Number(event.target.value))}
                                />
                            </label>
                        )}
                        <label className="grid gap-1 text-sm">
                            Description
                            <Textarea
                                name="description"
                                required
                                value={form.data.description}
                                onChange={(event) => form.setData('description', event.target.value)}
                            />
                        </label>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <NumberField
                                name="frequency"
                                label="Frequency"
                                value={form.data.frequency}
                                set={(value) => form.setData('frequency', value)}
                            />
                            <NumberField
                                name="unit_count"
                                label="Unit count"
                                value={form.data.unit_count}
                                set={(value) => form.setData('unit_count', value)}
                            />
                            <NumberField
                                name="unit_value_tzs"
                                label="Unit value (TZS)"
                                value={form.data.unit_value_tzs}
                                set={(value) => form.setData('unit_value_tzs', value)}
                            />
                        </div>
                        <fieldset>
                            <legend className="mb-2 text-sm">Planned months</legend>
                            <div className="flex flex-wrap gap-3">
                                {months.map((month, index) => {
                                    const number = index + 1;
                                    return (
                                        <label key={month} className="flex gap-1 text-sm">
                                            <input
                                                type="checkbox"
                                                name="planned_months[]"
                                                value={number}
                                                checked={form.data.planned_months.includes(number)}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'planned_months',
                                                        event.target.checked
                                                            ? [...form.data.planned_months, number]
                                                            : form.data.planned_months.filter((item) => item !== number),
                                                    )
                                                }
                                            />
                                            {month}
                                        </label>
                                    );
                                })}
                            </div>
                        </fieldset>
                        {Object.values(form.errors).length > 0 && (
                            <p className="text-destructive text-sm">{Object.values(form.errors)[0]}</p>
                        )}
                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing} loading={form.processing}>
                                Save activity
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </PageShell>
    );
}

function NumberField({ name, label, value, set }: { name: string; label: string; value: number; set: (value: number) => void }) { return <label className="grid gap-1 text-sm">{label}<Input name={name} type="number" min={0} step="0.01" required value={value} onChange={(event) => set(Number(event.target.value))} /></label>; }
function money(value: number): string { return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
YearPlanShow.layout = (page: ReactNode) => <AppLayout title="Year Plan">{page}</AppLayout>;
