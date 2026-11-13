import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { notify } from '@/lib/notify';

type Task = { task_uid: string; task_no: number; task: string; responsible_user_ids: string[]; progress: string; due_date: string; compilation_date: string; notes_progress: string };
type User = { id: string; name: string };
type Props = { mode: 'create' | 'edit'; defaultPeriodId: string; tasks: Task[]; teamUsers: User[]; submitUrl: string; backUrl: string };
const blankTask = (number: number): Task => ({ task_uid: `new_${Date.now()}_${number}`, task_no: number, task: '', responsible_user_ids: [], progress: 'Not Started', due_date: '', compilation_date: '', notes_progress: '' });

export default function ActionLogForm({ mode, defaultPeriodId, tasks, teamUsers, submitUrl, backUrl }: Props) {
    const form = useForm<{ period_id: string; content: { tasks: Task[] }; action: string }>({ period_id: defaultPeriodId, content: { tasks: tasks.length ? tasks : [blankTask(1)] }, action: 'draft' });
    const update = (index: number, patch: Partial<Task>) => form.setData('content', { tasks: form.data.content.tasks.map((task, taskIndex) => taskIndex === index ? { ...task, ...patch } : task) });
    const submit = (action: 'draft' | 'submit') => {
        form.setData('action', action);
        form.transform((data) => ({ ...data, action }));
        const options = { onSuccess: () => notify('success', action === 'submit' ? 'Action log submitted.' : 'Draft saved.') };
        if (mode === 'create') form.post(submitUrl, options); else form.put(submitUrl, options);
    };
    return <div className="flex flex-col gap-6"><div><h1 className="text-2xl font-semibold">{mode === 'create' ? 'Create' : 'Edit'} Action Log</h1><p className="text-muted-foreground text-sm">Add and assign weekly action tasks.</p></div>
        <Card><CardHeader className="flex-row items-center justify-between"><CardTitle>Action Log Tasks</CardTitle><Button type="button" size="sm" onClick={() => form.setData('content', { tasks: [...form.data.content.tasks, blankTask(form.data.content.tasks.length + 1)] })}>Add task</Button></CardHeader><CardContent className="space-y-4">
            {form.data.content.tasks.map((task, index) => <div key={task.task_uid} className="grid gap-3 rounded-lg border p-4 md:grid-cols-2">
                <Textarea name={`content[tasks][${index}][task]`} value={task.task} placeholder="Task" required onChange={(event) => update(index, { task: event.target.value })} />
                <select multiple name={`content[tasks][${index}][responsible_user_ids][]`} className="border-input bg-background min-h-20 rounded-md border px-3" value={task.responsible_user_ids} onChange={(event) => update(index, { responsible_user_ids: Array.from(event.target.selectedOptions, (option) => option.value) })}>{teamUsers.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}</select>
                <select name={`content[tasks][${index}][progress]`} className="border-input bg-background h-9 rounded-md border px-3" value={task.progress} onChange={(event) => update(index, { progress: event.target.value })}>{['Not Started', 'In Progress', 'Done', 'Cancelled'].map((status) => <option key={status}>{status}</option>)}</select>
                <Input name={`content[tasks][${index}][due_date]`} type="date" value={task.due_date} onChange={(event) => update(index, { due_date: event.target.value })} />
                <Input name={`content[tasks][${index}][compilation_date]`} type="date" value={task.compilation_date} onChange={(event) => update(index, { compilation_date: event.target.value })} />
                <Textarea name={`content[tasks][${index}][notes_progress]`} value={task.notes_progress} placeholder="Notes / progress" onChange={(event) => update(index, { notes_progress: event.target.value })} />
                <Button type="button" variant="destructive" size="sm" className="w-fit" onClick={() => form.setData('content', { tasks: form.data.content.tasks.filter((_, taskIndex) => taskIndex !== index) })}>Remove</Button>
            </div>)}
            <div className="flex justify-between gap-2"><Button type="button" variant="outline" render={<a href={backUrl} />}>Cancel</Button><div className="flex gap-2"><Button type="button" variant="outline" disabled={form.processing} data-action="draft" onClick={() => submit('draft')}>Save draft</Button><Button type="button" disabled={form.processing} data-action="submit" onClick={() => submit('submit')}>Submit</Button></div></div>
        </CardContent></Card>
    </div>;
}

ActionLogForm.layout = (page: ReactNode) => <AppLayout title="Action Log">{page}</AppLayout>;
