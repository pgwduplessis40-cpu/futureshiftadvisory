import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    CategoryOption,
    ClientOption,
    KnowledgeEntryDetail,
    KnowledgeFormData,
} from './types';

type Props = {
    entry: Partial<KnowledgeEntryDetail> & {
        client_id?: string | null;
        category: string;
        title: string;
        body: string;
        tags_string?: string;
    };
    categories: CategoryOption[];
    clients: ClientOption[];
    submitUrl: string;
    method: 'post' | 'patch';
    submitLabel: string;
};

export function KnowledgeForm({
    entry,
    categories,
    clients,
    submitUrl,
    method,
    submitLabel,
}: Props) {
    const form = useForm<KnowledgeFormData>({
        client_id: entry.client_id ?? '',
        category: entry.category,
        title: entry.title,
        body: entry.body,
        tags: entry.tags_string ?? '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = { preserveScroll: true };

        if (method === 'patch') {
            form.patch(submitUrl, options);

            return;
        }

        form.post(submitUrl, options);
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <section className="space-y-4 rounded-md border bg-background p-4">
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="knowledge_category">Category</Label>
                        <Select
                            value={form.data.category}
                            onValueChange={(value) =>
                                form.setData('category', value)
                            }
                        >
                            <SelectTrigger id="knowledge_category">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.value}
                                        value={category.value}
                                    >
                                        {category.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.category} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="knowledge_client">Client</Label>
                        <Select
                            value={form.data.client_id || 'none'}
                            onValueChange={(value) =>
                                form.setData(
                                    'client_id',
                                    value === 'none' ? '' : value,
                                )
                            }
                        >
                            <SelectTrigger id="knowledge_client">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">No client</SelectItem>
                                {clients.map((client) => (
                                    <SelectItem
                                        key={client.id}
                                        value={client.id}
                                    >
                                        {client.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.client_id} />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="knowledge_title">Title</Label>
                    <Input
                        id="knowledge_title"
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                    />
                    <InputError message={form.errors.title} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="knowledge_body">Body</Label>
                    <textarea
                        id="knowledge_body"
                        value={form.data.body}
                        onChange={(event) =>
                            form.setData('body', event.target.value)
                        }
                        rows={14}
                        className="min-h-80 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.body} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="knowledge_tags">Tags</Label>
                    <Input
                        id="knowledge_tags"
                        value={form.data.tags}
                        onChange={(event) =>
                            form.setData('tags', event.target.value)
                        }
                    />
                    <InputError message={form.errors.tags} />
                </div>
            </section>

            <Button type="submit" disabled={form.processing}>
                <Save className="size-4" aria-hidden="true" />
                {submitLabel}
            </Button>
        </form>
    );
}
