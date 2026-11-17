import type { LucideIcon } from '@lucide/vue';
import {
    BookOpen,
    ChartColumn,
    Circle,
    GraduationCap,
    LayoutDashboard,
    Megaphone,
    School,
    Settings,
    SquareCheckBig,
} from '@lucide/vue';

/** Icon keys used in config/navigation.php. */
const navIcons: Record<string, LucideIcon> = {
    dashboard: LayoutDashboard,
    admission: GraduationCap,
    tasks: SquareCheckBig,
    school: School,
    marketing: Megaphone,
    majors: BookOpen,
    reports: ChartColumn,
    settings: Settings,
};

export function navIcon(key: string | null): LucideIcon {
    return (key && navIcons[key]) || Circle;
}

/** True when `url` is the current path or one of its sub-pages (/cases → /cases/123). */
export function isActivePath(url: string | null, currentPath: string): boolean {
    if (!url) {
        return false;
    }

    return currentPath === url || currentPath.startsWith(`${url}/`);
}
