import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

import AppLink from '@/components/shared/AppLink';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { notify } from '@/lib/notify';

type Period = { id: string; label: string; date_range?: string };
type StaffUser = { id: string; name: string };

type Props = {
    periods: Period[];
    staffUsers: StaffUser[];
    defaultPeriodId: string;
    submitUrl: string;
    indexUrl: string;
};

export default function AssignedTasksCreate({
    periods,
    staffUsers,
    defaultPeriodId,
    submitUrl,
    indexUrl,
}: Props) {
    const form = useForm({
        title: '',
        description: '',
        period_id: defaultPeriodId,
        deadline: '',
        assignee_user_ids: [] as string[],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(submitUrl, {
            onSuccess: () => notify('success', 'Task assigned successfully.'),
        });
    };

    return (
        <div className="flex flex-col gap-6">
            <div>
                <h1 className="text-lg font-semibold">Assign Task</h1>
                <p className="text-muted-foreground text-sm">
                    Assign a management task to one or more staff members for a selected work week.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Task details</CardTitle>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="space-y-4">
                        <Field>
                            <FieldLabel htmlFor="task-title">Title</FieldLabel>
                            <Input
                                id="task-title"
                                value={form.data.title}
                                onChange={(event) => form.setData('title', event.target.value)}
                                required
                            />
                            <FieldError>{form.errors.title}</FieldError>
                        </Field>

                        <Field>
                            <FieldLabel htmlFor="task-description">Description</FieldLabel>
                            <Textarea
                                id="task-description"
                                value={form.data.description}
                                onChange={(event) => form.setData('description', event.target.value)}
                                rows={4}
                            />
                            <FieldError>{form.errors.description}</FieldError>
                        </Field>

                        <Field>
                            <FieldLabel htmlFor="task-period">Week</FieldLabel>
                            <select
                                id="task-period"
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                value={form.data.period_id}
                                onChange={(event) => form.setData('period_id', event.target.value)}
                                required
                            >
                                <option value="" disabled>
                                    Select week
                                </option>
                                {periods.map((period) => (
                                    <option key={period.id} value={period.id}>
                                        {period.label}
                                        {period.date_range ? ` (${period.date_range})` : ''}
                                    </option>
                                ))}
                            </select>
                            <FieldError>{form.errors.period_id}</FieldError>
                        </Field>

                        <Field>
                            <FieldLabel htmlFor="task-deadline">Deadline (optional)</FieldLabel>
                            <Input
                                id="task-deadline"
                                type="date"
                                value={form.data.deadline}
                                onChange={(event) => form.setData('deadline', event.target.value)}
                            />
                            <FieldError>{form.errors.deadline}</FieldError>
                        </Field>

                        <Field>
                            <FieldLabel htmlFor="task-assignees">Assign to</FieldLabel>
                            <select
                                id="task-assignees"
                                multiple
                                className="border-input bg-background min-h-40 w-full rounded-md border px-3 py-2 text-sm"
                                value={form.data.assignee_user_ids}
                                onChange={(event) =>
                                    form.setData(
                                        'assignee_user_ids',
                                        Array.from(event.target.selectedOptions, (option) => option.value),
                                    )
                                }
                                required
                            >
                                {staffUsers.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name}
                                    </option>
                                ))}
                            </select>
                            <p className="text-muted-foreground mt-1 text-xs">
                                Hold Ctrl (Windows) or Cmd (Mac) to select multiple staff members.
                            </p>
                            <FieldError>{form.errors.assignee_user_ids}</FieldError>
                        </Field>

                        <div className="flex justify-between gap-2">
                            <Button type="button" variant="outline" render={<AppLink href={indexUrl} />}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing} loading={form.processing}>
                                Assign task
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

AssignedTasksCreate.layout = (page: ReactNode) => <AppLayout title="Assign Task">{page}</AppLayout>;
