import { RotateCcw } from 'lucide-react';
import { Component } from 'react';
import type { ErrorInfo, ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type AppErrorBoundaryProps = {
    children: ReactNode;
};

type AppErrorBoundaryState = {
    hasError: boolean;
};

export class AppErrorBoundary extends Component<
    AppErrorBoundaryProps,
    AppErrorBoundaryState
> {
    public state: AppErrorBoundaryState = { hasError: false };

    public static getDerivedStateFromError(): AppErrorBoundaryState {
        return { hasError: true };
    }

    public componentDidCatch(error: Error, errorInfo: ErrorInfo): void {
        console.error('Unhandled application error', error, errorInfo);
    }

    public render(): ReactNode {
        if (this.state.hasError) {
            return (
                <main
                    className="flex min-h-screen items-center justify-center bg-background px-6 py-12 text-center"
                    role="alert"
                >
                    <div className="max-w-md space-y-4">
                        <h1 className="text-xl font-semibold">
                            We could not load this page
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Refresh the page to try again.
                        </p>
                        <Button
                            type="button"
                            onClick={() => window.location.reload()}
                        >
                            <RotateCcw aria-hidden="true" />
                            Reload page
                        </Button>
                    </div>
                </main>
            );
        }

        return this.props.children;
    }
}
