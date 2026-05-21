import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\OnboardingController::index
 * @see app/Http/Controllers/Portal/OnboardingController.php:28
 * @route '/portal/onboarding'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/portal/onboarding',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\OnboardingController::index
 * @see app/Http/Controllers/Portal/OnboardingController.php:28
 * @route '/portal/onboarding'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\OnboardingController::index
 * @see app/Http/Controllers/Portal/OnboardingController.php:28
 * @route '/portal/onboarding'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\OnboardingController::index
 * @see app/Http/Controllers/Portal/OnboardingController.php:28
 * @route '/portal/onboarding'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\OnboardingController::index
 * @see app/Http/Controllers/Portal/OnboardingController.php:28
 * @route '/portal/onboarding'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\OnboardingController::index
 * @see app/Http/Controllers/Portal/OnboardingController.php:28
 * @route '/portal/onboarding'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\OnboardingController::index
 * @see app/Http/Controllers/Portal/OnboardingController.php:28
 * @route '/portal/onboarding'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    index.form = indexForm
/**
* @see \App\Http\Controllers\Portal\OnboardingController::step
 * @see app/Http/Controllers/Portal/OnboardingController.php:37
 * @route '/portal/onboarding/{step}'
 */
export const step = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: step.url(args, options),
    method: 'get',
})

step.definition = {
    methods: ["get","head"],
    url: '/portal/onboarding/{step}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\OnboardingController::step
 * @see app/Http/Controllers/Portal/OnboardingController.php:37
 * @route '/portal/onboarding/{step}'
 */
step.url = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { step: args }
    }


    if (Array.isArray(args)) {
        args = {
                    step: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        step: args.step,
                }

    return step.definition.url
            .replace('{step}', parsedArgs.step.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\OnboardingController::step
 * @see app/Http/Controllers/Portal/OnboardingController.php:37
 * @route '/portal/onboarding/{step}'
 */
step.get = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: step.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\OnboardingController::step
 * @see app/Http/Controllers/Portal/OnboardingController.php:37
 * @route '/portal/onboarding/{step}'
 */
step.head = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: step.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\OnboardingController::step
 * @see app/Http/Controllers/Portal/OnboardingController.php:37
 * @route '/portal/onboarding/{step}'
 */
    const stepForm = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: step.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\OnboardingController::step
 * @see app/Http/Controllers/Portal/OnboardingController.php:37
 * @route '/portal/onboarding/{step}'
 */
        stepForm.get = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: step.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\OnboardingController::step
 * @see app/Http/Controllers/Portal/OnboardingController.php:37
 * @route '/portal/onboarding/{step}'
 */
        stepForm.head = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: step.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    step.form = stepForm
/**
* @see \App\Http\Controllers\Portal\OnboardingController::store
 * @see app/Http/Controllers/Portal/OnboardingController.php:51
 * @route '/portal/onboarding/{step}'
 */
export const store = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/onboarding/{step}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\OnboardingController::store
 * @see app/Http/Controllers/Portal/OnboardingController.php:51
 * @route '/portal/onboarding/{step}'
 */
store.url = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { step: args }
    }


    if (Array.isArray(args)) {
        args = {
                    step: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        step: args.step,
                }

    return store.definition.url
            .replace('{step}', parsedArgs.step.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\OnboardingController::store
 * @see app/Http/Controllers/Portal/OnboardingController.php:51
 * @route '/portal/onboarding/{step}'
 */
store.post = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\OnboardingController::store
 * @see app/Http/Controllers/Portal/OnboardingController.php:51
 * @route '/portal/onboarding/{step}'
 */
    const storeForm = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\OnboardingController::store
 * @see app/Http/Controllers/Portal/OnboardingController.php:51
 * @route '/portal/onboarding/{step}'
 */
        storeForm.post = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
const onboarding = {
    index: Object.assign(index, index),
step: Object.assign(step, step),
store: Object.assign(store, store),
}

export default onboarding