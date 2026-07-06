import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:149
 * @route '/portal/entrepreneur/plan/budget-pack'
 */
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/portal/entrepreneur/plan/budget-pack',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:149
 * @route '/portal/entrepreneur/plan/budget-pack'
 */
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:149
 * @route '/portal/entrepreneur/plan/budget-pack'
 */
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:149
 * @route '/portal/entrepreneur/plan/budget-pack'
 */
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:149
 * @route '/portal/entrepreneur/plan/budget-pack'
 */
    const showForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:149
 * @route '/portal/entrepreneur/plan/budget-pack'
 */
        showForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:149
 * @route '/portal/entrepreneur/plan/budget-pack'
 */
        showForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::pdf
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:172
 * @route '/portal/entrepreneur/plan/budget-pack/pdf'
 */
export const pdf = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pdf.url(options),
    method: 'get',
})

pdf.definition = {
    methods: ["get","head"],
    url: '/portal/entrepreneur/plan/budget-pack/pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::pdf
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:172
 * @route '/portal/entrepreneur/plan/budget-pack/pdf'
 */
pdf.url = (options?: RouteQueryOptions) => {
    return pdf.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::pdf
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:172
 * @route '/portal/entrepreneur/plan/budget-pack/pdf'
 */
pdf.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pdf.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::pdf
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:172
 * @route '/portal/entrepreneur/plan/budget-pack/pdf'
 */
pdf.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pdf.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::pdf
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:172
 * @route '/portal/entrepreneur/plan/budget-pack/pdf'
 */
    const pdfForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: pdf.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::pdf
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:172
 * @route '/portal/entrepreneur/plan/budget-pack/pdf'
 */
        pdfForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: pdf.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::pdf
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:172
 * @route '/portal/entrepreneur/plan/budget-pack/pdf'
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
    show: Object.assign(show, show),
pdf: Object.assign(pdf, pdf),
}

export default budgetPack