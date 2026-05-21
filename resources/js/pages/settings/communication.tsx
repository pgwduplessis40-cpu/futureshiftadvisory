import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Preference = {
    channel: string;
    frequency: string;
    timezone: string;
};

type Props = {
    preference: Preference;
    channels: string[];
    frequencies: string[];
};

const labels: Record<string, string> = {
    email_only: 'Email only',
    in_platform_only: 'In-platform only',
    both: 'Email and in-platform',
    immediate: 'Immediate',
    daily: 'Daily digest',
    weekly: 'Weekly digest',
};

export default function CommunicationSettings({
    preference,
    channels,
    frequencies,
}: Props) {
    const form = useForm<Preference>(preference);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put('/settings/communication', { preserveScroll: true });
    };

    return (
        <>
            <Head title="Communication settings" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Communication"
                    description="Set delivery channel and digest frequency"
                />

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="channel">Delivery channel</Label>
                        <Select
                            value={form.data.channel}
                            onValueChange={(value) =>
                                form.setData('channel', value)
                            }
                        >
                            <SelectTrigger id="channel" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {channels.map((channel) => (
                                    <SelectItem key={channel} value={channel}>
                                        {labels[channel] ?? channel}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.channel} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="frequency">Frequency</Label>
                        <Select
                            value={form.data.frequency}
                            onValueChange={(value) =>
                                form.setData('frequency', value)
                            }
                        >
                            <SelectTrigger id="frequency" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {frequencies.map((frequency) => (
                                    <SelectItem
                                        key={frequency}
                                        value={frequency}
                                    >
                                        {labels[frequency] ?? frequency}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.frequency} />
                    </div>

                    <input
                        type="hidden"
                        name="timezone"
                        value={form.data.timezone}
                    />

                    <Button disabled={form.processing}>Save</Button>
                </form>
            </div>
        </>
    );
}

CommunicationSettings.layout = {
    breadcrumbs: [
        {
            title: 'Communication settings',
            href: '/settings/communication',
        },
    ],
};
