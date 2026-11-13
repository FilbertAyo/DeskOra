import { Link, useForm, usePage } from '@inertiajs/react';
import { CircleAlertIcon } from 'lucide-react';
import { useState, type ReactNode } from 'react';

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

export default function Login() {
    const { branding } = usePage<SharedProps>().props;
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });
    const [formNotice, setFormNotice] = useState<string | null>(null);
    const validationMessage = formNotice ?? firstFormError(form.errors);

    return (
        <AuthCard title="Welcome back" description={`Sign in to your ${branding.appName} workspace`}>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    setFormNotice(null);
                    form.post(route('login'), {
                        preserveScroll: true,
                        onError: (errors) => {
                            const message = firstFormError(errors) ?? 'These credentials do not match our records.';
                            setFormNotice(message);
                            notify('error', message);
                        },
                    });
                }}
            >
                <FieldGroup className="gap-4">
                    <Button type="button" variant="outline" className="pointer-events-none w-full" aria-disabled="true" tabIndex={-1}>
                        Login as staff
                    </Button>
                    {validationMessage && (
                        <div role="alert" className="border-destructive/30 bg-destructive/10 text-destructive flex items-start gap-2 rounded-lg border px-3 py-2 text-sm">
                            <CircleAlertIcon className="mt-0.5 size-4 shrink-0" />
                            <span>{validationMessage}</span>
                        </div>
                    )}
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
                            aria-invalid={form.errors.email ? true : undefined}
                            onChange={(event) => form.setData('email', event.target.value)}
                        />
                        <FieldError>{form.errors.email}</FieldError>
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
                            Remember me
                        </label>
                        <Link href={route('password.request')} className="text-sm hover:underline">
                            Forgot Password?
                        </Link>
                    </div>
                    <Field>
                        <Button className="w-full" type="submit" disabled={form.processing} loading={form.processing}>
                            Sign in
                        </Button>
                    </Field>
                </FieldGroup>
            </form>
        </AuthCard>
    );
}

Login.layout = (page: ReactNode) => <GuestLayout title="Sign in">{page}</GuestLayout>;
