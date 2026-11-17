import type { Directive } from 'vue';
import type { Auth } from '@/types/auth';
import type {
    BreadcrumbItem,
    CrmNavItem,
    CycleOption,
} from '@/types/navigation';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            locale: string;
            timezone: string;
            auth: Auth;
            navigation: CrmNavItem[];
            /** Set by pages rendered straight from routes (e.g. placeholders). */
            breadcrumbs?: BreadcrumbItem[];
            /** Shared from task 1.5 (admission cycle selector). */
            cycles?: CycleOption[];
            currentCycle?: CycleOption | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }

    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
