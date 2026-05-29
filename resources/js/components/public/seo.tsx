import { Head, usePage } from '@inertiajs/react';

type JsonLd = Record<string, unknown>;

type SeoProps = {
    /** Page title (without the site-name suffix). */
    title: string;
    /** Meta description — keep it answer-first and under ~155 chars. */
    description: string;
    /** Canonical path override (defaults to the current path, query stripped). */
    path?: string;
    /** og:type — "website" for most pages. */
    type?: string;
    /** Keep the page out of search/AI indexes (e.g. thank-you pages). */
    noindex?: boolean;
    /** One or more JSON-LD blocks for SEO / GEO / AEO / AIO. */
    jsonLd?: JsonLd | JsonLd[];
};

const SITE_NAME = 'Future Shift Advisory';

export function Seo({
    title,
    description,
    path,
    type = 'website',
    noindex = false,
    jsonLd,
}: SeoProps) {
    const page = usePage();
    const base = (page.props.publicUrl ?? '').replace(/\/$/, '');
    const currentPath = (path ?? page.url).split('?')[0].split('#')[0];
    const canonical =
        base + (currentPath === '/' ? '' : currentPath.replace(/\/$/, ''));

    const blocks = jsonLd ? (Array.isArray(jsonLd) ? jsonLd : [jsonLd]) : [];

    return (
        <Head title={title}>
            <meta
                name="description"
                content={description}
                head-key="description"
            />
            <link rel="canonical" href={canonical} head-key="canonical" />
            {noindex ? (
                <meta
                    name="robots"
                    content="noindex, nofollow"
                    head-key="robots"
                />
            ) : (
                <meta
                    name="robots"
                    content="index, follow, max-image-preview:large"
                    head-key="robots"
                />
            )}

            {/* Open Graph */}
            <meta
                property="og:site_name"
                content={SITE_NAME}
                head-key="og:site_name"
            />
            <meta property="og:title" content={title} head-key="og:title" />
            <meta
                property="og:description"
                content={description}
                head-key="og:description"
            />
            <meta property="og:type" content={type} head-key="og:type" />
            <meta property="og:url" content={canonical} head-key="og:url" />
            <meta property="og:locale" content="en_NZ" head-key="og:locale" />

            {/* Twitter */}
            <meta
                name="twitter:card"
                content="summary_large_image"
                head-key="twitter:card"
            />
            <meta
                name="twitter:title"
                content={title}
                head-key="twitter:title"
            />
            <meta
                name="twitter:description"
                content={description}
                head-key="twitter:description"
            />

            {blocks.map((block, i) => (
                <script
                    key={`jsonld-${i}`}
                    head-key={`jsonld-${i}`}
                    type="application/ld+json"
                    dangerouslySetInnerHTML={{ __html: JSON.stringify(block) }}
                />
            ))}
        </Head>
    );
}
