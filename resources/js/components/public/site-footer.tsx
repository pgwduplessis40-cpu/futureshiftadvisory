import { Link } from '@inertiajs/react';

import { BrandMark } from '@/components/public/brand-mark';
import { login } from '@/routes';

export function SiteFooter() {
    const year = new Date().getFullYear();

    return (
        <footer className="mt-24 bg-[var(--fs-admiralty)] text-[var(--fs-parchment)]">
            <div className="mx-auto max-w-6xl px-6 py-16 lg:px-8">
                <div className="grid gap-12 md:grid-cols-12">
                    <div className="md:col-span-5">
                        <BrandMark variant="dark" width={184} />
                        <p className="font-accent mt-5 max-w-sm text-base text-[#E0D8CC] italic">
                            Evidence-based advisory for New&nbsp;Zealand SMEs
                            and entrepreneurs.
                        </p>
                        <Link
                            href="/contact"
                            className="mt-6 inline-flex items-center gap-2 rounded-md border border-[var(--fs-warm-gold)] px-4 py-2 text-sm font-medium text-[var(--fs-warm-gold)] transition-colors hover:bg-[var(--fs-warm-gold)] hover:text-[var(--fs-admiralty)]"
                        >
                            Book a discovery call &rarr;
                        </Link>
                    </div>

                    <div className="md:col-span-3">
                        <p className="text-[11px] font-semibold tracking-[0.15em] text-[var(--fs-warm-gold)] uppercase">
                            Explore
                        </p>
                        <ul className="mt-4 space-y-2 text-sm">
                            <li>
                                <Link
                                    href="/services"
                                    className="text-[#E0D8CC] hover:text-white"
                                >
                                    Services
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href="/about"
                                    className="text-[#E0D8CC] hover:text-white"
                                >
                                    About
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href="/faq"
                                    className="text-[#E0D8CC] hover:text-white"
                                >
                                    FAQ
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href="/contact"
                                    className="text-[#E0D8CC] hover:text-white"
                                >
                                    Contact
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <div className="md:col-span-4">
                        <p className="text-[11px] font-semibold tracking-[0.15em] text-[var(--fs-warm-gold)] uppercase">
                            Contact
                        </p>
                        <ul className="mt-4 space-y-2 text-sm text-[#E0D8CC]">
                            <li>Hamilton, New&nbsp;Zealand</li>
                            <li>
                                <a
                                    href="mailto:hello@futureshiftadvisory.nz"
                                    className="hover:text-white"
                                >
                                    hello@futureshiftadvisory.nz
                                </a>
                            </li>
                            <li>
                                <Link
                                    href={login()}
                                    className="text-[var(--fs-warm-gold)] hover:text-white"
                                >
                                    Client Login &rarr;
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>

                <div className="mt-12 flex flex-col items-start justify-between gap-3 border-t border-[#2A3B5C] pt-6 text-xs text-[#9AAAB8] md:flex-row md:items-center">
                    <p>
                        &copy; {year} Future Shift Advisory. All rights
                        reserved.
                    </p>
                    <p className="text-[11px] tracking-wider uppercase">
                        futureshiftadvisory.nz
                    </p>
                </div>
            </div>
        </footer>
    );
}
