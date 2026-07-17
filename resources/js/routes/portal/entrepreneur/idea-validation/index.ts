import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:213
 * @route '/portal/entrepreneur/idea-validation'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/idea-validation',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:213
 * @route '/portal/entrepreneur/idea-validation'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:213
 * @route '/portal/entrepreneur/idea-validation'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:213
 * @route '/portal/entrepreneur/idea-validation'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:213
 * @route '/portal/entrepreneur/idea-validation'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::recall
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:235
 * @route '/portal/entrepreneur/idea-validation/recall'
 */
export const recall = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: recall.url(options),
    method: 'post',
})

recall.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/idea-validation/recall',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::recall
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:235
 * @route '/portal/entrepreneur/idea-validation/recall'
 */
recall.url = (options?: RouteQueryOptions) => {
    return recall.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::recall
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:235
 * @route '/portal/entrepreneur/idea-validation/recall'
 */
recall.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: recall.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::recall
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:235
 * @route '/portal/entrepreneur/idea-validation/recall'
 */
    const recallForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: recall.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::recall
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:235
 * @route '/portal/entrepreneur/idea-validation/recall'
 */
        recallForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: recall.url(options),
            method: 'post',
        })

    recall.form = recallForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::restore
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:251
 * @route '/portal/entrepreneur/idea-validation/{ideaValidation}/restore'
 */
export const restore = (args: { ideaValidation: string | { id: string } } | [ideaValidation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: restore.url(args, options),
    method: 'post',
})

restore.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/idea-validation/{ideaValidation}/restore',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::restore
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:251
 * @route '/portal/entrepreneur/idea-validation/{ideaValidation}/restore'
 */
restore.url = (args: { ideaValidation: string | { id: string } } | [ideaValidation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ideaValidation: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { ideaValidation: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    ideaValidation: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        ideaValidation: typeof args.ideaValidation === 'object'
                ? args.ideaValidation.id
                : args.ideaValidation,
                }

    return restore.definition.url
            .replace('{ideaValidation}', parsedArgs.ideaValidation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::restore
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:251
 * @route '/portal/entrepreneur/idea-validation/{ideaValidation}/restore'
 */
restore.post = (args: { ideaValidation: string | { id: string } } | [ideaValidation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: restore.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::restore
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:251
 * @route '/portal/entrepreneur/idea-validation/{ideaValidation}/restore'
 */
    const restoreForm = (args: { ideaValidation: string | { id: string } } | [ideaValidation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: restore.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::restore
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:251
 * @route '/portal/entrepreneur/idea-validation/{ideaValidation}/restore'
 */
        restoreForm.post = (args: { ideaValidation: string | { id: string } } | [ideaValidation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: restore.url(args, options),
            method: 'post',
        })

    restore.form = restoreForm
const ideaValidation = {
    store: Object.assign(store, store),
recall: Object.assign(recall, recall),
restore: Object.assign(restore, restore),
}

export default ideaValidation