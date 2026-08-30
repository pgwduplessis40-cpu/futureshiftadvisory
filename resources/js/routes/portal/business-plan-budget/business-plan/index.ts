import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:102
* @route '/portal/business-plan-budget/business-plan/pdf'
*/
export const pdf = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pdf.url(options),
    method: 'get',
})

pdf.definition = {
    methods: ["get","head"],
    url: '/portal/business-plan-budget/business-plan/pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:102
* @route '/portal/business-plan-budget/business-plan/pdf'
*/
pdf.url = (options?: RouteQueryOptions) => {
    return pdf.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:102
* @route '/portal/business-plan-budget/business-plan/pdf'
*/
pdf.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pdf.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:102
* @route '/portal/business-plan-budget/business-plan/pdf'
*/
pdf.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pdf.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:102
* @route '/portal/business-plan-budget/business-plan/pdf'
*/
const pdfForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: pdf.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:102
* @route '/portal/business-plan-budget/business-plan/pdf'
*/
pdfForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: pdf.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:102
* @route '/portal/business-plan-budget/business-plan/pdf'
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

const businessPlan = {
    pdf: Object.assign(pdf, pdf),
}

export default businessPlan
