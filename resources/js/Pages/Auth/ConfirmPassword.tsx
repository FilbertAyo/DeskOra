import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AuthCard from '@/components/auth/AuthCard';
import PasswordField from '@/components/auth/PasswordField';
import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/components/ui/button';
import { Field, FieldGroup } from '@/components/ui/field';
import { route } from '@/lib/route';

export default function ConfirmPassword() {
    const form = useForm({
        password: '',
    });

    return (
        <AuthCard title="Confirm password" description="This is a secure area. Please confirm your password before continuing.">
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(route('password.confirm'));
                }}
            >
                <FieldGroup className="gap-4">
                    <PasswordField
                        id="password"
                        name="password"
                        label="Password"
                        value={form.data.password}
                        error={form.errors.password}
                        autoComplete="current-password"
                        onChange={(value) => form.setData('password', value)}
                    />
                    <Field>
                        <Button className="w-full" type="submit" disabled={form.processing} loading={form.processing}>
                            Confirm
                        </Button>
                    </Field>
                </FieldGroup>
            </form>
        </AuthCard>
    );
}

ConfirmPassword.layout = (page: ReactNode) => <GuestLayout title="Confirm password">{page}</GuestLayout>;
