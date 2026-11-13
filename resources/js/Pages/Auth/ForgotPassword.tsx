import { Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AuthCard from '@/components/auth/AuthCard';
import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { route } from '@/lib/route';

export default function ForgotPassword() {
    const form = useForm({
        email: '',
    });

    return (
        <AuthCard
            title="Forgot password"
            description="Enter your email address and we'll send you a link to reset your password."
        >
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(route('password.email'));
                }}
            >
                <FieldGroup className="gap-4">
                    <Field className="gap-2">
                        <FieldLabel htmlFor="email" className="leading-5">
                            Email address
                        </FieldLabel>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            placeholder="Enter your email address"
                            value={form.data.email}
                            autoComplete="username"
                            autoFocus
                            required
                            aria-invalid={form.errors.email ? true : undefined}
                            onChange={(event) => form.setData('email', event.target.value)}
                        />
                        <FieldError>{form.errors.email}</FieldError>
                    </Field>
                    <Field>
                        <Button className="w-full" type="submit" disabled={form.processing} loading={form.processing}>
                            Send reset link
                        </Button>
                    </Field>
                </FieldGroup>
            </form>
            <div className="mt-6 text-center">
                <Link href={route('login')} className="text-muted-foreground text-sm hover:underline">
                    Back to login
                </Link>
            </div>
        </AuthCard>
    );
}

ForgotPassword.layout = (page: ReactNode) => <GuestLayout title="Forgot password">{page}</GuestLayout>;
