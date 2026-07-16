import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:314
 * @route '/portal/entrepreneur/plan/sections'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/sections',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:314
 * @route '/portal/entrepreneur/plan/sections'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:314
 * @route '/portal/entrepreneur/plan/sections'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:314
 * @route '/portal/entrepreneur/plan/sections'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:314
 * @route '/portal/entrepreneur/plan/sections'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::guidance
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:520
 * @route '/portal/entrepreneur/plan/sections/{planSection}/guidance'
 */
export const guidance = (args: { planSection: string | { id: string } } | [planSection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: guidance.url(args, options),
    method: 'post',
})

guidance.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/sections/{planSection}/guidance',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::guidance
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:520
 * @route '/portal/entrepreneur/plan/sections/{planSection}/guidance'
 */
guidance.url = (args: { planSection: string | { id: string } } | [planSection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planSection: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { planSection: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    planSection: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        planSection: typeof args.planSection === 'object'
                ? args.planSection.id
                : args.planSection,
                }

    return guidance.definition.url
            .replace('{planSection}', parsedArgs.planSection.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::guidance
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:520
 * @route '/portal/entrepreneur/plan/sections/{planSection}/guidance'
 */
guidance.post = (args: { planSection: string | { id: string } } | [planSection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: guidance.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::guidance
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:520
 * @route '/portal/entrepreneur/plan/sections/{planSection}/guidance'
 */
    const guidanceForm = (args: { planSection: string | { id: string } } | [planSection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: guidance.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::guidance
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:520
 * @route '/portal/entrepreneur/plan/sections/{planSection}/guidance'
 */
        guidanceForm.post = (args: { planSection: string | { id: string } } | [planSection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: guidance.url(args, options),
            method: 'post',
        })
    
    guidance.form = guidanceForm
const sections = {
    store: Object.assign(store, store),
guidance: Object.assign(guidance, guidance),
}

export default sections