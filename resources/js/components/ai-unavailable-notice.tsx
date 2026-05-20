import { AlertTriangle } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

export type AiNotice = {
    message: string;
    reason?: string;
    prompt_id?: string;
    recorded_at?: string;
};

export function AiUnavailableNotice({ notice }: { notice?: AiNotice | null }) {
    if (!notice) {
        return null;
    }

    return (
        <Alert className="mx-4 mt-4 border-amber-200 bg-amber-50 text-amber-950 md:mx-6">
            <AlertTriangle className="h-4 w-4 text-amber-700" />
            <AlertTitle>AI analysis deferred</AlertTitle>
            <AlertDescription className="text-sm text-amber-900">
                {notice.message}
                {notice.reason ? ` Reason: ${notice.reason}` : null}
            </AlertDescription>
        </Alert>
    );
}
