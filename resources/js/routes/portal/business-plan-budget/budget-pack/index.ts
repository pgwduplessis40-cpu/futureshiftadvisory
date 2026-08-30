import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:123
* @route '/portal/business-plan-budget/budget-pack/pdf'
*/
export const pdf = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pdf.url(options),
    method: 'get',
})

pdf.definition = {
    methods: ["get","head"],
    url: '/portal/business-plan-budget/budget-pack/pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:123
* @route '/portal/business-plan-budget/budget-pack/pdf'
*/
pdf.url = (options?: RouteQueryOptions) => {
    return pdf.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:123
* @route '/portal/business-plan-budget/budget-pack/pdf'
*/
pdf.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pdf.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:123
* @route '/portal/business-plan-budget/budget-pack/pdf'
*/
pdf.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pdf.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:123
* @route '/portal/business-plan-budget/budget-pack/pdf'
*/
const pdfForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: pdf.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:123
* @route '/portal/business-plan-budget/budget-pack/pdf'
*/
pdfForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: pdf.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:123
* @route '/portal/business-plan-budget/budget-pack/pdf'
*/
pdfForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: pdf.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

pdf.form = pdfForm

const budgetPack = {
    pdf: Object.assign(pdf, pdf),
}

export default budgetPack
