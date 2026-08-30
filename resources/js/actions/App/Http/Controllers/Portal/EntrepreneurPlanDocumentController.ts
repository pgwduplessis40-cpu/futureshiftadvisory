import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::preview
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:31
* @route '/portal/entrepreneur/plan/preview'
*/
export const preview = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(options),
    method: 'get',
})

preview.definition = {
    methods: ["get","head"],
    url: '/portal/entrepreneur/plan/preview',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::preview
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:31
* @route '/portal/entrepreneur/plan/preview'
*/
preview.url = (options?: RouteQueryOptions) => {
    return preview.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::preview
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:31
* @route '/portal/entrepreneur/plan/preview'
*/
preview.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::preview
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:31
* @route '/portal/entrepreneur/plan/preview'
*/
preview.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: preview.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::preview
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:31
* @route '/portal/entrepreneur/plan/preview'
*/
const previewForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: preview.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::preview
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:31
* @route '/portal/entrepreneur/plan/preview'
*/
previewForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: preview.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::preview
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:31
* @route '/portal/entrepreneur/plan/preview'
*/
previewForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: preview.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

preview.form = previewForm

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::budgetPack
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:45
* @route '/portal/entrepreneur/plan/budget-pack'
*/
export const budgetPack = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: budgetPack.url(options),
    method: 'get',
})

budgetPack.definition = {
    methods: ["get","head"],
    url: '/portal/entrepreneur/plan/budget-pack',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::budgetPack
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:45
* @route '/portal/entrepreneur/plan/budget-pack'
*/
budgetPack.url = (options?: RouteQueryOptions) => {
    return budgetPack.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::budgetPack
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:45
* @route '/portal/entrepreneur/plan/budget-pack'
*/
budgetPack.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: budgetPack.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::budgetPack
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:45
* @route '/portal/entrepreneur/plan/budget-pack'
*/
budgetPack.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: budgetPack.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::budgetPack
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:45
* @route '/portal/entrepreneur/plan/budget-pack'
*/
const budgetPackForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: budgetPack.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::budgetPack
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:45
* @route '/portal/entrepreneur/plan/budget-pack'
*/
budgetPackForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: budgetPack.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::budgetPack
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:45
* @route '/portal/entrepreneur/plan/budget-pack'
*/
budgetPackForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: budgetPack.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

budgetPack.form = budgetPackForm

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::budgetPackPdf
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:67
* @route '/portal/entrepreneur/plan/budget-pack/pdf'
*/
export const budgetPackPdf = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: budgetPackPdf.url(options),
    method: 'get',
})

budgetPackPdf.definition = {
    methods: ["get","head"],
    url: '/portal/entrepreneur/plan/budget-pack/pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::budgetPackPdf
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:67
* @route '/portal/entrepreneur/plan/budget-pack/pdf'
*/
budgetPackPdf.url = (options?: RouteQueryOptions) => {
    return budgetPackPdf.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::budgetPackPdf
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:67
* @route '/portal/entrepreneur/plan/budget-pack/pdf'
*/
budgetPackPdf.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: budgetPackPdf.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::budgetPackPdf
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:67
* @route '/portal/entrepreneur/plan/budget-pack/pdf'
*/
budgetPackPdf.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: budgetPackPdf.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::budgetPackPdf
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:67
* @route '/portal/entrepreneur/plan/budget-pack/pdf'
*/
const budgetPackPdfForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: budgetPackPdf.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::budgetPackPdf
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:67
* @route '/portal/entrepreneur/plan/budget-pack/pdf'
*/
budgetPackPdfForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: budgetPackPdf.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanDocumentController::budgetPackPdf
* @see app/Http/Controllers/Portal/EntrepreneurPlanDocumentController.php:67
* @route '/portal/entrepreneur/plan/budget-pack/pdf'
*/
budgetPackPdfForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: budgetPackPdf.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

budgetPackPdf.form = budgetPackPdfForm

const EntrepreneurPlanDocumentController = { preview, budgetPack, budgetPackPdf }

export default EntrepreneurPlanDocumentController
