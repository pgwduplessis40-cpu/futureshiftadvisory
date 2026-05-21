import type { QuestionnaireSchema } from '@/types/questionnaire';

export type QuestionnaireSummary = {
    id: string;
    set: string;
    version: string;
    title: string;
    published_at: string | null;
    sections_count: number;
    responses_count: number;
};

export type QuestionnaireForm = QuestionnaireSchema;
