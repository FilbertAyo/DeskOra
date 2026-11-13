import { router, useForm } from '@inertiajs/react';
import { EyeIcon, LockIcon, PencilIcon } from 'lucide-react';
import { useState, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import type { NextPeriod, Period } from '@/Pages/WorkPeriods/types';
import RowActions from '@/components/shared/RowActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';


type Link = { url: string | null; label: string; active: boolean };
type PeriodRow = Period & { documentsCount: number };
type Paginated = { data: PeriodRow[]; from: number | null; links: Link[] };

export default function WorkPeriodsIndex({
    periods,
    years,
    filters,
    currentPeriod,
    nextPeriod,
    storeUrl,
    bulkResetUrl,
}: {
    periods: Paginated;
    years: number[];
    filters: { year: number; status: string | null };
    currentPeriod: Period | null;
    nextPeriod: NextPeriod;
    storeUrl: string;
    bulkResetUrl: string;
}) {
    const [createOpen, setCreateOpen] = useState(false);
    const [resetOpen, setResetOpen] = useState(false);
    const [selected, setSelected] = useState<string[]>([]);
    const createForm = useForm({
        year: String(nextPeriod.year),
        week_number: String(nextPeriod.weekNumber),
        week_start_date: nextPeriod.weekStartDate,
    });
    const resetForm = useForm<{ period_ids: string[] }>({ period_ids: [] });
    const allSelected = periods.data.length > 0 && periods.data.every((period) => selected.includes(period.id));

    const submitCreate = () =>
        createForm.post(storeUrl, {
            onSuccess: () => setCreateOpen(false),
            onError: () => setCreateOpen(true),
        });

    const submitReset = () => {
        resetForm.transform(() => ({ period_ids: selected }));
        resetForm.post(bulkResetUrl, {
            onSuccess: () => {
                setResetOpen(false);
                setSelected([]);
            },
        });
    };

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold">Work Periods Management</h1>
                    <p className="text-muted-foreground text-sm">Manage weekly submission periods and deadlines.</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" onClick={() => router.reload()}>
                        Refresh
                    </Button>
                    <Button variant="outline" onClick={() => setResetOpen(true)}>
                        Reopen + Reset Docs
                    </Button>
                    <Badge variant="outline">{selected.length} selected</Badge>
                    <Button onClick={() => setCreateOpen(true)}>Add Work Period</Button>
                </div>
            </div>

            {currentPeriod && (
                <Card>
                    <CardContent className="flex flex-wrap justify-between gap-4 pt-6">
                        <div>
                            <p className="font-medium">
                                Current Period: Week {currentPeriod.weekNumber} of {currentPeriod.year}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                {currentPeriod.dateRange} · <Status status={currentPeriod.status} />
                            </p>
                        </div>
                        <div className="grid grid-cols-2 gap-6 text-sm">
                            <div>
                                <span className="text-muted-foreground">Plan Deadline</span>
                                <strong className="block">{currentPeriod.planDeadline}</strong>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Report Deadline</span>
                                <strong className="block">{currentPeriod.reportDeadline}</strong>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>All Work Periods</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex flex-wrap gap-2">
                        <select
                            name="year"
                            className="border-input bg-background h-9 rounded-md border px-2"
                            value={filters.year}
                            onChange={(event) =>
                                router.get(window.location.pathname, {
                                    year: event.target.value,
                                    status: filters.status || undefined,
                                })
                            }
                        >
                            {years.map((year) => (
                                <option key={year} value={year}>
                                    {year}
                                </option>
                            ))}
                        </select>
                        <select
                            name="status"
                            className="border-input bg-background h-9 rounded-md border px-2"
                            value={filters.status ?? ''}
                            onChange={(event) =>
                                router.get(window.location.pathname, {
                                    year: filters.year,
                                    status: event.target.value || undefined,
                                })
                            }
                        >
                            <option value="">All statuses</option>
                            <option value="open">Open</option>
                            <option value="closed">Closed</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    <Checkbox
                                        checked={allSelected}
                                        onCheckedChange={(checked) =>
                                            setSelected(checked ? periods.data.map((period) => period.id) : [])
                                        }
                                        aria-label="Select all periods"
                                    />
                                </TableHead>
                                <TableHead>No.</TableHead>
                                <TableHead>Year / Week</TableHead>
                                <TableHead>Date Range</TableHead>
                                <TableHead>Plan Deadline</TableHead>
                                <TableHead>Report Deadline</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Documents</TableHead>
                                <TableHead className="w-12 text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {periods.data.map((period, index) => (
                                <TableRow key={period.id}>
                                    <TableCell>
                                        <Checkbox
                                            checked={selected.includes(period.id)}
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked
                                                        ? [...selected, period.id]
                                                        : selected.filter((id) => id !== period.id),
                                                )
                                            }
                                            aria-label={`Select week ${period.weekNumber}`}
                                        />
                                    </TableCell>
                                    <TableCell>{(periods.from ?? 1) + index}</TableCell>
                                    <TableCell>
                                        <strong>{period.year}</strong>
                                        <br />
                                        Week {period.weekNumber}
                                    </TableCell>
                                    <TableCell>{period.dateRange}</TableCell>
                                    <Deadline value={period.planDeadline} state={period.planDeadlineState} />
                                    <Deadline value={period.reportDeadline} state={period.reportDeadlineState} />
                                    <TableCell>
                                        <Status status={period.status} />
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline">{period.documentsCount}</Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <RowActions
                                            items={[
                                                { label: 'View', href: period.showUrl, icon: <EyeIcon /> },
                                                { label: 'Edit', href: period.editUrl, icon: <PencilIcon /> },
                                                {
                                                    label: 'Close',
                                                    icon: <LockIcon />,
                                                    hidden: period.status !== 'open',
                                                    onClick: () =>
                                                        window.confirm('Are you sure you want to close this period?') &&
                                                        router.post(period.closeUrl),
                                                },
                                            ]}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                            {periods.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={9} className="text-muted-foreground py-10 text-center">
                                        No work periods found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>

                    <div className="flex flex-wrap gap-1">
                        {periods.links.map((link, index) => (
                            <Button
                                key={`${link.label}-${index}`}
                                size="sm"
                                variant={link.active ? 'default' : 'outline'}
                                disabled={!link.url}
                                onClick={() => link.url && router.visit(link.url)}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                </CardContent>
            </Card>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create Work Period</DialogTitle>
                        <DialogDescription>Only the immediate next week can be created.</DialogDescription>
                    </DialogHeader>
                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitCreate();
                        }}
                    >
                        <ReadOnlyField label="Year" name="year" value={createForm.data.year} error={createForm.errors.year} />
                        <ReadOnlyField
                            label="Week Number"
                            name="week_number"
                            value={createForm.data.week_number}
                            error={createForm.errors.week_number}
                        />
                        <ReadOnlyField
                            label="Week Start Date (Monday)"
                            name="week_start_date"
                            value={createForm.data.week_start_date}
                            error={createForm.errors.week_start_date}
                            type="date"
                        />
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createForm.processing} loading={createForm.processing}>
                                Create Work Period
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={resetOpen} onOpenChange={setResetOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reopen Selected Periods + Reset Weekly Docs</DialogTitle>
                        <DialogDescription>
                            This applies organization-wide within the {selected.length} selected period(s).
                        </DialogDescription>
                    </DialogHeader>
                    <ul className="list-disc space-y-1 pl-5 text-sm">
                        <li>Closed periods will be reopened.</li>
                        <li>Weekly plans and reports will move to draft.</li>
                    </ul>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setResetOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            disabled={selected.length === 0 || resetForm.processing}
                            loading={resetForm.processing}
                            onClick={() => window.confirm('Confirm bulk reset?') && submitReset()}
                        >
                            Confirm Reopen + Reset
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function Status({ status }: { status: Period['status'] }) {
    return (
        <Badge variant="outline" className="capitalize">
            {status}
        </Badge>
    );
}

function Deadline({ value, state }: { value: string; state: string }) {
    return (
        <TableCell>
            {value}
            <br />
            <Badge variant="outline" className="mt-1 capitalize">
                {state}
            </Badge>
        </TableCell>
    );
}

function ReadOnlyField({
    label,
    name,
    value,
    error,
    type = 'text',
}: {
    label: string;
    name: string;
    value: string;
    error?: string;
    type?: string;
}) {
    return (
        <Field>
            <FieldLabel htmlFor={name}>{label}</FieldLabel>
            <Input id={name} name={name} type={type} value={value} readOnly required />
            <FieldError>{error}</FieldError>
        </Field>
    );
}

WorkPeriodsIndex.layout = (page: ReactNode) => <AppLayout title="Work Periods">{page}</AppLayout>;
