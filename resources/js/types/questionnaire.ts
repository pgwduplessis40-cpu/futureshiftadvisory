export type QuestionType =
    | 'text'
    | 'long-text'
    | 'number'
    | 'currency'
    | 'date'
    | 'single-select'
    | 'multi-select'
    | 'file-attach'
    | 'likert';

export type QuestionOption = {
    value: string;
    label: string;
};

export type ConditionalRule = {
    when?: string;
    equals?: string | number | boolean | null;
    in?: Array<string | number | boolean>;
    show?: string;
};

export type ConditionalLogic = ConditionalRule | ConditionalRule[] | null;

export type QuestionnaireQuestion = {
    id: string;
    order: number;
    type: QuestionType;
    prompt: string;
    help_text: string | null;
    options: QuestionOption[];
    conditional_logic: ConditionalLogic;
    required: boolean;
};

export type QuestionnaireSection = {
    id: string;
    order: number;
    title: string;
    help_text: string | null;
    questions: QuestionnaireQuestion[];
};

export type QuestionnaireSchema = {
    id: string;
    set: string;
    version: string;
    title: string;
    published_at: string | null;
    sections: QuestionnaireSection[];
};

export type QuestionnaireAnswerValue = string | number | string[] | null;

export type QuestionnaireAnswer = {
    value: QuestionnaireAnswerValue;
    attached_document_ids: string[];
};

export type QuestionnaireAnswers = Record<string, QuestionnaireAnswer>;
