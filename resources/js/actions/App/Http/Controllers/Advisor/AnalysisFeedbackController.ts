import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\AnalysisFeedbackController::store
 * @see app/Http/Controllers/Advisor/AnalysisFeedbackController.php:19
 * @route '/advisor/analysis-findings/{analysisFinding}/feedback'
 */
export const store = (args: { analysisFinding: string | { id: string } } | [analysisFinding: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/analysis-findings/{analysisFinding}/feedback',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\AnalysisFeedbackController::store
 * @see app/Http/Controllers/Advisor/AnalysisFeedbackController.php:19
 * @route '/advisor/analysis-findings/{analysisFinding}/feedback'
 */
store.url = (args: { analysisFinding: string | { id: string } } | [analysisFinding: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { analysisFinding: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { analysisFinding: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    analysisFinding: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        analysisFinding: typeof args.analysisFinding === 'object'
                ? args.analysisFinding.id
                : args.analysisFinding,
                }

    return store.definition.url
            .replace('{analysisFinding}', parsedArgs.analysisFinding.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\AnalysisFeedbackController::store
 * @see app/Http/Controllers/Advisor/AnalysisFeedbackController.php:19
 * @route '/advisor/analysis-findings/{analysisFinding}/feedback'
 */
store.post = (args: { analysisFinding: string | { id: string } } | [analysisFinding: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\AnalysisFeedbackController::store
 * @see app/Http/Controllers/Advisor/AnalysisFeedbackController.php:19
 * @route '/advisor/analysis-findings/{analysisFinding}/feedback'
 */
    const storeForm = (args: { analysisFinding: string | { id: string } } | [analysisFinding: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\AnalysisFeedbackController::store
 * @see app/Http/Controllers/Advisor/AnalysisFeedbackController.php:19
 * @route '/advisor/analysis-findings/{analysisFinding}/feedback'
 */
        storeForm.post = (args: { analysisFinding: string | { id: string } } | [analysisFinding: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
const AnalysisFeedbackController = { store }

export default AnalysisFeedbackController