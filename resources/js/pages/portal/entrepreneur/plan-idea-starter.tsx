import type { Dispatch, SetStateAction } from 'react';
import { Button } from '@/components/ui/button';
export function PlanIdeaStarter({
    ideaStarter,
    planChangesLocked,
    setSectionBody,
}: {
    ideaStarter: string | null;
    planChangesLocked: boolean;
    setSectionBody: Dispatch<SetStateAction<string>>;
}) {
    return (
        <>
            {' '}
            {ideaStarter ? (
                <div className="rounded-md border border-sky-200 bg-sky-50 p-3 text-sm text-sky-950">
                    <div className="font-medium">From your validated idea</div>
                    <p className="mt-1 whitespace-pre-line text-sky-900">
                        {ideaStarter}
                    </p>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="mt-3 border-sky-300 bg-white"
                        disabled={planChangesLocked}
                        onClick={() =>
                            setSectionBody((current) =>
                                current.trim()
                                    ? `${current}\n\n${ideaStarter}`
                                    : ideaStarter,
                            )
                        }
                    >
                        Use as a starting point
                    </Button>
                </div>
            ) : null}
        </>
    );
}
