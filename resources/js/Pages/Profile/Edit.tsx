import { router, useForm } from '@inertiajs/react';
import {
    BriefcaseBusinessIcon,
    CalendarDaysIcon,
    ChartNoAxesColumnIncreasingIcon,
    CheckCheckIcon,
    MailIcon,
    MapPinIcon,
    PhoneIcon,
    SettingsIcon,
    StarIcon,
    UserIcon,
    UserRoundCheckIcon,
} from 'lucide-react';
import { useMemo, useState, type FormEvent, type ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { BackgroundRippleEffect } from '@/components/ui/background-ripple';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Timeline,
    TimelineContent,
    TimelineDot,
    TimelineHeading,
    TimelineItem,
    TimelineLine,
} from '@/components/ui/timeline';
import { notify } from '@/lib/notify';
import { route } from '@/lib/route';

type Profile = {
    id: string | number;
    name: string;
    email: string;
    phone: string | null;
    notification_channel: string;
    avatar: string;
    initials: string;
    signature: string | null;
    department: string | null;
    role: string;
    status: string;
    member_since: string | null;
    joined_label: string | null;
};

type ActivityItem = {
    id: string | number;
    title: string;
    description?: string | null;
    time?: string | null;
};

type Props = {
    profile: Profile;
    activities?: ActivityItem[];
    tab?: string;
    urls: { update: string; image: string; password?: string };
};

