import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\AuditTrailController::__invoke
 * @see app/Http/Controllers/Admin/AuditTrailController.php:19
 * @route '/admin/audit-trail'
 */
const AuditTrailController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AuditTrailController.url(options),
    method: 'get',
})

AuditTrailController.definition = {
    methods: ["get","head"],
    url: '/admin/audit-trail',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\AuditTrailController::__invoke
 * @see app/Http/Controllers/Admin/AuditTrailController.php:19
 * @route '/admin/audit-trail'
 */
AuditTrailController.url = (options?: RouteQueryOptions) => {
    return AuditTrailController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\AuditTrailController::__invoke
 * @see app/Http/Controllers/Admin/AuditTrailController.php:19
 * @route '/admin/audit-trail'
 */
AuditTrailController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AuditTrailController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\AuditTrailController::__invoke
 * @see app/Http/Controllers/Admin/AuditTrailController.php:19
 * @route '/admin/audit-trail'
 */
AuditTrailController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AuditTrailController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\AuditTrailController::__invoke
 * @see app/Http/Controllers/Admin/AuditTrailController.php:19
 * @route '/admin/audit-trail'
 */
    const AuditTrailControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: AuditTrailController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\AuditTrailController::__invoke
 * @see app/Http/Controllers/Admin/AuditTrailController.php:19
 * @route '/admin/audit-trail'
 */
        AuditTrailControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: AuditTrailController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\AuditTrailController::__invoke
 * @see app/Http/Controllers/Admin/AuditTrailController.php:19
 * @route '/admin/audit-trail'
 */
        AuditTrailControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: AuditTrailController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    AuditTrailController.form = AuditTrailControllerForm
export default AuditTrailController