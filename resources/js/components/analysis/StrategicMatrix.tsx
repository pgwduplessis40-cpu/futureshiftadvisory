import { Badge } from '@/components/ui/badge';

type MatrixQuadrants = Record<string, string[]>;

type Props = {
    title: string;
    quadrants: MatrixQuadrants;
};

export function StrategicMatrix({ title, quadrants }: Props) {
    return (
        <section className="space-y-3 rounded-md border bg-background p-4">
            <div className="flex items-center justify-between gap-3">
                <h2 className="text-sm font-medium">{title}</h2>
                <Badge variant="outline">{Object.keys(quadrants).length}</Badge>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                {Object.entries(quadrants).map(([key, items]) => (
                    <div key={key} className="min-h-28 rounded-md border p-3">
                        <div className="text-xs font-medium text-muted-foreground uppercase">
                            {key.replaceAll('_', ' ')}
                        </div>
                        <ul className="mt-2 space-y-1 text-sm">
                            {items.map((item) => (
                                <li key={item}>{item}</li>
                            ))}
                        </ul>
                    </div>
                ))}
            </div>
        </section>
    );
}