export default function ProfileEdit({ profile, activities = [], tab = 'profile', urls }: Props) {
    const initialTab = tab === 'settings' ? 'settings' : 'profile';
    const [activeTab, setActiveTab] = useState(initialTab);

    const details = useForm({
        name: profile.name,
        email: profile.email,
        phone: profile.phone ?? '',
        notification_channel: profile.notification_channel,
    });
    const image = useForm<{ profile_image: File | null }>({ profile_image: null });
    const signature = useForm<{ signature: File | null }>({ signature: null });
    const password = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const aboutItems = useMemo(
        () => [
            { label: 'Full Name', value: profile.name, icon: UserIcon },
            { label: 'Status', value: profile.status, icon: CheckCheckIcon },
            { label: 'Role', value: profile.role, icon: StarIcon },
            { label: 'Department', value: profile.department ?? '—', icon: MapPinIcon },
            { label: 'Email', value: profile.email, icon: MailIcon },
            { label: 'Phone', value: profile.phone || '—', icon: PhoneIcon },
        ],
        [profile],
    );

    const submitUpload = (event: FormEvent, kind: 'image' | 'signature') => {
        event.preventDefault();
        const form = kind === 'image' ? image : signature;
        form.post(urls.image, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                notify('success', kind === 'image' ? 'Profile image updated.' : 'Signature updated.');
            },
        });
    };

    const switchTab = (value: string) => {
        setActiveTab(value);
        router.get(
            route('profile.edit'),
            value === 'profile' ? {} : { tab: value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <div>
            <div className="mb-4 md:mb-6 lg:mb-10">
                <Card className="py-0 pb-6">
                    <div className="bg-muted relative h-44 overflow-hidden">
                        <div className="absolute inset-0">
                            <BackgroundRippleEffect cellSize={45} rows={8} activeSquares={18} cols={45} />
                        </div>
                    </div>

                    <CardContent>
                        <div className="flex items-end gap-4 pb-1 max-md:flex-col max-md:items-center md:flex-nowrap md:gap-6">
                            <Avatar className="ring-card z-3 -mt-12 size-28 rounded-md ring-4 after:rounded-[inherit] md:-mt-14">
                                <AvatarImage src={profile.avatar} alt={profile.name} className="rounded-[inherit]" />
                                <AvatarFallback className="rounded-[inherit] text-2xl">{profile.initials}</AvatarFallback>
                            </Avatar>

                            <div className="min-w-0 flex-1 space-y-2 text-center md:text-left">
                                <h2 className="text-2xl font-medium">{profile.name}</h2>
                                <div className="text-muted-foreground flex flex-wrap items-center gap-x-6 gap-y-2 max-md:justify-center">
                                    <span className="inline-flex items-center gap-2">
                                        <BriefcaseBusinessIcon className="size-4.5" />
                                        {profile.role}
                                    </span>
                                    <span className="inline-flex items-center gap-2">
                                        <MapPinIcon className="size-4.5" />
                                        {profile.department ?? 'No department'}
                                    </span>
                                    <span className="inline-flex items-center gap-2">
                                        <CalendarDaysIcon className="size-4.5" />
                                        {profile.joined_label ?? profile.member_since ?? '—'}
                                    </span>
                                </div>
                            </div>

                            <Button
                                className="md:ml-auto md:self-end"
                                variant={profile.status.toLowerCase() === 'active' ? 'default' : 'outline'}
                                onClick={() => switchTab('settings')}
                            >
                                <UserRoundCheckIcon className="size-4" />
                                {profile.status}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Tabs value={activeTab} onValueChange={switchTab} className="gap-4">
                <TabsList className="max-sm:w-full">
                    <TabsTrigger value="profile">
                        <UserIcon className="size-4" />
                        Profile
                    </TabsTrigger>
                    <TabsTrigger value="settings">
                        <SettingsIcon className="size-4" />
                        Settings
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="profile">
                    <div className="grid grid-cols-12 gap-6">
                        <div className="col-span-12 space-y-6 lg:col-span-4">
                            <Card>
                                <CardContent className="space-y-5 pt-6">
                                    <div className="space-y-2">
                                        <p className="text-muted-foreground text-xs font-medium uppercase">About</p>
                                        <ul className="space-y-3">
                                            {aboutItems.map((item) => {
                                                const Icon = item.icon;
                                                return (
                                                    <li className="flex items-center gap-2" key={item.label}>
                                                        <Icon className="size-4 shrink-0" />
                                                        <span className="text-sm font-medium">{item.label}:</span>
                                                        <span className="truncate text-sm">{item.value}</span>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div className="col-span-12 lg:col-span-8">
                            <Card>
                                <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                                    <ChartNoAxesColumnIncreasingIcon className="size-4" />
                                    <CardTitle className="text-base">Activity Timeline</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {activities.length === 0 ? (
                                        <p className="text-muted-foreground text-sm">No recent activity yet.</p>
                                    ) : (
                                        <Timeline positions="left">
                                            {activities.map((item, index) => (
                                                <TimelineItem key={item.id} status="done">
                                                    <TimelineHeading className="flex w-full items-center justify-between gap-3">
                                                        <span>{item.title}</span>
                                                        {item.time && (
                                                            <span className="text-muted-foreground shrink-0 text-xs font-normal">
                                                                {item.time}
                                                            </span>
                                                        )}
                                                    </TimelineHeading>
                                                    <TimelineDot status="current" />
                                                    {index < activities.length - 1 && <TimelineLine done />}
                                                    <TimelineContent>
                                                        {item.description && (
                                                            <p className="text-muted-foreground text-sm">{item.description}</p>
                                                        )}
                                                    </TimelineContent>
                                                </TimelineItem>
                                            ))}
                                        </Timeline>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </TabsContent>

                <TabsContent value="settings">
                    <div className="grid gap-6 lg:grid-cols-[18rem_1fr]">
                        <Card>
                            <CardHeader>
                                <CardTitle>Profile photo</CardTitle>
                                <CardDescription>Shown across DeskOra.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4 text-center">
                                <img src={profile.avatar} alt="" className="mx-auto size-36 rounded-md object-cover" />
                                <form onSubmit={(event) => submitUpload(event, 'image')} className="space-y-3 text-left">
                                    <Label htmlFor="profile_image">Choose image</Label>
                                    <Input
                                        id="profile_image"
                                        name="profile_image"
                                        type="file"
                                        accept="image/jpeg,image/png,image/jpg,image/gif"
                                        onChange={(event) => image.setData('profile_image', event.target.files?.[0] ?? null)}
                                    />
                                    {image.errors.profile_image && (
                                        <p className="text-destructive text-sm">{image.errors.profile_image}</p>
                                    )}
                                    <Button
                                        type="submit"
                                        disabled={image.processing || !image.data.profile_image}
                                        loading={image.processing}
                                    >
                                        Upload image
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Account details</CardTitle>
                                <CardDescription>Keep your contacts up to date.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form
                                    className="space-y-4"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        details.patch(urls.update, {
                                            preserveScroll: true,
                                            onSuccess: () => notify('success', 'Profile updated.'),
                                        });
                                    }}
                                >
                                    <Field label="Full name" error={details.errors.name}>
                                        <Input
                                            name="name"
                                            value={details.data.name}
                                            onChange={(event) => details.setData('name', event.target.value)}
                                            required
                                        />
                                    </Field>
                                    <Field label="Email" error={details.errors.email}>
                                        <Input
                                            name="email"
                                            type="email"
                                            value={details.data.email}
                                            onChange={(event) => details.setData('email', event.target.value)}
                                            required
                                        />
                                    </Field>
                                    <Field label="Phone" error={details.errors.phone}>
                                        <Input
                                            name="phone"
                                            value={details.data.phone}
                                            onChange={(event) => details.setData('phone', event.target.value)}
                                        />
                                    </Field>
                                    <Field label="Notification channel" error={details.errors.notification_channel}>
                                        <select
                                            name="notification_channel"
                                            className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                            value={details.data.notification_channel}
                                            onChange={(event) => details.setData('notification_channel', event.target.value)}
                                        >
                                            <option value="sms">SMS (default)</option>
                                            <option value="email">Email</option>
                                            <option value="both">Both</option>
                                        </select>
                                    </Field>
                                    <Button type="submit" disabled={details.processing} loading={details.processing}>
                                        Save changes
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="mt-6 grid gap-6 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Signature</CardTitle>
                                <CardDescription>Used on reports and approvals.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {profile.signature && (
                                    <img src={profile.signature} alt="Signature" className="max-h-48 rounded-md border p-2" />
                                )}
                                <form onSubmit={(event) => submitUpload(event, 'signature')} className="space-y-3">
                                    <Label htmlFor="signature">Signature image</Label>
                                    <Input
                                        id="signature"
                                        name="signature"
                                        type="file"
                                        accept="image/jpeg,image/png,image/jpg,image/gif"
                                        onChange={(event) => signature.setData('signature', event.target.files?.[0] ?? null)}
                                    />
                                    {signature.errors.signature && (
                                        <p className="text-destructive text-sm">{signature.errors.signature}</p>
                                    )}
                                    <Button
                                        type="submit"
                                        disabled={signature.processing || !signature.data.signature}
                                        loading={signature.processing}
                                    >
                                        Upload signature
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>

                        {urls.password && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Password</CardTitle>
                                    <CardDescription>Update your account password.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form
                                        className="space-y-4"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            password.put(urls.password!, {
                                                preserveScroll: true,
                                                onSuccess: () => {
                                                    password.reset();
                                                    notify('success', 'Password updated.');
                                                },
                                            });
                                        }}
                                    >
                                        <Field label="Current password" error={password.errors.current_password}>
                                            <Input
                                                type="password"
                                                value={password.data.current_password}
                                                onChange={(event) => password.setData('current_password', event.target.value)}
                                                autoComplete="current-password"
                                            />
                                        </Field>
                                        <Field label="New password" error={password.errors.password}>
                                            <Input
                                                type="password"
                                                value={password.data.password}
                                                onChange={(event) => password.setData('password', event.target.value)}
                                                autoComplete="new-password"
                                            />
                                        </Field>
                                        <Field label="Confirm password" error={password.errors.password_confirmation}>
                                            <Input
                                                type="password"
                                                value={password.data.password_confirmation}
                                                onChange={(event) =>
                                                    password.setData('password_confirmation', event.target.value)
                                                }
                                                autoComplete="new-password"
                                            />
                                        </Field>
                                        <Button type="submit" disabled={password.processing} loading={password.processing}>
                                            Update password
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <label className="block space-y-1.5">
            <span className="text-sm font-medium">{label}</span>
            {children}
            {error && <span className="text-destructive block text-sm">{error}</span>}
        </label>
    );
}

ProfileEdit.layout = (page: ReactNode) => <AppLayout title="My profile">{page}</AppLayout>;
