import { EyeIcon, EyeOffIcon } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group';

export default function PasswordField({
    id,
    label,
    name,
    value,
    error,
    autoComplete,
    placeholder = '••••••••',
    onChange,
}: {
    id: string;
    label: string;
    name: string;
    value: string;
    error?: string;
    autoComplete: string;
    placeholder?: string;
    onChange: (value: string) => void;
}) {
    const [visible, setVisible] = useState(false);

    return (
        <Field className="w-full gap-2">
            <FieldLabel htmlFor={id} className="leading-5">
                {label}
            </FieldLabel>
            <InputGroup>
                <InputGroupInput
                    id={id}
                    name={name}
                    type={visible ? 'text' : 'password'}
                    placeholder={placeholder}
                    value={value}
                    autoComplete={autoComplete}
                    required
                    aria-invalid={error ? true : undefined}
                    onChange={(event) => onChange(event.target.value)}
                />
                <InputGroupAddon align="inline-end" className="pr-1.5">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={() => setVisible((current) => !current)}
                        className="text-muted-foreground rounded-l-none hover:bg-transparent"
                    >
                        {visible ? <EyeOffIcon /> : <EyeIcon />}
                        <span className="sr-only">{visible ? 'Hide password' : 'Show password'}</span>
                    </Button>
                </InputGroupAddon>
            </InputGroup>
            <FieldError>{error}</FieldError>
        </Field>
    );
}
