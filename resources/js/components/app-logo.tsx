import { BrandMark } from '@/components/public/brand-mark';

export default function AppLogo() {
    return (
        <div className="flex min-w-0 items-center rounded-full bg-white px-2 py-1 shadow-sm group-data-[collapsible=icon]:bg-transparent group-data-[collapsible=icon]:p-0 group-data-[collapsible=icon]:shadow-none dark:bg-white/95">
            <BrandMark className="h-12 w-auto group-data-[collapsible=icon]:hidden" />
            <BrandMark
                showWordmark={false}
                className="hidden h-9 w-auto group-data-[collapsible=icon]:block"
            />
        </div>
    );
}
