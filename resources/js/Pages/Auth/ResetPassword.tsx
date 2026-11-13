import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AuthCard from '@/components/auth/AuthCard';
import PasswordField from '@/components/auth/PasswordField';
import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { route } from '@/lib/route';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    return (
        <AuthCard title="Reset your password" description="Enter your email and a new password to complete the reset.">
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(route('password.store'));
                }}
            >
                <FieldGroup className="gap-4">
                    <input type="hidden" name="token" value={form.data.token} />
                    <Field className="gap-2">
                        <FieldLabel htmlFor="email" className="leading-5">
                            Email address
                        </FieldLabel>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            value={form.data.email}
                            autoComplete="username"
                            required
                            aria-invalid={form.errors.email ? true : undefined}
                            onChange={(event) => form.setData('email', event.target.value)}
                        />
                        <FieldError>{form.errors.email}</FieldError>
                    </Field>
                    <PasswordField
                        id="password"
                        name="password"
                        label="New password"
                        value={form.data.password}
                        error={form.errors.password}
                        autoComplete="new-password"
                        onChange={(value) => form.setData('password', value)}
                    />
                    <PasswordField
                        id="password_confirmation"
                        name="password_confirmation"
                        label="Confirm password"
                        value={form.data.password_confirmation}
                        error={form.errors.password_confirmation}
                        autoComplete="new-password"
                        onChange={(value) => form.setData('password_confirmation', value)}
                    />
                    <Field>
                        <Button className="w-full" type="submit" disabled={form.processing} loading={form.processing}>
                            Set new password
                        </Button>
                    </Field>
                </FieldGroup>
            </form>
        </AuthCard>
    );
}

ResetPassword.layout = (page: ReactNode) => <GuestLayout title="Reset password">{page}</GuestLayout>;
