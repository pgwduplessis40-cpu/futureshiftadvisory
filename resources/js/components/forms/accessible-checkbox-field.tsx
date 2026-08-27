import type { ComponentProps } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

type AccessibleCheckboxFieldProps = Omit<
    ComponentProps<typeof Checkbox>,
    'id' | 'name'
> & {
    name: string;
    label: string;
    description?: string;
};

export function AccessibleCheckboxField({
    name,
    label,
    description,
    ...checkboxProps
}: AccessibleCheckboxFieldProps) {
    const descriptionId = description ? `${name}-description` : undefined;

    return (
        <div className="flex items-start gap-3">
            <Checkbox
                {...checkboxProps}
                aria-describedby={descriptionId}
                id={name}
                name={name}
            />
            <div className="space-y-1">
                <Label htmlFor={name}>{label}</Label>
                {description ? (
                    <p
                        className="text-sm text-muted-foreground"
                        id={descriptionId}
                    >
                        {description}
                    </p>
                ) : null}
            </div>
        </div>
    );
}
