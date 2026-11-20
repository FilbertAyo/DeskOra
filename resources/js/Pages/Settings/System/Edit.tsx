import { useForm, router } from '@inertiajs/react';
import { ImageIcon, Type } from 'lucide-react';
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

type SectionId = 'brand' | 'logos';

type SectionMeta = {
    id: SectionId;
    label: string;
    description: string;
};

type Props = {
    section: SectionId;
    sections: SectionMeta[];
    brand: {
        appName: string;
        primaryColor: string;
        defaultPrimaryColor: string;
    };
    logos: {
        logoUrl: string;
        iconUrl: string;
        usingDefaultLogo: boolean;
        usingDefaultIcon: boolean;
        formsLogoUrl: string;
        usingDefaultFormsLogo: boolean;
    };
    updateUrl: string;
};

const SECTION_ICONS: Record<SectionId, ComponentType<{ className?: string }>> = {
    brand: Type,
    logos: ImageIcon,
};

function BrandPanel({
    appName,
    primaryColor,
    defaultPrimaryColor,
    updateUrl,
}: {
    appName: string;
    primaryColor: string;
    defaultPrimaryColor: string;
    updateUrl: string;
}) {
    const form = useForm({
        section: 'brand' as const,
        app_name: appName,
        primary_color: primaryColor,
    });

    return (
        <Card className="shadow-none">
            <CardHeader>
                <CardTitle>Brand</CardTitle>
                <CardDescription>System name and accent color used across the portal.</CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    className="max-w-lg space-y-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(updateUrl, {
                            preserveScroll: true,
                            onSuccess: () => notify('success', 'Brand settings updated.'),
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
                        <p className="text-muted-foreground text-xs">Shown in the sidebar, footer, login screen, and page titles.</p>
                        {form.errors.app_name && <p className="text-destructive text-sm">{form.errors.app_name}</p>}
                    </div>

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
                        Save brand
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function LogosPanel({
    logoUrl,
    iconUrl,
    usingDefaultLogo,
    usingDefaultIcon,
    formsLogoUrl,
    usingDefaultFormsLogo,
    updateUrl,
}: {
    logoUrl: string;
    iconUrl: string;
    usingDefaultLogo: boolean;
    usingDefaultIcon: boolean;
    formsLogoUrl: string;
    usingDefaultFormsLogo: boolean;
    updateUrl: string;
}) {
    const form = useForm<{
        section: 'logos';
        logo: File | null;
        icon: File | null;
        forms_logo: File | null;
        remove_logo: boolean;
        remove_icon: boolean;
        remove_forms_logo: boolean;
    }>({
        section: 'logos',
        logo: null,
        icon: null,
        forms_logo: null,
        remove_logo: false,
        remove_icon: false,
        remove_forms_logo: false,
    });

    return (
        <Card className="shadow-none">
            <CardHeader>
                <CardTitle>Logos</CardTitle>
                <CardDescription>
                    Manage the portal icon, organization photo, and forms logo used on downloads and printable exports.
                </CardDescription>
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
                                    section: 'logos',
                                    logo: null,
                                    icon: null,
                                    forms_logo: null,
                                    remove_logo: false,
                                    remove_icon: false,
                                    remove_forms_logo: false,
                                });
                                notify('success', 'Logo settings updated.');
                            },
                        });
                    }}
                >
                    <div className="grid gap-6 md:grid-cols-[12rem_1fr]">
                        <div className="flex items-center justify-center rounded-md border bg-muted/30 p-4">
                            <img src={iconUrl} alt="System icon" className="size-16 object-contain" />
                        </div>
                        <div className="space-y-3">
                            <div>
                                <Label htmlFor="icon">System icon</Label>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Square mark for the sidebar, browser tab, and authentication pages.
                                </p>
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

                    <div className="grid gap-6 md:grid-cols-[12rem_1fr]">
                        <div className="flex items-center justify-center rounded-md border bg-muted/30 p-4">
                            <img src={logoUrl} alt="System photo" className="max-h-28 object-contain" />
                        </div>
                        <div className="space-y-3">
                            <div>
                                <Label htmlFor="logo">System photo / logo</Label>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Organization photo or full logo for branded surfaces.
                                </p>
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
                            <img src={formsLogoUrl} alt="Forms logo" className="max-h-28 object-contain" />
                        </div>
                        <div className="space-y-3">
                            <div>
                                <Label htmlFor="forms_logo">Forms & downloads logo</Label>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Used on printable and downloadable forms such as requisitions.
                                </p>
                            </div>
                            <Input
                                id="forms_logo"
                                name="forms_logo"
                                type="file"
                                accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                onChange={(event) => form.setData('forms_logo', event.target.files?.[0] ?? null)}
                            />
                            {form.errors.forms_logo && <p className="text-destructive text-sm">{form.errors.forms_logo}</p>}
                            {!usingDefaultFormsLogo && (
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        name="remove_forms_logo"
                                        checked={form.data.remove_forms_logo}
                                        onCheckedChange={(checked) => form.setData('remove_forms_logo', checked === true)}
                                    />
                                    Reset to default forms logo
                                </label>
                            )}
                        </div>
                    </div>

                    <Button type="submit" disabled={form.processing} loading={form.processing}>
                        Save logos
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function renderSection(section: SectionId, props: Pick<Props, 'brand' | 'logos' | 'updateUrl'>): ReactNode {
    switch (section) {
        case 'brand':
            return (
                <BrandPanel
                    appName={props.brand.appName}
                    primaryColor={props.brand.primaryColor}
                    defaultPrimaryColor={props.brand.defaultPrimaryColor}
                    updateUrl={props.updateUrl}
                />
            );
        case 'logos':
            return (
                <LogosPanel
                    logoUrl={props.logos.logoUrl}
                    iconUrl={props.logos.iconUrl}
                    usingDefaultLogo={props.logos.usingDefaultLogo}
                    usingDefaultIcon={props.logos.usingDefaultIcon}
                    formsLogoUrl={props.logos.formsLogoUrl}
                    usingDefaultFormsLogo={props.logos.usingDefaultFormsLogo}
                    updateUrl={props.updateUrl}
                />
            );
        default: {
            const _exhaustive: never = section;
            return _exhaustive;
        }
    }
}

export default function SystemEdit({ section, sections, brand, logos, updateUrl }: Props) {
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
                </nav>

                <div className="min-w-0">{renderSection(section, { brand, logos, updateUrl })}</div>
            </div>
        </div>
    );
}

SystemEdit.layout = (page: ReactNode) => <AppLayout title="System configuration">{page}</AppLayout>;
