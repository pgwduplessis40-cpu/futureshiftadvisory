import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\AuditTrailController::__invoke
 * @see app/Http/Controllers/Admin/AuditTrailController.php:19
 * @route '/admin/audit-trail'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/audit-trail',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\AuditTrailController::__invoke
 * @see app/Http/Controllers/Admin/AuditTrailController.php:19
 * @route '/admin/audit-trail'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AuditTrailController::__invoke
 * @see app/Http/Controllers/Admin/AuditTrailController.php:19
 * @route '/admin/audit-trail'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\AuditTrailController::__invoke
 * @see app/Http/Controllers/Admin/AuditTrailController.php:19
 * @route '/admin/audit-trail'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\AuditTrailController::__invoke
 * @see app/Http/Controllers/Admin/AuditTrailController.php:19
 * @route '/admin/audit-trail'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\AuditTrailController::__invoke
 * @see app/Http/Controllers/Admin/AuditTrailController.php:19
 * @route '/admin/audit-trail'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\AuditTrailController::__invoke
 * @see app/Http/Controllers/Admin/AuditTrailController.php:19
 * @route '/admin/audit-trail'
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
const auditTrail = {
    index: Object.assign(index, index),
}

export default auditTrail