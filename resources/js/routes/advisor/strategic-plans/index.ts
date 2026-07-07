import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::pdf
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:101
 * @route '/advisor/strategic-plans/{strategicPlan}/pdf'
 */
export const pdf = (args: { strategicPlan: string | { id: string } } | [strategicPlan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pdf.url(args, options),
    method: 'get',
})

pdf.definition = {
    methods: ["get","head"],
    url: '/advisor/strategic-plans/{strategicPlan}/pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::pdf
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:101
 * @route '/advisor/strategic-plans/{strategicPlan}/pdf'
 */
pdf.url = (args: { strategicPlan: string | { id: string } } | [strategicPlan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { strategicPlan: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { strategicPlan: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    strategicPlan: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        strategicPlan: typeof args.strategicPlan === 'object'
                ? args.strategicPlan.id
                : args.strategicPlan,
                }

    return pdf.definition.url
            .replace('{strategicPlan}', parsedArgs.strategicPlan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::pdf
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:101
 * @route '/advisor/strategic-plans/{strategicPlan}/pdf'
 */
pdf.get = (args: { strategicPlan: string | { id: string } } | [strategicPlan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pdf.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::pdf
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:101
 * @route '/advisor/strategic-plans/{strategicPlan}/pdf'
 */
pdf.head = (args: { strategicPlan: string | { id: string } } | [strategicPlan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pdf.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::pdf
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:101
 * @route '/advisor/strategic-plans/{strategicPlan}/pdf'
 */
    const pdfForm = (args: { strategicPlan: string | { id: string } } | [strategicPlan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: pdf.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::pdf
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:101
 * @route '/advisor/strategic-plans/{strategicPlan}/pdf'
 */
        pdfForm.get = (args: { strategicPlan: string | { id: string } } | [strategicPlan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: pdf.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::pdf
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:101
 * @route '/advisor/strategic-plans/{strategicPlan}/pdf'
 */
        pdfForm.head = (args: { strategicPlan: string | { id: string } } | [strategicPlan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: pdf.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    pdf.form = pdfForm
/**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::update
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:51
 * @route '/advisor/strategic-plans/{strategicPlan}'
 */
export const update = (args: { strategicPlan: string | { id: string } } | [strategicPlan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/advisor/strategic-plans/{strategicPlan}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::update
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:51
 * @route '/advisor/strategic-plans/{strategicPlan}'
 */
update.url = (args: { strategicPlan: string | { id: string } } | [strategicPlan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { strategicPlan: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { strategicPlan: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    strategicPlan: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        strategicPlan: typeof args.strategicPlan === 'object'
                ? args.strategicPlan.id
                : args.strategicPlan,
                }

    return update.definition.url
            .replace('{strategicPlan}', parsedArgs.strategicPlan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::update
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:51
 * @route '/advisor/strategic-plans/{strategicPlan}'
 */
update.patch = (args: { strategicPlan: string | { id: string } } | [strategicPlan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::update
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:51
 * @route '/advisor/strategic-plans/{strategicPlan}'
 */
    const updateForm = (args: { strategicPlan: string | { id: string } } | [strategicPlan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::update
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:51
 * @route '/advisor/strategic-plans/{strategicPlan}'
 */
        updateForm.patch = (args: { strategicPlan: string | { id: string } } | [strategicPlan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    update.form = updateForm
/**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::deploy
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:87
 * @route '/advisor/strategic-plans/{strategicPlan}/deploy'
 */
export const deploy = (args: { strategicPlan: string | { id: string } } | [strategicPlan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: deploy.url(args, options),
    method: 'patch',
})

deploy.definition = {
    methods: ["patch"],
    url: '/advisor/strategic-plans/{strategicPlan}/deploy',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::deploy
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:87
 * @route '/advisor/strategic-plans/{strategicPlan}/deploy'
 */
deploy.url = (args: { strategicPlan: string | { id: string } } | [strategicPlan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { strategicPlan: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { strategicPlan: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    strategicPlan: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        strategicPlan: typeof args.strategicPlan === 'object'
                ? args.strategicPlan.id
                : args.strategicPlan,
                }

    return deploy.definition.url
            .replace('{strategicPlan}', parsedArgs.strategicPlan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::deploy
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:87
 * @route '/advisor/strategic-plans/{strategicPlan}/deploy'
 */
deploy.patch = (args: { strategicPlan: string | { id: string } } | [strategicPlan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: deploy.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::deploy
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:87
 * @route '/advisor/strategic-plans/{strategicPlan}/deploy'
 */
    const deployForm = (args: { strategicPlan: string | { id: string } } | [strategicPlan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: deploy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::deploy
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:87
 * @route '/advisor/strategic-plans/{strategicPlan}/deploy'
 */
        deployForm.patch = (args: { strategicPlan: string | { id: string } } | [strategicPlan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: deploy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    deploy.form = deployForm
const strategicPlans = {
    pdf: Object.assign(pdf, pdf),
update: Object.assign(update, update),
deploy: Object.assign(deploy, deploy),
}

export default strategicPlans