/**
 * JSON-LD builders for SEO / GEO / AEO / AIO.
 * Keep the entity facts here so search engines and AI answer engines get one
 * consistent description of who Future Shift Advisory is and what it offers.
 */

type Json = Record<string, unknown>;

export const ORG_ID = '#organization';
const LINKEDIN_URL = 'https://www.linkedin.com/company/future-shift-advisory';

const clean = (base: string) => base.replace(/\/$/, '');

/** ProfessionalService (a LocalBusiness subtype) — the core entity. */
export function organizationLd(base: string): Json {
    const origin = clean(base);

    return {
        '@context': 'https://schema.org',
        '@type': 'ProfessionalService',
        '@id': `${origin}/${ORG_ID}`,
        name: 'Future Shift Advisory',
        url: `${origin}/`,
        description:
            'Future Shift Advisory is a Hamilton-based business advisory practice for New Zealand SMEs, founders, acquirers, and not-for-profits. Clear, honest, evidence-based advice.',
        slogan: 'The truth before the comfortable.',
        email: 'hello@futureshiftadvisory.nz',
        areaServed: { '@type': 'Country', name: 'New Zealand' },
        address: {
            '@type': 'PostalAddress',
            addressLocality: 'Hamilton',
            addressRegion: 'Waikato',
            addressCountry: 'NZ',
        },
        knowsAbout: [
            'Business advisory',
            'Due diligence',
            'Post-acquisition advisory',
            'Startup and entrepreneur advisory',
            'Not-for-profit and social enterprise advisory',
            'Governance review',
            'Business process automation',
            'Custom business tools',
        ],
        sameAs: [LINKEDIN_URL],
    };
}

/** WebSite entity — helps engines understand the site as a whole. */
export function webSiteLd(base: string): Json {
    const origin = clean(base);

    return {
        '@context': 'https://schema.org',
        '@type': 'WebSite',
        '@id': `${origin}/#website`,
        url: `${origin}/`,
        name: 'Future Shift Advisory',
        inLanguage: 'en-NZ',
        publisher: { '@id': `${origin}/${ORG_ID}` },
    };
}

/** BreadcrumbList for an inner page. */
export function breadcrumbLd(
    base: string,
    trail: Array<{ name: string; path: string }>,
): Json {
    const origin = clean(base);

    return {
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: trail.map((item, i) => ({
            '@type': 'ListItem',
            position: i + 1,
            name: item.name,
            item: `${origin}${item.path === '/' ? '/' : item.path}`,
        })),
    };
}

/** ItemList of the services offered, each tied back to the organization. */
export function servicesLd(
    base: string,
    services: Array<{ slug: string; title: string; summary: string }>,
): Json {
    const origin = clean(base);

    return {
        '@context': 'https://schema.org',
        '@type': 'ItemList',
        name: 'Future Shift Advisory services',
        itemListElement: services.map((s, i) => ({
            '@type': 'ListItem',
            position: i + 1,
            item: {
                '@type': 'Service',
                name: s.title,
                description: s.summary,
                serviceType: s.title,
                url: `${origin}/services#${s.slug}`,
                areaServed: { '@type': 'Country', name: 'New Zealand' },
                provider: { '@id': `${origin}/${ORG_ID}` },
            },
        })),
    };
}
