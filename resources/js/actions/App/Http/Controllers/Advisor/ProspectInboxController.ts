import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\ProspectInboxController::index
 * @see app/Http/Controllers/Advisor/ProspectInboxController.php:25
 * @route '/advisor/prospects'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/advisor/prospects',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ProspectInboxController::index
 * @see app/Http/Controllers/Advisor/ProspectInboxController.php:25
 * @route '/advisor/prospects'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ProspectInboxController::index
 * @see app/Http/Controllers/Advisor/ProspectInboxController.php:25
 * @route '/advisor/prospects'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ProspectInboxController::index
 * @see app/Http/Controllers/Advisor/ProspectInboxController.php:25
 * @route '/advisor/prospects'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ProspectInboxController::index
 * @see app/Http/Controllers/Advisor/ProspectInboxController.php:25
 * @route '/advisor/prospects'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ProspectInboxController::index
 * @see app/Http/Controllers/Advisor/ProspectInboxController.php:25
 * @route '/advisor/prospects'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ProspectInboxController::index
 * @see app/Http/Controllers/Advisor/ProspectInboxController.php:25
 * @route '/advisor/prospects'
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
* @see \App\Http\Controllers\Advisor\ProspectInboxController::triage
 * @see app/Http/Controllers/Advisor/ProspectInboxController.php:45
 * @route '/advisor/prospects/{prospectLead}/triage'
 */
export const triage = (args: { prospectLead: number | { id: number } } | [prospectLead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: triage.url(args, options),
    method: 'patch',
})

triage.definition = {
    methods: ["patch"],
    url: '/advisor/prospects/{prospectLead}/triage',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\ProspectInboxController::triage
 * @see app/Http/Controllers/Advisor/ProspectInboxController.php:45
 * @route '/advisor/prospects/{prospectLead}/triage'
 */
triage.url = (args: { prospectLead: number | { id: number } } | [prospectLead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { prospectLead: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { prospectLead: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    prospectLead: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        prospectLead: typeof args.prospectLead === 'object'
                ? args.prospectLead.id
                : args.prospectLead,
                }

    return triage.definition.url
            .replace('{prospectLead}', parsedArgs.prospectLead.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ProspectInboxController::triage
 * @see app/Http/Controllers/Advisor/ProspectInboxController.php:45
 * @route '/advisor/prospects/{prospectLead}/triage'
 */
triage.patch = (args: { prospectLead: number | { id: number } } | [prospectLead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: triage.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\ProspectInboxController::triage
 * @see app/Http/Controllers/Advisor/ProspectInboxController.php:45
 * @route '/advisor/prospects/{prospectLead}/triage'
 */
    const triageForm = (args: { prospectLead: number | { id: number } } | [prospectLead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: triage.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ProspectInboxController::triage
 * @see app/Http/Controllers/Advisor/ProspectInboxController.php:45
 * @route '/advisor/prospects/{prospectLead}/triage'
 */
        triageForm.patch = (args: { prospectLead: number | { id: number } } | [prospectLead: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: triage.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    triage.form = triageForm
const ProspectInboxController = { index, triage }

export default ProspectInboxController