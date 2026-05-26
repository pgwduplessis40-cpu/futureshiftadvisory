import { BrandMark } from '@/components/public/brand-mark';

export default function AppLogo() {
    return (
        <div className="flex min-w-0 items-center">
            <BrandMark className="h-8 w-auto group-data-[collapsible=icon]:hidden" />
            <BrandMark
                showWordmark={false}
                className="hidden h-7 w-auto group-data-[collapsible=icon]:block"
            />
        </div>
    );
}
