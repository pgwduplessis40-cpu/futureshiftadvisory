 
import { Link } from '@inertiajs/react';

export function NegativeFixture() {
    return (
        <Frame
            actions={
                <Link
                    href="/kept"
                    onClick={() => {
                        window.localStorage.setItem('kept', 'true');
                    }}
                    className="rounded-md bg-white px-3 py-2 text-sm"
                >
                    Kept
                </Link>
            }
        />
    );
}

function Frame({ actions }: { actions: React.ReactNode }) {
    return <div>{actions}</div>;
}
