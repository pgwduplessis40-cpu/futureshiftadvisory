export const founderBudgetActionFocuses = [
    'fixed-costs',
    'forecast-start-month',
    'revenue-forecast',
    'funding-sources',
    'budget-overview',
] as const;

export type FounderBudgetActionFocus =
    (typeof founderBudgetActionFocuses)[number];

export type FounderActionDeepLink = {
    requirement: 'financial-assumptions';
    focus: FounderBudgetActionFocus;
    showGuide: boolean;
};

export function founderActionDeepLink(
    search = typeof window === 'undefined' ? '' : window.location.search,
): FounderActionDeepLink | null {
    const params = new URLSearchParams(search);
    const focus = params.get('focus');

    if (
        params.get('requirement') !== 'financial-assumptions' ||
        focus === null ||
        !founderBudgetActionFocuses.includes(focus as FounderBudgetActionFocus)
    ) {
        return null;
    }

    return {
        requirement: 'financial-assumptions',
        focus: focus as FounderBudgetActionFocus,
        showGuide: params.get('guide') === '1',
    };
}
