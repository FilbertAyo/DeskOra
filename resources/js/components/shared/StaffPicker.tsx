import { UsersIcon } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type StaffUser = {
    id: string;
    name: string;
    title?: string | null;
};

function staffSummary(users: StaffUser[], value: string[], emptyLabel: string): string {
    if (value.length === 0) {
        return emptyLabel;
    }

    const names = value
        .map((id) => users.find((user) => user.id === id)?.name)
        .filter((name): name is string => Boolean(name));

    if (names.length === 0) {
        return `${value.length} selected`;
    }

    if (names.length <= 2) {
        return names.join(', ');
    }

    return `${names.slice(0, 2).join(', ')} +${names.length - 2}`;
}

export default function StaffPicker({
    users,
    value,
    onChange,
    label = 'Participants',
    disabled = false,
}: {
    users: StaffUser[];
    value: string[];
    onChange: (ids: string[]) => void;
    label?: string;
    disabled?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<string[]>(value);
    const [query, setQuery] = useState('');

    useEffect(() => {
        if (open) {
            setDraft(value);
            setQuery('');
        }
    }, [open, value]);

    const filtered = query.trim()
        ? users.filter((user) => {
              const haystack = `${user.name} ${user.title ?? ''}`.toLowerCase();
              return haystack.includes(query.trim().toLowerCase());
          })
        : users;

    const toggle = (id: string) => {
        setDraft((current) => (current.includes(id) ? current.filter((item) => item !== id) : [...current, id]));
    };

    return (
        <div className="space-y-1.5 text-sm">
            <span className="font-medium">{label}</span>
            <button
                type="button"
                disabled={disabled}
                onClick={() => setOpen(true)}
                className="border-input bg-background hover:bg-muted/40 flex h-9 w-full items-center gap-2 rounded-md border px-3 text-left text-sm disabled:cursor-not-allowed disabled:opacity-70"
            >
                <UsersIcon className="text-muted-foreground size-4 shrink-0" />
                <span className={`min-w-0 flex-1 truncate ${value.length === 0 ? 'text-muted-foreground' : ''}`}>
                    {staffSummary(users, value, `Select ${label.toLowerCase()}…`)}
                </span>
                {value.length > 0 && (
                    <span className="bg-muted text-muted-foreground rounded px-1.5 py-0.5 text-[10px] font-medium tabular-nums">
                        {value.length}
                    </span>
                )}
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Select {label.toLowerCase()}</DialogTitle>
                        <DialogDescription>Choose one or more people, then apply.</DialogDescription>
                    </DialogHeader>
                    <input
                        className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                        placeholder="Search staff…"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                    />
                    <div className="border-input max-h-64 space-y-1 overflow-y-auto rounded-md border p-2">
                        {filtered.length === 0 ? (
                            <p className="text-muted-foreground px-1 py-2 text-xs">No staff found</p>
                        ) : (
                            filtered.map((user) => {
                                const checked = draft.includes(user.id);
                                return (
                                    <label
                                        key={user.id}
                                        className="hover:bg-muted/50 flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm"
                                    >
                                        <input type="checkbox" checked={checked} onChange={() => toggle(user.id)} />
                                        <span className="min-w-0 flex-1 truncate">
                                            {user.name}
                                            {user.title ? (
                                                <span className="text-muted-foreground"> · {user.title}</span>
                                            ) : null}
                                        </span>
                                    </label>
                                );
                            })
                        )}
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={() => {
                                onChange(draft);
                                setOpen(false);
                            }}
                        >
                            Apply ({draft.length})
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
