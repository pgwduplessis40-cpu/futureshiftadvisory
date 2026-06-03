import { Head } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { InspirationCard } from '@/components/inspiration/InspirationCard';
import type { InspirationPost } from '@/components/inspiration/InspirationCard';

type Props = {
    posts: InspirationPost[];
};

export default function PortalInspirationBoard({ posts }: Props) {
    return (
        <>
            <Head title="Inspiration" />

            <main className="flex-1 space-y-6 p-6">
                <header className="flex items-center gap-2">
                    <Sparkles className="size-5" aria-hidden="true" />
                    <div>
                        <h1 className="text-xl font-semibold">Inspiration</h1>
                        <p className="text-sm text-muted-foreground">
                            A little motivation from your advisory team.
                        </p>
                    </div>
                </header>

                {posts.length > 0 ? (
                    <div className="grid gap-4">
                        {posts.map((post) => (
                            <InspirationCard key={post.id} post={post} />
                        ))}
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No inspiration posts yet — check back soon.
                    </p>
                )}
            </main>
        </>
    );
}
