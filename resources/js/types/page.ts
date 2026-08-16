import type { AiNotice } from '@/components/ai-unavailable-notice';
import type { Auth } from '@/types/auth';

export type SharedPageProps = {
    name: string;
    publicUrl: string;
    auth: Auth;
    aiNotice?: AiNotice | null;
    sidebarOpen: boolean;
};
