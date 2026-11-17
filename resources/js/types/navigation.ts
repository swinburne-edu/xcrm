import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    /** Omitted for group crumbs (e.g. "Tuyển sinh") that have no page of their own. */
    href?: NonNullable<InertiaLinkProps['href']>;
};

/** CRM sidebar entry, shared by the server from config/navigation.php. */
export type CrmNavChild = {
    title: string;
    url: string;
    phase: string | null;
    placeholder: boolean;
};

export type CrmNavItem = {
    title: string;
    url: string | null;
    icon: string | null;
    phase: string | null;
    placeholder: boolean;
    children: CrmNavChild[];
};

/** Admission cycle option for the header selector (filled from task 1.5). */
export type CycleOption = {
    id: number;
    name: string;
    is_current?: boolean;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
};
