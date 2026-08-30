import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetController::budget
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetController.php:24
* @route '/portal/entrepreneur/plan/budget'
*/
export const budget = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: budget.url(options),
    method: 'post',
})

budget.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/budget',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetController::budget
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetController.php:24
* @route '/portal/entrepreneur/plan/budget'
*/
budget.url = (options?: RouteQueryOptions) => {
    return budget.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetController::budget
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetController.php:24
* @route '/portal/entrepreneur/plan/budget'
*/
budget.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: budget.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetController::budget
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetController.php:24
* @route '/portal/entrepreneur/plan/budget'
*/
const budgetForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: budget.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetController::budget
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetController.php:24
* @route '/portal/entrepreneur/plan/budget'
*/
budgetForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: budget.url(options),
    method: 'post',
})

budget.form = budgetForm

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetController::acknowledgeBudgetFlag
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetController.php:139
* @route '/portal/entrepreneur/plan/budget/flags/acknowledge'
*/
export const acknowledgeBudgetFlag = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: acknowledgeBudgetFlag.url(options),
    method: 'post',
})

acknowledgeBudgetFlag.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/budget/flags/acknowledge',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetController::acknowledgeBudgetFlag
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetController.php:139
* @route '/portal/entrepreneur/plan/budget/flags/acknowledge'
*/
acknowledgeBudgetFlag.url = (options?: RouteQueryOptions) => {
    return acknowledgeBudgetFlag.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetController::acknowledgeBudgetFlag
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetController.php:139
* @route '/portal/entrepreneur/plan/budget/flags/acknowledge'
*/
acknowledgeBudgetFlag.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: acknowledgeBudgetFlag.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetController::acknowledgeBudgetFlag
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetController.php:139
* @route '/portal/entrepreneur/plan/budget/flags/acknowledge'
*/
const acknowledgeBudgetFlagForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: acknowledgeBudgetFlag.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetController::acknowledgeBudgetFlag
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetController.php:139
* @route '/portal/entrepreneur/plan/budget/flags/acknowledge'
*/
acknowledgeBudgetFlagForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: acknowledgeBudgetFlag.url(options),
    method: 'post',
})

acknowledgeBudgetFlag.form = acknowledgeBudgetFlagForm

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetController::dismissBudgetAdvisorNudge
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetController.php:161
* @route '/portal/entrepreneur/plan/budget/advisor-nudge/dismiss'
*/
export const dismissBudgetAdvisorNudge = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: dismissBudgetAdvisorNudge.url(options),
    method: 'post',
})

dismissBudgetAdvisorNudge.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/budget/advisor-nudge/dismiss',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetController::dismissBudgetAdvisorNudge
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetController.php:161
* @route '/portal/entrepreneur/plan/budget/advisor-nudge/dismiss'
*/
dismissBudgetAdvisorNudge.url = (options?: RouteQueryOptions) => {
    return dismissBudgetAdvisorNudge.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetController::dismissBudgetAdvisorNudge
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetController.php:161
* @route '/portal/entrepreneur/plan/budget/advisor-nudge/dismiss'
*/
dismissBudgetAdvisorNudge.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: dismissBudgetAdvisorNudge.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetController::dismissBudgetAdvisorNudge
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetController.php:161
* @route '/portal/entrepreneur/plan/budget/advisor-nudge/dismiss'
*/
const dismissBudgetAdvisorNudgeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: dismissBudgetAdvisorNudge.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetController::dismissBudgetAdvisorNudge
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetController.php:161
* @route '/portal/entrepreneur/plan/budget/advisor-nudge/dismiss'
*/
dismissBudgetAdvisorNudgeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: dismissBudgetAdvisorNudge.url(options),
    method: 'post',
})

dismissBudgetAdvisorNudge.form = dismissBudgetAdvisorNudgeForm

const EntrepreneurPlanBudgetController = { budget, acknowledgeBudgetFlag, dismissBudgetAdvisorNudge }

export default EntrepreneurPlanBudgetController
