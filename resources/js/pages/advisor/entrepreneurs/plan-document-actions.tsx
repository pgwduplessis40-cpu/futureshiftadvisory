import { Banknote, Download, FileText } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    previewUrl: string;
    previewDownloadUrl: string;
    budgetUrl: string | null;
    budgetDownloadUrl: string | null;
};

export function PlanDocumentActions({
    previewUrl,
    previewDownloadUrl,
    budgetUrl,
    budgetDownloadUrl,
}: Props) {
    return (
        <>
            <Button asChild size="sm" variant="outline">
                <a href={previewUrl} target="_blank" rel="noreferrer">
                    <FileText className="size-4" aria-hidden="true" />
                    View business plan PDF
                </a>
            </Button>
            <Button asChild size="sm" variant="outline">
                <a href={previewDownloadUrl} download>
                    <Download className="size-4" aria-hidden="true" />
                    Download plan PDF
                </a>
            </Button>
            {budgetUrl && budgetDownloadUrl ? (
                <>
                    <Button asChild size="sm" variant="outline">
                        <a href={budgetUrl} target="_blank" rel="noreferrer">
                            <Banknote className="size-4" aria-hidden="true" />
                            View budget PDF
                        </a>
                    </Button>
                    <Button asChild size="sm" variant="outline">
                        <a href={budgetDownloadUrl} download>
                            <Download className="size-4" aria-hidden="true" />
                            Download budget PDF
                        </a>
                    </Button>
                </>
            ) : null}
        </>
    );
}
