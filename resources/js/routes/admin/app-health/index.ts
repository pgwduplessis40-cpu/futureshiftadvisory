import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\OperationalHealthController::index
 * @see app/Http/Controllers/Admin/OperationalHealthController.php:24
 * @route '/admin/app-health'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/app-health',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\OperationalHealthController::index
 * @see app/Http/Controllers/Admin/OperationalHealthController.php:24
 * @route '/admin/app-health'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\OperationalHealthController::index
 * @see app/Http/Controllers/Admin/OperationalHealthController.php:24
 * @route '/admin/app-health'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\OperationalHealthController::index
 * @see app/Http/Controllers/Admin/OperationalHealthController.php:24
 * @route '/admin/app-health'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\OperationalHealthController::index
 * @see app/Http/Controllers/Admin/OperationalHealthController.php:24
 * @route '/admin/app-health'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\OperationalHealthController::index
 * @see app/Http/Controllers/Admin/OperationalHealthController.php:24
 * @route '/admin/app-health'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\OperationalHealthController::index
 * @see app/Http/Controllers/Admin/OperationalHealthController.php:24
 * @route '/admin/app-health'
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
* @see \App\Http\Controllers\Admin\OperationalHealthController::run
 * @see app/Http/Controllers/Admin/OperationalHealthController.php:69
 * @route '/admin/app-health/run'
 */
export const run = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: run.url(options),
    method: 'post',
})

run.definition = {
    methods: ["post"],
    url: '/admin/app-health/run',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\OperationalHealthController::run
 * @see app/Http/Controllers/Admin/OperationalHealthController.php:69
 * @route '/admin/app-health/run'
 */
run.url = (options?: RouteQueryOptions) => {
    return run.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\OperationalHealthController::run
 * @see app/Http/Controllers/Admin/OperationalHealthController.php:69
 * @route '/admin/app-health/run'
 */
run.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: run.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\OperationalHealthController::run
 * @see app/Http/Controllers/Admin/OperationalHealthController.php:69
 * @route '/admin/app-health/run'
 */
    const runForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: run.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\OperationalHealthController::run
 * @see app/Http/Controllers/Admin/OperationalHealthController.php:69
 * @route '/admin/app-health/run'
 */
        runForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: run.url(options),
            method: 'post',
        })

    run.form = runForm
const appHealth = {
    index: Object.assign(index, index),
run: Object.assign(run, run),
}

export default appHealth