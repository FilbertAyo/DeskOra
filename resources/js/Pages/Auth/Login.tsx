import { Link, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';

import AuthCard from '@/components/auth/AuthCard';
import PasswordField from '@/components/auth/PasswordField';
import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { notify } from '@/lib/notify';
import { route } from '@/lib/route';
import type { SharedProps } from '@/types/inertia';

function firstFormError(errors: Record<string, string | undefined>): string | null {
    for (const value of Object.values(errors)) {
        if (typeof value === 'string' && value !== '') {
            return value;
        }
    }

    return null;
}

function fieldValue(form: HTMLFormElement, name: string): string {
    const field = form.elements.namedItem(name);

    if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
        return field.value;
    }

    if (field instanceof RadioNodeList && field.length > 0) {
        const first = field[0];
        if (first instanceof HTMLInputElement) {
            return first.value;
        }
    }

    return '';
}

export default function Login() {
    const { branding } = usePage<SharedProps>().props;
    const form = useForm({
        email: '',
        password: '',
        remember: true,
    });
    const [formNotice, setFormNotice] = useState<string | null>(null);
    const emailError = form.errors.email ?? formNotice;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setFormNotice(null);

        // Chrome autofill often paints values without firing React onChange.
        // Always read the live DOM values at submit time.
        const email = fieldValue(event.currentTarget, 'email').trim() || form.data.email.trim();
        const password = fieldValue(event.currentTarget, 'password') || form.data.password;

        if (!email || !password) {
            setFormNotice('Enter your work email and password to sign in.');
            return;
        }

        form.transform(() => ({
            email,
            password,
            remember: form.data.remember,
        }));

        form.post(route('login'), {
            preserveScroll: true,
            replace: true,
            onError: (errors) => {
                if (!firstFormError(errors)) {
                    setFormNotice('These credentials do not match our records.');
                }
            },
            onCancel: () => {
                const message = 'Sign-in was interrupted. Please try again.';
                setFormNotice(message);
                notify('error', message);
            },
            onFinish: () => {
                form.transform((data) => data);
            },
        });
    };

    return (
        <AuthCard title="Welcome back" description={`Sign in to your ${branding.appName} workspace`}>
            <form onSubmit={submit}>
                <FieldGroup className="gap-4">
                    <Button type="button" variant="outline" className="pointer-events-none w-full" aria-disabled="true" tabIndex={-1}>
                        Login as staff
                    </Button>
                    <Field className="gap-2">
                        <FieldLabel htmlFor="email" className="leading-5">
                            Work email
                        </FieldLabel>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            placeholder="you@company.com"
                            value={form.data.email}
                            autoComplete="username"
                            autoFocus
                            required
                            aria-invalid={emailError ? true : undefined}
                            onChange={(event) => {
                                setFormNotice(null);
                                form.setData('email', event.target.value);
                            }}
                            onInput={(event) => form.setData('email', event.currentTarget.value)}
                        />
                        <FieldError>{emailError}</FieldError>
                    </Field>
                    <PasswordField
                        id="password"
                        name="password"
                        label="Password"
                        value={form.data.password}
                        error={form.errors.password}
                        autoComplete="current-password"
                        onChange={(value) => form.setData('password', value)}
                    />
                    <div className="flex items-center justify-between gap-3">
                        <label htmlFor="remember" className="flex items-center gap-2 text-sm">
                            <Checkbox
                                id="remember"
                                name="remember"
                                checked={form.data.remember}
                                onCheckedChange={(checked) => form.setData('remember', checked === true)}
                            />
                            <span>Remember me</span>
                        </label>
                        <Link href={route('password.request')} className="text-sm hover:underline">
                            Forgot Password?
                        </Link>
                    </div>
                    <Field>
                        <button
                            className="bg-primary text-primary-foreground inline-flex h-10 w-full items-center justify-center gap-2 rounded-md text-sm font-medium disabled:opacity-50"
                            type="submit"
                            disabled={form.processing}
                        >
                            {form.processing ? (
                                <span className="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" />
                            ) : null}
                            Sign in
                        </button>
                    </Field>
                </FieldGroup>
            </form>
        </AuthCard>
    );
}

Login.layout = (page: ReactNode) => <GuestLayout title="Sign in">{page}</GuestLayout>;
