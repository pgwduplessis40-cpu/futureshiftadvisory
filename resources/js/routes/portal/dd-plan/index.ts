import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
import sections from './sections'
import businessAdvice from './business-advice'
/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::show
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:57
 * @route '/portal/acquisition-plan'
 */
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/portal/acquisition-plan',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::show
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:57
 * @route '/portal/acquisition-plan'
 */
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::show
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:57
 * @route '/portal/acquisition-plan'
 */
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::show
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:57
 * @route '/portal/acquisition-plan'
 */
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::show
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:57
 * @route '/portal/acquisition-plan'
 */
    const showForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::show
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:57
 * @route '/portal/acquisition-plan'
 */
        showForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::show
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:57
 * @route '/portal/acquisition-plan'
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
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::preview
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:100
 * @route '/portal/acquisition-plan/preview'
 */
export const preview = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(options),
    method: 'get',
})

preview.definition = {
    methods: ["get","head"],
    url: '/portal/acquisition-plan/preview',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::preview
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:100
 * @route '/portal/acquisition-plan/preview'
 */
preview.url = (options?: RouteQueryOptions) => {
    return preview.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::preview
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:100
 * @route '/portal/acquisition-plan/preview'
 */
preview.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::preview
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:100
 * @route '/portal/acquisition-plan/preview'
 */
preview.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: preview.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::preview
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:100
 * @route '/portal/acquisition-plan/preview'
 */
    const previewForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: preview.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::preview
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:100
 * @route '/portal/acquisition-plan/preview'
 */
        previewForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: preview.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::preview
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:100
 * @route '/portal/acquisition-plan/preview'
 */
        previewForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: preview.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    preview.form = previewForm
/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:120
 * @route '/portal/acquisition-plan'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/acquisition-plan',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:120
 * @route '/portal/acquisition-plan'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:120
 * @route '/portal/acquisition-plan'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:120
 * @route '/portal/acquisition-plan'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:120
 * @route '/portal/acquisition-plan'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::complete
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:191
 * @route '/portal/acquisition-plan/complete'
 */
export const complete = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(options),
    method: 'post',
})

complete.definition = {
    methods: ["post"],
    url: '/portal/acquisition-plan/complete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::complete
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:191
 * @route '/portal/acquisition-plan/complete'
 */
complete.url = (options?: RouteQueryOptions) => {
    return complete.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::complete
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:191
 * @route '/portal/acquisition-plan/complete'
 */
complete.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::complete
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:191
 * @route '/portal/acquisition-plan/complete'
 */
    const completeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: complete.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::complete
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:191
 * @route '/portal/acquisition-plan/complete'
 */
        completeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: complete.url(options),
            method: 'post',
        })
    
    complete.form = completeForm
const ddPlan = {
    show: Object.assign(show, show),
preview: Object.assign(preview, preview),
store: Object.assign(store, store),
sections: Object.assign(sections, sections),
complete: Object.assign(complete, complete),
businessAdvice: Object.assign(businessAdvice, businessAdvice),
}

export default ddPlan