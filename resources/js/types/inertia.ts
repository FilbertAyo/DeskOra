export type Branding = {
    appName: string;
    productName: string;
    logo: string;
    logoIcon: string;
};

export type AuthUser = {
    id: number | string;
    name: string;
    email: string;
    status: string;
    isDemo: boolean;
    avatar: string;
    initials: string;
    permissions: string[];
};

export type NavItem = {
    label: string;
    href: string | null;
    icon: string | null;
    active: boolean;
    badge: string | null;
    children: NavItem[] | null;
};

export type NavGroup = {
    label: string | null;
    items: NavItem[];
};

export type CurrentPeriod = {
    week_number: number | string | null;
    date_range: string;
    status: string;
};

export type NotificationItem = {
    id: string;
    title: string;
    message: string;
    time: string;
    href: string;
};

export type Flash = {
    success?: string | null;
    error?: string | null;
    warning?: string | null;
    info?: string | null;
    status?: string | null;
};

export type SharedProps = {
    branding: Branding;
    auth: { user: AuthUser | null };
    nav: { groups: NavGroup[] };
    currentPeriod: CurrentPeriod | null;
    notifications: {
        unread: number;
        canManage: boolean;
        items: NotificationItem[];
    };
    flash: Flash;
    overlays: {
        demo: boolean;
        approvalGate: {
            requisition_count: number;
            budget_remaining: number;
            requisition_action_url: string | null;
            budget_action_url: string | null;
            requisitions: Array<{
                url: string;
                summary: string;
                ref: string;
                requester_name: string | null;
                department_name: string | null;
                amount: string;
                stage_label: string;
            }>;
        } | null;
        nomination: { periodId: string; label: string } | null;
        anonymousFeedback: { count: number; hideOnCurrentPage: boolean } | null;
    };
};
