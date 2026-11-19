import { useForm, router } from '@inertiajs/react';
import { ImageIcon, Palette, Printer, Type } from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PageHeader } from '@/lib/page-kit';
import { notify } from '@/lib/notify';
import { cn } from '@/lib/utils';
import { route } from '@/lib/route';

type SectionId = 'identity' | 'branding' | 'appearance' | 'forms';

type SectionMeta = {
    id: SectionId;
    label: string;
    description: string;
};

type Props = {
    section: SectionId;
    sections: SectionMeta[];
    identity: { appName: string };
    brandingAssets: {
        logoUrl: string;
        iconUrl: string;
        usingDefaultLogo: boolean;
        usingDefaultIcon: boolean;
    };
    appearance: { primaryColor: string; defaultPrimaryColor: string };
    forms: {
        formsLogoPath: string;
        formsLogoUrl: string;
        usingDefaultLogo: boolean;
    };
    updateUrl: string;
};

const SECTION_ICONS: Record<SectionId, ComponentType<{ className?: string }>> = {
    identity: Type,
    branding: ImageIcon,
    appearance: Palette,
    forms: Printer,
};

function IdentityPanel({ appName, updateUrl }: { appName: string; updateUrl: string }) {
    const form = useForm({ section: 'identity' as const, app_name: appName });

    return (
        <Card className="shadow-none">
            <CardHeader>
                <CardTitle>System name</CardTitle>
                <CardDescription>This name appears in the sidebar, footer, login screen, and page titles.</CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    className="max-w-lg space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(updateUrl, {
                            preserveScroll: true,
                            onSuccess: () => notify('success', 'System name updated.'),
                        });
                    }}
                >
                    <div className="space-y-2">
                        <Label htmlFor="app_name">Display name</Label>
                        <Input
                            id="app_name"
                            name="app_name"
                            value={form.data.app_name}
                            onChange={(event) => form.setData('app_name', event.target.value)}
                            placeholder="Adilisha Portal"
                            maxLength={120}
                        />
                        {form.errors.app_name && <p className="text-destructive text-sm">{form.errors.app_name}</p>}
                    </div>
                    <Button type="submit" disabled={form.processing} loading={form.processing}>
                        Save identity
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function BrandingPanel({
    logoUrl,
    iconUrl,
    usingDefaultLogo,
    usingDefaultIcon,
    updateUrl,
}: {
    logoUrl: string;
    iconUrl: string;
    usingDefaultLogo: boolean;
    usingDefaultIcon: boolean;
    updateUrl: string;
}) {
    const form = useForm<{
        section: 'branding';
        logo: File | null;
        icon: File | null;
        remove_logo: boolean;
        remove_icon: boolean;
    }>({
        section: 'branding',
        logo: null,
        icon: null,
        remove_logo: false,
        remove_icon: false,
    });

    return (
        <Card className="shadow-none">
            <CardHeader>
                <CardTitle>Logos & media</CardTitle>
                <CardDescription>Upload the organization photo and square icon used across the portal.</CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    className="space-y-8"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(updateUrl, {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => {
                                form.setData({
                                    section: 'branding',
                                    logo: null,
                                    icon: null,
                                    remove_logo: false,
                                    remove_icon: false,
                                });
                                notify('success', 'Branding assets updated.');
                            },
                        });
                    }}
                >
                    <div className="grid gap-6 md:grid-cols-[12rem_1fr]">
                        <div className="flex items-center justify-center rounded-md border bg-muted/30 p-4">
                            <img src={logoUrl} alt="System photo" className="max-h-28 object-contain" />
                        </div>
                        <div className="space-y-3">
                            <div>
                                <Label htmlFor="logo">System photo / logo</Label>
                                <p className="text-muted-foreground mt-1 text-xs">Shown on login and branded surfaces. Prefer a clear landscape or square image.</p>
                            </div>
                            <Input
                                id="logo"
                                name="logo"
                                type="file"
                                accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                onChange={(event) => form.setData('logo', event.target.files?.[0] ?? null)}
                            />
                            {form.errors.logo && <p className="text-destructive text-sm">{form.errors.logo}</p>}
                            {!usingDefaultLogo && (
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        name="remove_logo"
                                        checked={form.data.remove_logo}
                                        onCheckedChange={(checked) => form.setData('remove_logo', checked === true)}
                                    />
                                    Reset to default photo
                                </label>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-6 md:grid-cols-[12rem_1fr]">
                        <div className="flex items-center justify-center rounded-md border bg-muted/30 p-4">
                            <img src={iconUrl} alt="System icon" className="size-16 object-contain" />
                        </div>
                        <div className="space-y-3">
                            <div>
                                <Label htmlFor="icon">System icon</Label>
                                <p className="text-muted-foreground mt-1 text-xs">Square mark for the sidebar and browser tab (favicon).</p>
                            </div>
                            <Input
                                id="icon"
                                name="icon"
                                type="file"
                                accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                onChange={(event) => form.setData('icon', event.target.files?.[0] ?? null)}
                            />
                            {form.errors.icon && <p className="text-destructive text-sm">{form.errors.icon}</p>}
                            {!usingDefaultIcon && (
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        name="remove_icon"
                                        checked={form.data.remove_icon}
                                        onCheckedChange={(checked) => form.setData('remove_icon', checked === true)}
                                    />
                                    Reset to default icon
                                </label>
                            )}
                        </div>
                    </div>

                    <Button type="submit" disabled={form.processing} loading={form.processing}>
                        Save branding
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function AppearancePanel({
    primaryColor,
    defaultPrimaryColor,
    updateUrl,
}: {
    primaryColor: string;
    defaultPrimaryColor: string;
    updateUrl: string;
}) {
    const form = useForm({ section: 'appearance' as const, primary_color: primaryColor });

    return (
        <Card className="shadow-none">
            <CardHeader>
                <CardTitle>Appearance</CardTitle>
                <CardDescription>Choose the primary accent used for buttons, links, and highlights.</CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    className="max-w-lg space-y-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(updateUrl, {
                            preserveScroll: true,
                            onSuccess: () => notify('success', 'Appearance updated.'),
                        });
                    }}
                >
                    <div className="space-y-3">
                        <Label htmlFor="primary_color">Primary color</Label>
                        <div className="flex flex-wrap items-center gap-3">
                            <Input
                                id="primary_color_picker"
                                type="color"
                                className="h-10 w-14 cursor-pointer p-1"
                                value={form.data.primary_color}
                                onChange={(event) => form.setData('primary_color', event.target.value)}
                            />
                            <Input
                                id="primary_color"
                                name="primary_color"
                                value={form.data.primary_color}
                                onChange={(event) => form.setData('primary_color', event.target.value)}
                                placeholder={defaultPrimaryColor}
                                className="max-w-40 font-mono uppercase"
                                maxLength={7}
                            />
                            <span
                                className="inline-flex h-10 items-center rounded-md px-3 text-sm font-medium text-white"
                                style={{ backgroundColor: form.data.primary_color }}
                            >
                                Preview
                            </span>
                        </div>
                        {form.errors.primary_color && <p className="text-destructive text-sm">{form.errors.primary_color}</p>}
                        <button
                            type="button"
                            className="text-muted-foreground hover:text-foreground text-xs underline-offset-2 hover:underline"
                            onClick={() => form.setData('primary_color', defaultPrimaryColor)}
                        >
                            Reset to default ({defaultPrimaryColor})
                        </button>
                    </div>
                    <Button type="submit" disabled={form.processing} loading={form.processing}>
                        Save appearance
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function FormsPanel({
    formsLogoUrl,
    usingDefaultLogo,
    updateUrl,
}: {
    formsLogoUrl: string;
    usingDefaultLogo: boolean;
    updateUrl: string;
}) {
    const form = useForm<{ section: 'forms'; forms_logo: File | null; remove_forms_logo: boolean }>({
        section: 'forms',
        forms_logo: null,
        remove_forms_logo: false,
    });

    return (
        <Card className="shadow-none">
            <CardHeader>
                <CardTitle>Forms logo</CardTitle>
                <CardDescription>
                    {usingDefaultLogo
                        ? 'Currently using the default application logo on printable and downloadable forms.'
                        : 'A custom forms logo is active on printable and downloadable forms.'}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    className="grid gap-6 md:grid-cols-[16rem_1fr]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(updateUrl, {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => {
                                form.setData({ section: 'forms', forms_logo: null, remove_forms_logo: false });
                                notify('success', 'System settings updated.');
                            },
                        });
                    }}
                >
                    <div className="rounded-md border bg-muted/30 p-4">
                        <img src={formsLogoUrl} alt="Forms logo" className="mx-auto max-h-32 object-contain" />
                    </div>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="forms_logo">Upload new logo</Label>
                            <Input
                                id="forms_logo"
                                name="forms_logo"
                                type="file"
                                accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                onChange={(event) => form.setData('forms_logo', event.target.files?.[0] ?? null)}
                            />
                            {form.errors.forms_logo && <p className="text-destructive text-sm">{form.errors.forms_logo}</p>}
                        </div>
                        {!usingDefaultLogo && (
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    name="remove_forms_logo"
                                    checked={form.data.remove_forms_logo}
                                    onCheckedChange={(checked) => form.setData('remove_forms_logo', checked === true)}
                                />
                                Reset to default logo
                            </label>
                        )}
                        <Button type="submit" disabled={form.processing} loading={form.processing}>
                            Save changes
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function renderSection(
    section: SectionId,
    props: Pick<Props, 'identity' | 'brandingAssets' | 'appearance' | 'forms' | 'updateUrl'>,
): ReactNode {
    switch (section) {
        case 'identity':
            return <IdentityPanel appName={props.identity.appName} updateUrl={props.updateUrl} />;
        case 'branding':
            return (
                <BrandingPanel
                    logoUrl={props.brandingAssets.logoUrl}
                    iconUrl={props.brandingAssets.iconUrl}
                    usingDefaultLogo={props.brandingAssets.usingDefaultLogo}
                    usingDefaultIcon={props.brandingAssets.usingDefaultIcon}
                    updateUrl={props.updateUrl}
                />
            );
        case 'appearance':
            return (
                <AppearancePanel
                    primaryColor={props.appearance.primaryColor}
                    defaultPrimaryColor={props.appearance.defaultPrimaryColor}
                    updateUrl={props.updateUrl}
                />
            );
        case 'forms':
            return (
                <FormsPanel
                    formsLogoUrl={props.forms.formsLogoUrl}
                    usingDefaultLogo={props.forms.usingDefaultLogo}
                    updateUrl={props.updateUrl}
                />
            );
        default: {
            const _exhaustive: never = section;
            return _exhaustive;
        }
    }
}

