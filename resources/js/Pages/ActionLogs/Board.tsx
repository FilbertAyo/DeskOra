import type { ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Task = { task_uid: string; task: string; notes_progress?: string; due_date?: string; responsible_names: string[] };
type Props = { board: { document?: { id: string } | null; columns: Record<string, Task[]>; can_edit: boolean }; progressOptions: string[]; indexUrl: string };

export default function ActionLogsBoard({ board, progressOptions, indexUrl }: Props) {
    return <div className="flex flex-col gap-6"><div className="flex items-center justify-between"><div><h1 className="text-2xl font-semibold">Action Log Board</h1><p className="text-muted-foreground text-sm">Tasks grouped by progress.</p></div><Button variant="outline" render={<a href={indexUrl} />}>List view</Button></div>
        {!board.document ? <Card><CardContent className="text-muted-foreground py-10 text-center">No action log found for this work period yet.</CardContent></Card> :
            <div className="grid items-start gap-4 xl:grid-cols-4">{progressOptions.map((status) => <Card key={status}><CardHeader className="flex-row items-center justify-between"><CardTitle className="text-base">{status}</CardTitle><Badge variant="outline">{board.columns[status]?.length ?? 0}</Badge></CardHeader><CardContent className="space-y-3">{(board.columns[status] ?? []).length === 0 ? <p className="text-muted-foreground text-sm">No tasks</p> : board.columns[status].map((task) => <div key={task.task_uid} className="rounded-lg border p-3"><p className="font-medium">{task.task}</p>{task.responsible_names.length > 0 && <p className="text-muted-foreground mt-1 text-xs">{task.responsible_names.join(', ')}</p>}{task.notes_progress && <p className="mt-2 text-sm">{task.notes_progress}</p>}{task.due_date && <p className="text-muted-foreground mt-2 text-xs">Due {task.due_date}</p>}</div>)}</CardContent></Card>)}</div>}
    </div>;
}

ActionLogsBoard.layout = (page: ReactNode) => <AppLayout title="Action Log Board">{page}</AppLayout>;
