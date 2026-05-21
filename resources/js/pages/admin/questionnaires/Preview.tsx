import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { QuestionnaireRenderer } from '@/components/questionnaires/QuestionnaireRenderer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { QuestionnaireAnswers } from '@/types/questionnaire';
import type { QuestionnaireForm } from './types';

type Props = {
    questionnaire: QuestionnaireForm;
};

export default function QuestionnairesPreview({ questionnaire }: Props) {
    const [answers, setAnswers] = useState<QuestionnaireAnswers>({});

    return (
        <>
            <Head title={`Preview ${questionnaire.title}`} />

            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <Button asChild size="sm" variant="outline">
                            <Link href="/admin/questionnaires">
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Questionnaires
                            </Link>
                        </Button>
                        <h1 className="mt-4 text-xl font-semibold">
                            {questionnaire.title}
                        </h1>
                        <div className="mt-2 flex flex-wrap gap-2">
                            <Badge variant="secondary">
                                {questionnaire.set}
                            </Badge>
                            <Badge variant="outline">
                                Version {questionnaire.version}
                            </Badge>
                            <Badge
                                variant={
                                    questionnaire.published_at
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {questionnaire.published_at
                                    ? 'published'
                                    : 'draft'}
                            </Badge>
                        </div>
                    </div>
                    {!questionnaire.published_at && (
                        <Button asChild size="sm">
                            <Link
                                href={`/admin/questionnaires/${questionnaire.id}/edit`}
                            >
                                Edit
                            </Link>
                        </Button>
                    )}
                </div>

                <QuestionnaireRenderer
                    schema={questionnaire}
                    answers={answers}
                    onChange={setAnswers}
                />
            </div>
        </>
    );
}
