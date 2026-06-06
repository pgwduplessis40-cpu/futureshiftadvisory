import { Head } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { InspirationCard } from '@/components/inspiration/InspirationCard';
import type { InspirationPost } from '@/components/inspiration/InspirationCard';
import { PageHeader } from '@/components/page-header';

type Props = {
    posts: InspirationPost[];
};

export default function PortalInspirationBoard({ posts }: Props) {
    return (
        <>
            <Head title="Inspiration" />

            <main className="flex-1 space-y-6">
                <PageHeader
                    eyebrow="For you"
                    icon={Sparkles}
                    title="Inspiration"
                    description="A little motivation from your advisory team."
                />

                {posts.length > 0 ? (
                    <div className="grid gap-4">
                        {posts.map((post) => (
                            <InspirationCard key={post.id} post={post} />
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        icon={Sparkles}
                        title="No inspiration yet"
                        description="Check back soon — your advisory team will share motivation here."
                    />
                )}
            </main>
        </>
    );
}
