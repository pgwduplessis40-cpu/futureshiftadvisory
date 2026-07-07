import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::show
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:30
 * @route '/portal/business-plan-budget'
 */
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/portal/business-plan-budget',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::show
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:30
 * @route '/portal/business-plan-budget'
 */
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::show
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:30
 * @route '/portal/business-plan-budget'
 */
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::show
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:30
 * @route '/portal/business-plan-budget'
 */
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::show
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:30
 * @route '/portal/business-plan-budget'
 */
    const showForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::show
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:30
 * @route '/portal/business-plan-budget'
 */
        showForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::show
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:30
 * @route '/portal/business-plan-budget'
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
* @see \App\Http\Controllers\Portal\StrategicBudgetController::update
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:54
 * @route '/portal/business-plan-budget'
 */
export const update = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(options),
    method: 'post',
})

update.definition = {
    methods: ["post"],
    url: '/portal/business-plan-budget',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::update
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:54
 * @route '/portal/business-plan-budget'
 */
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::update
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:54
 * @route '/portal/business-plan-budget'
 */
update.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::update
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:54
 * @route '/portal/business-plan-budget'
 */
    const updateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::update
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:54
 * @route '/portal/business-plan-budget'
 */
        updateForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(options),
            method: 'post',
        })

    update.form = updateForm
/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::submit
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:67
 * @route '/portal/business-plan-budget/submit'
 */
export const submit = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(options),
    method: 'post',
})

submit.definition = {
    methods: ["post"],
    url: '/portal/business-plan-budget/submit',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::submit
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:67
 * @route '/portal/business-plan-budget/submit'
 */
submit.url = (options?: RouteQueryOptions) => {
    return submit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::submit
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:67
 * @route '/portal/business-plan-budget/submit'
 */
submit.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::submit
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:67
 * @route '/portal/business-plan-budget/submit'
 */
    const submitForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: submit.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::submit
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:67
 * @route '/portal/business-plan-budget/submit'
 */
        submitForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: submit.url(options),
            method: 'post',
        })

    submit.form = submitForm
/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::exportMethod
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:90
 * @route '/portal/business-plan-budget/export'
 */
export const exportMethod = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(options),
    method: 'get',
})

exportMethod.definition = {
    methods: ["get","head"],
    url: '/portal/business-plan-budget/export',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::exportMethod
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:90
 * @route '/portal/business-plan-budget/export'
 */
exportMethod.url = (options?: RouteQueryOptions) => {
    return exportMethod.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::exportMethod
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:90
 * @route '/portal/business-plan-budget/export'
 */
exportMethod.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::exportMethod
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:90
 * @route '/portal/business-plan-budget/export'
 */
exportMethod.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportMethod.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::exportMethod
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:90
 * @route '/portal/business-plan-budget/export'
 */
    const exportMethodForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: exportMethod.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::exportMethod
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:90
 * @route '/portal/business-plan-budget/export'
 */
        exportMethodForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: exportMethod.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::exportMethod
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:90
 * @route '/portal/business-plan-budget/export'
 */
        exportMethodForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: exportMethod.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    exportMethod.form = exportMethodForm
const StrategicBudgetController = { show, update, submit, exportMethod, export: exportMethod }

export default StrategicBudgetController