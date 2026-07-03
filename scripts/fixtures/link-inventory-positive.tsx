 
import { Link, router, useForm } from '@inertiajs/react';
import DashboardController from '@/actions/App/Http/Controllers/DashboardController';
import { dashboard } from '@/routes';

type Props = {
    id: string;
    urls: {
        assistRequirement: string;
        download_url: string;
        review_url: string;
        submit: string;
    };
};

export function PositiveFixture({ id, urls }: Props) {
    const createForm = useForm({ title: '' });
    const briefing = { review_url: urls.review_url };
    const action = {
        label: 'Review',
        onClick: () => review(briefing.review_url),
    };

    createForm.post(urls.submit, { preserveScroll: true });
    router.flushAll();
    void fetch(urls.assistRequirement, {
        method: 'POST',
        headers: { Accept: 'application/json' },
    });

    return (
        <Link
            href={`/reports/${id}/preview`}
            as="button"
            method="post"
            target="_blank"
            rel="noopener noreferrer"
            download={urls.download_url}
            type="button"
            disabled={false}
            aria-disabled={false}
            data-test="fixture-link"
            onValueChange={(value) => createForm.setData('title', value)}
            onCheckedChange={(checked) => {
                if (checked) {
                    action.onClick();
                }
            }}
        >
            {dashboard().url}
            {DashboardController.toString()}
        </Link>
    );
}

function review(url: string) {
    router.post(url, {}, { preserveScroll: true });
}
