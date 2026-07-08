import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:65
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/assessments'
 */
export const store = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/assessments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:65
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/assessments'
 */
store.url = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                    businessPlan: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                                businessPlan: typeof args.businessPlan === 'object'
                ? args.businessPlan.id
                : args.businessPlan,
                }

    return store.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{businessPlan}', parsedArgs.businessPlan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:65
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/assessments'
 */
store.post = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:65
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/assessments'
 */
    const storeForm = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:65
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/assessments'
 */
        storeForm.post = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
const assessments = {
    store: Object.assign(store, store),
}

export default assessments