import { router, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

import AuthCard from '@/components/auth/AuthCard';
import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp';
import { route } from '@/lib/route';

export default function VerifyOtp({ maskedEmail }: { maskedEmail: string }) {
    const form = useForm({
        code: '',
    });

    return (
        <AuthCard title="Verify sign in" description={`Enter the 4-digit code sent to ${maskedEmail}.`}>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(route('login.verify-otp.store'));
                }}
            >
                <FieldGroup className="gap-4">
                    <Field className="gap-4">
                        <FieldLabel htmlFor="code" className="text-base">
                            Verification code
                        </FieldLabel>
                        <InputOTP
                            id="code"
                            maxLength={4}
                            value={form.data.code}
                            onChange={(value) => form.setData('code', value.replace(/\D/g, '').slice(0, 4))}
                            autoFocus
                        >
                            <InputOTPGroup className="w-full justify-center gap-4 *:data-[slot=input-otp-slot]:rounded-lg *:data-[slot=input-otp-slot]:border">
                                <InputOTPSlot index={0} className="input-size-lg" />
                                <InputOTPSlot index={1} className="input-size-lg" />
                                <InputOTPSlot index={2} className="input-size-lg" />
                                <InputOTPSlot index={3} className="input-size-lg" />
                            </InputOTPGroup>
                        </InputOTP>
                        <FieldError>{form.errors.code}</FieldError>
                    </Field>
                    <Field>
                        <Button size="lg" className="w-full" type="submit" disabled={form.processing || form.data.code.length !== 4} loading={form.processing}>
                            Verify & sign in
                        </Button>
                    </Field>
                </FieldGroup>
            </form>
            <div className="mt-6 flex items-center justify-between gap-3 text-sm">
                <Button
                    type="button"
                    variant="link"
                    className="text-muted-foreground h-auto px-0"
                    data-action="resend-code"
                    onClick={() => router.post(route('login.verify-otp.resend'))}
                >
                    Resend code
                </Button>
                <a href={route('login.verify-otp.cancel')} className="text-muted-foreground hover:text-foreground">
                    Back to login
                </a>
            </div>
        </AuthCard>
    );
}

VerifyOtp.layout = (page: ReactNode) => <GuestLayout title="Verify sign in">{page}</GuestLayout>;
