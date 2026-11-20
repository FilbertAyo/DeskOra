import { BoldIcon, ItalicIcon, ListIcon, ListOrderedIcon, Redo2Icon, RemoveFormattingIcon, UnderlineIcon, Undo2Icon } from 'lucide-react';
import { useEffect, useRef } from 'react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const COMMANDS = [
    { command: 'undo', label: 'Undo', icon: Undo2Icon },
    { command: 'redo', label: 'Redo', icon: Redo2Icon },
    { command: 'bold', label: 'Bold', icon: BoldIcon },
    { command: 'italic', label: 'Italic', icon: ItalicIcon },
    { command: 'underline', label: 'Underline', icon: UnderlineIcon },
    { command: 'insertUnorderedList', label: 'Bulleted list', icon: ListIcon },
    { command: 'insertOrderedList', label: 'Numbered list', icon: ListOrderedIcon },
    { command: 'removeFormat', label: 'Clear formatting', icon: RemoveFormattingIcon },
] as const;

export default function LetterBodyEditor({
    value,
    onChange,
    invalid,
}: {
    value: string;
    onChange: (html: string) => void;
    invalid?: boolean;
}) {
    const editorRef = useRef<HTMLDivElement>(null);
    // Painted on first render. React leaves the DOM alone while this string stays stable,
    // so typing is not reset and the saved body is visible immediately on edit.
    const initialHtml = useRef(value);
    const lastHtml = useRef(value);

    useEffect(() => {
        const editor = editorRef.current;
        if (!editor || value === lastHtml.current) {
            return;
        }

        editor.innerHTML = value;
        lastHtml.current = value;
    }, [value]);

    const emit = () => {
        const html = editorRef.current?.innerHTML ?? '';
        lastHtml.current = html;
        onChange(html);
    };

    const run = (command: string) => {
        editorRef.current?.focus();
        document.execCommand(command);
        emit();
    };

    return (
        <div className={cn('overflow-hidden rounded-md border bg-transparent shadow-xs', invalid ? 'border-destructive' : 'border-input')}>
            <div className="bg-muted/40 flex flex-wrap gap-1 border-b p-1">
                {COMMANDS.map(({ command, label, icon: Icon }) => (
                    <Button
                        key={command}
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        aria-label={label}
                        title={label}
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={() => run(command)}
                    >
                        <Icon />
                    </Button>
                ))}
            </div>
            <div
                ref={editorRef}
                contentEditable
                suppressContentEditableWarning
                role="textbox"
                aria-multiline="true"
                aria-label="Letter body"
                className="text-foreground min-h-[360px] px-3 py-3 text-[12pt] leading-snug outline-none [&_*]:!text-foreground [&_ol]:my-2 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:mb-[0.6em] [&_p]:text-justify [&_ul]:my-2 [&_ul]:list-disc [&_ul]:pl-6"
                style={{ fontFamily: "Calibri, Carlito, 'Segoe UI', sans-serif", textAlign: 'justify', WebkitTextFillColor: 'currentcolor' }}
                dangerouslySetInnerHTML={{ __html: initialHtml.current }}
                onInput={emit}
                onBlur={emit}
            />
        </div>
    );
}
