import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:33
 * @route '/terms'
 */
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/terms',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:33
 * @route '/terms'
 */
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:33
 * @route '/terms'
 */
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:33
 * @route '/terms'
 */
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:33
 * @route '/terms'
 */
    const showForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:33
 * @route '/terms'
 */
        showForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:33
 * @route '/terms'
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
* @see \App\Http\Controllers\Auth\TermsPendingController::pending
 * @see app/Http/Controllers/Auth/TermsPendingController.php:33
 * @route '/terms/pending'
 */
export const pending = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pending.url(options),
    method: 'get',
})

pending.definition = {
    methods: ["get","head"],
    url: '/terms/pending',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::pending
 * @see app/Http/Controllers/Auth/TermsPendingController.php:33
 * @route '/terms/pending'
 */
pending.url = (options?: RouteQueryOptions) => {
    return pending.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::pending
 * @see app/Http/Controllers/Auth/TermsPendingController.php:33
 * @route '/terms/pending'
 */
pending.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pending.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::pending
 * @see app/Http/Controllers/Auth/TermsPendingController.php:33
 * @route '/terms/pending'
 */
pending.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pending.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Auth\TermsPendingController::pending
 * @see app/Http/Controllers/Auth/TermsPendingController.php:33
 * @route '/terms/pending'
 */
    const pendingForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: pending.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::pending
 * @see app/Http/Controllers/Auth/TermsPendingController.php:33
 * @route '/terms/pending'
 */
        pendingForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: pending.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::pending
 * @see app/Http/Controllers/Auth/TermsPendingController.php:33
 * @route '/terms/pending'
 */
        pendingForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: pending.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    pending.form = pendingForm
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::download
 * @see app/Http/Controllers/Auth/TermsPendingController.php:56
 * @route '/terms/download'
 */
export const download = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/terms/download',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::download
 * @see app/Http/Controllers/Auth/TermsPendingController.php:56
 * @route '/terms/download'
 */
download.url = (options?: RouteQueryOptions) => {
    return download.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::download
 * @see app/Http/Controllers/Auth/TermsPendingController.php:56
 * @route '/terms/download'
 */
download.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::download
 * @see app/Http/Controllers/Auth/TermsPendingController.php:56
 * @route '/terms/download'
 */
download.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Auth\TermsPendingController::download
 * @see app/Http/Controllers/Auth/TermsPendingController.php:56
 * @route '/terms/download'
 */
    const downloadForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: download.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::download
 * @see app/Http/Controllers/Auth/TermsPendingController.php:56
 * @route '/terms/download'
 */
        downloadForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::download
 * @see app/Http/Controllers/Auth/TermsPendingController.php:56
 * @route '/terms/download'
 */
        downloadForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    download.form = downloadForm
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::accept
 * @see app/Http/Controllers/Auth/TermsPendingController.php:80
 * @route '/terms/accept'
 */
export const accept = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: accept.url(options),
    method: 'post',
})

accept.definition = {
    methods: ["post"],
    url: '/terms/accept',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::accept
 * @see app/Http/Controllers/Auth/TermsPendingController.php:80
 * @route '/terms/accept'
 */
accept.url = (options?: RouteQueryOptions) => {
    return accept.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::accept
 * @see app/Http/Controllers/Auth/TermsPendingController.php:80
 * @route '/terms/accept'
 */
accept.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: accept.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Auth\TermsPendingController::accept
 * @see app/Http/Controllers/Auth/TermsPendingController.php:80
 * @route '/terms/accept'
 */
    const acceptForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: accept.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::accept
 * @see app/Http/Controllers/Auth/TermsPendingController.php:80
 * @route '/terms/accept'
 */
        acceptForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: accept.url(options),
            method: 'post',
        })

    accept.form = acceptForm
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::decline
 * @see app/Http/Controllers/Auth/TermsPendingController.php:130
 * @route '/terms/decline'
 */
export const decline = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: decline.url(options),
    method: 'post',
})

decline.definition = {
    methods: ["post"],
    url: '/terms/decline',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::decline
 * @see app/Http/Controllers/Auth/TermsPendingController.php:130
 * @route '/terms/decline'
 */
decline.url = (options?: RouteQueryOptions) => {
    return decline.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::decline
 * @see app/Http/Controllers/Auth/TermsPendingController.php:130
 * @route '/terms/decline'
 */
decline.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: decline.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Auth\TermsPendingController::decline
 * @see app/Http/Controllers/Auth/TermsPendingController.php:130
 * @route '/terms/decline'
 */
    const declineForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: decline.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::decline
 * @see app/Http/Controllers/Auth/TermsPendingController.php:130
 * @route '/terms/decline'
 */
        declineForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: decline.url(options),
            method: 'post',
        })

    decline.form = declineForm
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::declined
 * @see app/Http/Controllers/Auth/TermsPendingController.php:170
 * @route '/terms/declined'
 */
export const declined = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: declined.url(options),
    method: 'get',
})

declined.definition = {
    methods: ["get","head"],
    url: '/terms/declined',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::declined
 * @see app/Http/Controllers/Auth/TermsPendingController.php:170
 * @route '/terms/declined'
 */
declined.url = (options?: RouteQueryOptions) => {
    return declined.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::declined
 * @see app/Http/Controllers/Auth/TermsPendingController.php:170
 * @route '/terms/declined'
 */
declined.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: declined.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::declined
 * @see app/Http/Controllers/Auth/TermsPendingController.php:170
 * @route '/terms/declined'
 */
declined.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: declined.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Auth\TermsPendingController::declined
 * @see app/Http/Controllers/Auth/TermsPendingController.php:170
 * @route '/terms/declined'
 */
    const declinedForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: declined.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::declined
 * @see app/Http/Controllers/Auth/TermsPendingController.php:170
 * @route '/terms/declined'
 */
        declinedForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: declined.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::declined
 * @see app/Http/Controllers/Auth/TermsPendingController.php:170
 * @route '/terms/declined'
 */
        declinedForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: declined.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    declined.form = declinedForm
const terms = {
    show: Object.assign(show, show),
pending: Object.assign(pending, pending),
download: Object.assign(download, download),
accept: Object.assign(accept, accept),
decline: Object.assign(decline, decline),
declined: Object.assign(declined, declined),
}

export default terms