export default function SystemEdit({
    section,
    sections,
    identity,
    brandingAssets,
    appearance,
    forms,
    updateUrl,
}: Props) {
    const goToSection = (id: SectionId) => {
        router.get(route('settings.system.edit'), { section: id }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <div className="space-y-6">
            <PageHeader
                title="System configuration"
                description="Control how the portal looks and how organization branding appears across the system."
            />

            <div className="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
                <nav aria-label="System settings" className="lg:sticky lg:top-20 lg:self-start">
                    <ul className="flex gap-1 overflow-x-auto lg:flex-col lg:overflow-visible">
                        {sections.map((item) => {
                            const Icon = SECTION_ICONS[item.id];
                            const active = section === item.id;

                            return (
                                <li key={item.id} className="min-w-0 shrink-0 lg:w-full">
                                    <button
                                        type="button"
                                        onClick={() => goToSection(item.id)}
                                        className={cn(
                                            'flex w-full items-start gap-3 rounded-lg px-3 py-2.5 text-left transition-colors',
                                            active
                                                ? 'bg-primary/10 text-primary'
                                                : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                                        )}
                                    >
                                        <Icon className="mt-0.5 size-4 shrink-0" />
                                        <span className="min-w-0">
                                            <span className="block text-sm font-medium">{item.label}</span>
                                            <span className="text-muted-foreground mt-0.5 hidden text-xs lg:block">{item.description}</span>
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                    <p className="text-muted-foreground mt-4 hidden text-xs lg:block">
                        More system options can be added here later — email, locale, and document defaults.
                    </p>
                </nav>

                <div className="min-w-0">{renderSection(section, { identity, brandingAssets, appearance, forms, updateUrl })}</div>
            </div>
        </div>
    );
}

SystemEdit.layout = (page: ReactNode) => <AppLayout title="System configuration">{page}</AppLayout>;
