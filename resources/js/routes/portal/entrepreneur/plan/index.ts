import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
import sections from './sections'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:155
 * @route '/portal/entrepreneur/plan'
 */
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/portal/entrepreneur/plan',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:155
 * @route '/portal/entrepreneur/plan'
 */
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:155
 * @route '/portal/entrepreneur/plan'
 */
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:155
 * @route '/portal/entrepreneur/plan'
 */
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:155
 * @route '/portal/entrepreneur/plan'
 */
    const showForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:155
 * @route '/portal/entrepreneur/plan'
 */
        showForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:155
 * @route '/portal/entrepreneur/plan'
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
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::preview
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:189
 * @route '/portal/entrepreneur/plan/preview'
 */
export const preview = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(options),
    method: 'get',
})

preview.definition = {
    methods: ["get","head"],
    url: '/portal/entrepreneur/plan/preview',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::preview
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:189
 * @route '/portal/entrepreneur/plan/preview'
 */
preview.url = (options?: RouteQueryOptions) => {
    return preview.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::preview
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:189
 * @route '/portal/entrepreneur/plan/preview'
 */
preview.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::preview
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:189
 * @route '/portal/entrepreneur/plan/preview'
 */
preview.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: preview.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::preview
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:189
 * @route '/portal/entrepreneur/plan/preview'
 */
    const previewForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: preview.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::preview
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:189
 * @route '/portal/entrepreneur/plan/preview'
 */
        previewForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: preview.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::preview
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:189
 * @route '/portal/entrepreneur/plan/preview'
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
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::start
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:244
 * @route '/portal/entrepreneur/plan/start'
 */
export const start = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: start.url(options),
    method: 'post',
})

start.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/start',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::start
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:244
 * @route '/portal/entrepreneur/plan/start'
 */
start.url = (options?: RouteQueryOptions) => {
    return start.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::start
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:244
 * @route '/portal/entrepreneur/plan/start'
 */
start.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: start.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::start
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:244
 * @route '/portal/entrepreneur/plan/start'
 */
    const startForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: start.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::start
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:244
 * @route '/portal/entrepreneur/plan/start'
 */
        startForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: start.url(options),
            method: 'post',
        })
    
    start.form = startForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::submit
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:325
 * @route '/portal/entrepreneur/plan/submit'
 */
export const submit = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(options),
    method: 'post',
})

submit.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/submit',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::submit
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:325
 * @route '/portal/entrepreneur/plan/submit'
 */
submit.url = (options?: RouteQueryOptions) => {
    return submit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::submit
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:325
 * @route '/portal/entrepreneur/plan/submit'
 */
submit.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::submit
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:325
 * @route '/portal/entrepreneur/plan/submit'
 */
    const submitForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: submit.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::submit
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:325
 * @route '/portal/entrepreneur/plan/submit'
 */
        submitForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: submit.url(options),
            method: 'post',
        })
    
    submit.form = submitForm
const plan = {
    show: Object.assign(show, show),
preview: Object.assign(preview, preview),
start: Object.assign(start, start),
sections: Object.assign(sections, sections),
submit: Object.assign(submit, submit),
}

export default plan