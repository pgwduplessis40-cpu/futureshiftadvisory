import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\OnboardingController::redirect
 * @see app/Http/Controllers/Portal/OnboardingController.php:28
 * @route '/portal/onboarding'
 */
export const redirect = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: redirect.url(options),
    method: 'get',
})

redirect.definition = {
    methods: ["get","head"],
    url: '/portal/onboarding',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\OnboardingController::redirect
 * @see app/Http/Controllers/Portal/OnboardingController.php:28
 * @route '/portal/onboarding'
 */
redirect.url = (options?: RouteQueryOptions) => {
    return redirect.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\OnboardingController::redirect
 * @see app/Http/Controllers/Portal/OnboardingController.php:28
 * @route '/portal/onboarding'
 */
redirect.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: redirect.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\OnboardingController::redirect
 * @see app/Http/Controllers/Portal/OnboardingController.php:28
 * @route '/portal/onboarding'
 */
redirect.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: redirect.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\OnboardingController::redirect
 * @see app/Http/Controllers/Portal/OnboardingController.php:28
 * @route '/portal/onboarding'
 */
    const redirectForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: redirect.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\OnboardingController::redirect
 * @see app/Http/Controllers/Portal/OnboardingController.php:28
 * @route '/portal/onboarding'
 */
        redirectForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: redirect.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\OnboardingController::redirect
 * @see app/Http/Controllers/Portal/OnboardingController.php:28
 * @route '/portal/onboarding'
 */
        redirectForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: redirect.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    redirect.form = redirectForm
/**
* @see \App\Http\Controllers\Portal\OnboardingController::show
 * @see app/Http/Controllers/Portal/OnboardingController.php:37
 * @route '/portal/onboarding/{step}'
 */
export const show = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/portal/onboarding/{step}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\OnboardingController::show
 * @see app/Http/Controllers/Portal/OnboardingController.php:37
 * @route '/portal/onboarding/{step}'
 */
show.url = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{step}', parsedArgs.step.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\OnboardingController::show
 * @see app/Http/Controllers/Portal/OnboardingController.php:37
 * @route '/portal/onboarding/{step}'
 */
show.get = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\OnboardingController::show
 * @see app/Http/Controllers/Portal/OnboardingController.php:37
 * @route '/portal/onboarding/{step}'
 */
show.head = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\OnboardingController::show
 * @see app/Http/Controllers/Portal/OnboardingController.php:37
 * @route '/portal/onboarding/{step}'
 */
    const showForm = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\OnboardingController::show
 * @see app/Http/Controllers/Portal/OnboardingController.php:37
 * @route '/portal/onboarding/{step}'
 */
        showForm.get = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\OnboardingController::show
 * @see app/Http/Controllers/Portal/OnboardingController.php:37
 * @route '/portal/onboarding/{step}'
 */
        showForm.head = (args: { step: string | number } | [step: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    show.form = showForm
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
const OnboardingController = { redirect, show, store }

export default OnboardingController