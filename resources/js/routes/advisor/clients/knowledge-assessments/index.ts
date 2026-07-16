import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\KnowledgeAssessmentController::store
 * @see app/Http/Controllers/Advisor/KnowledgeAssessmentController.php:17
 * @route '/advisor/clients/{client}/knowledge-assessments'
 */
export const store = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/knowledge-assessments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\KnowledgeAssessmentController::store
 * @see app/Http/Controllers/Advisor/KnowledgeAssessmentController.php:17
 * @route '/advisor/clients/{client}/knowledge-assessments'
 */
store.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { client: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { client: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                }

    return store.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\KnowledgeAssessmentController::store
 * @see app/Http/Controllers/Advisor/KnowledgeAssessmentController.php:17
 * @route '/advisor/clients/{client}/knowledge-assessments'
 */
store.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\KnowledgeAssessmentController::store
 * @see app/Http/Controllers/Advisor/KnowledgeAssessmentController.php:17
 * @route '/advisor/clients/{client}/knowledge-assessments'
 */
    const storeForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\KnowledgeAssessmentController::store
 * @see app/Http/Controllers/Advisor/KnowledgeAssessmentController.php:17
 * @route '/advisor/clients/{client}/knowledge-assessments'
 */
        storeForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
const knowledgeAssessments = {
    store: Object.assign(store, store),
}

export default knowledgeAssessments