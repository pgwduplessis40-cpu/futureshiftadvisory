import type {
    ConditionalLogic,
    ConditionalRule,
    QuestionnaireAnswers,
    QuestionnaireSchema,
} from '@/types/questionnaire';

export function evaluateVisibleQuestionIds(
    schema: QuestionnaireSchema,
    answers: QuestionnaireAnswers,
): Set<string> {
    const questionIds = schema.sections.flatMap((section) =>
        section.questions.map((question) => question.id),
    );
    const visible = new Map(questionIds.map((id) => [id, true]));

    for (const section of schema.sections) {
        for (const question of section.questions) {
            for (const rule of rules(question.conditional_logic)) {
                const target = rule.show ?? question.id;

                if (!rule.when || !visible.has(target)) {
                    continue;
                }

                visible.set(
                    target,
                    Boolean(visible.get(target)) &&
                        matches(answers[rule.when]?.value ?? null, rule),
                );
            }
        }
    }

    return new Set(
        [...visible.entries()]
            .filter(([, isVisible]) => isVisible)
            .map(([id]) => id),
    );
}

function rules(logic: ConditionalLogic): ConditionalRule[] {
    if (!logic) {
        return [];
    }

    return Array.isArray(logic) ? logic : [logic];
}

function matches(value: unknown, rule: ConditionalRule): boolean {
    if ('equals' in rule) {
        return containsValue(value, rule.equals);
    }

    if (Array.isArray(rule.in)) {
        return rule.in.some((candidate) => containsValue(value, candidate));
    }

    return true;
}

function containsValue(actual: unknown, expected: unknown): boolean {
    if (Array.isArray(actual)) {
        return actual.some((item) => sameValue(item, expected));
    }

    return sameValue(actual, expected);
}

function sameValue(actual: unknown, expected: unknown): boolean {
    return normalise(actual) === normalise(expected);
}

function normalise(value: unknown): string {
    if (typeof value === 'boolean') {
        return value ? 'true' : 'false';
    }

    if (typeof value === 'number') {
        return String(value);
    }

    return String(value ?? '').trim();
}
