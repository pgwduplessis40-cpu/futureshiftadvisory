import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:38
 * @route '/terms'
 */
const show619dc3a99425f668ea9cab64e6648cb4 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show619dc3a99425f668ea9cab64e6648cb4.url(options),
    method: 'get',
})

show619dc3a99425f668ea9cab64e6648cb4.definition = {
    methods: ["get","head"],
    url: '/terms',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:38
 * @route '/terms'
 */
show619dc3a99425f668ea9cab64e6648cb4.url = (options?: RouteQueryOptions) => {
    return show619dc3a99425f668ea9cab64e6648cb4.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:38
 * @route '/terms'
 */
show619dc3a99425f668ea9cab64e6648cb4.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show619dc3a99425f668ea9cab64e6648cb4.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:38
 * @route '/terms'
 */
show619dc3a99425f668ea9cab64e6648cb4.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show619dc3a99425f668ea9cab64e6648cb4.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:38
 * @route '/terms'
 */
    const show619dc3a99425f668ea9cab64e6648cb4Form = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show619dc3a99425f668ea9cab64e6648cb4.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:38
 * @route '/terms'
 */
        show619dc3a99425f668ea9cab64e6648cb4Form.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show619dc3a99425f668ea9cab64e6648cb4.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:38
 * @route '/terms'
 */
        show619dc3a99425f668ea9cab64e6648cb4Form.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show619dc3a99425f668ea9cab64e6648cb4.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show619dc3a99425f668ea9cab64e6648cb4.form = show619dc3a99425f668ea9cab64e6648cb4Form
    /**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:38
 * @route '/terms/pending'
 */
const show5487df76140b55c3bdaed66107ccd928 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show5487df76140b55c3bdaed66107ccd928.url(options),
    method: 'get',
})

show5487df76140b55c3bdaed66107ccd928.definition = {
    methods: ["get","head"],
    url: '/terms/pending',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:38
 * @route '/terms/pending'
 */
show5487df76140b55c3bdaed66107ccd928.url = (options?: RouteQueryOptions) => {
    return show5487df76140b55c3bdaed66107ccd928.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:38
 * @route '/terms/pending'
 */
show5487df76140b55c3bdaed66107ccd928.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show5487df76140b55c3bdaed66107ccd928.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:38
 * @route '/terms/pending'
 */
show5487df76140b55c3bdaed66107ccd928.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show5487df76140b55c3bdaed66107ccd928.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:38
 * @route '/terms/pending'
 */
    const show5487df76140b55c3bdaed66107ccd928Form = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show5487df76140b55c3bdaed66107ccd928.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:38
 * @route '/terms/pending'
 */
        show5487df76140b55c3bdaed66107ccd928Form.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show5487df76140b55c3bdaed66107ccd928.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::show
 * @see app/Http/Controllers/Auth/TermsPendingController.php:38
 * @route '/terms/pending'
 */
        show5487df76140b55c3bdaed66107ccd928Form.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show5487df76140b55c3bdaed66107ccd928.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show5487df76140b55c3bdaed66107ccd928.form = show5487df76140b55c3bdaed66107ccd928Form

/**
* Multiple routes resolve to \App\Http\Controllers\Auth\TermsPendingController::show, so this export is a
* dictionary keyed by URI rather than a callable. Call a specific route with `show['<uri>'](...)`,
* or import the route by name from your generated `routes/` directory.
*/
export const show = {
    '/terms': show619dc3a99425f668ea9cab64e6648cb4,
    '/terms/pending': show5487df76140b55c3bdaed66107ccd928,
}

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::download
 * @see app/Http/Controllers/Auth/TermsPendingController.php:61
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
 * @see app/Http/Controllers/Auth/TermsPendingController.php:61
 * @route '/terms/download'
 */
download.url = (options?: RouteQueryOptions) => {
    return download.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::download
 * @see app/Http/Controllers/Auth/TermsPendingController.php:61
 * @route '/terms/download'
 */
download.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::download
 * @see app/Http/Controllers/Auth/TermsPendingController.php:61
 * @route '/terms/download'
 */
download.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Auth\TermsPendingController::download
 * @see app/Http/Controllers/Auth/TermsPendingController.php:61
 * @route '/terms/download'
 */
    const downloadForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: download.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::download
 * @see app/Http/Controllers/Auth/TermsPendingController.php:61
 * @route '/terms/download'
 */
        downloadForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::download
 * @see app/Http/Controllers/Auth/TermsPendingController.php:61
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
 * @see app/Http/Controllers/Auth/TermsPendingController.php:91
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
 * @see app/Http/Controllers/Auth/TermsPendingController.php:91
 * @route '/terms/accept'
 */
accept.url = (options?: RouteQueryOptions) => {
    return accept.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::accept
 * @see app/Http/Controllers/Auth/TermsPendingController.php:91
 * @route '/terms/accept'
 */
accept.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: accept.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Auth\TermsPendingController::accept
 * @see app/Http/Controllers/Auth/TermsPendingController.php:91
 * @route '/terms/accept'
 */
    const acceptForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: accept.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::accept
 * @see app/Http/Controllers/Auth/TermsPendingController.php:91
 * @route '/terms/accept'
 */
        acceptForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: accept.url(options),
            method: 'post',
        })
    
    accept.form = acceptForm
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::decline
 * @see app/Http/Controllers/Auth/TermsPendingController.php:141
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
 * @see app/Http/Controllers/Auth/TermsPendingController.php:141
 * @route '/terms/decline'
 */
decline.url = (options?: RouteQueryOptions) => {
    return decline.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::decline
 * @see app/Http/Controllers/Auth/TermsPendingController.php:141
 * @route '/terms/decline'
 */
decline.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: decline.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Auth\TermsPendingController::decline
 * @see app/Http/Controllers/Auth/TermsPendingController.php:141
 * @route '/terms/decline'
 */
    const declineForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: decline.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::decline
 * @see app/Http/Controllers/Auth/TermsPendingController.php:141
 * @route '/terms/decline'
 */
        declineForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: decline.url(options),
            method: 'post',
        })
    
    decline.form = declineForm
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::declined
 * @see app/Http/Controllers/Auth/TermsPendingController.php:185
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
 * @see app/Http/Controllers/Auth/TermsPendingController.php:185
 * @route '/terms/declined'
 */
declined.url = (options?: RouteQueryOptions) => {
    return declined.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::declined
 * @see app/Http/Controllers/Auth/TermsPendingController.php:185
 * @route '/terms/declined'
 */
declined.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: declined.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::declined
 * @see app/Http/Controllers/Auth/TermsPendingController.php:185
 * @route '/terms/declined'
 */
declined.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: declined.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Auth\TermsPendingController::declined
 * @see app/Http/Controllers/Auth/TermsPendingController.php:185
 * @route '/terms/declined'
 */
    const declinedForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: declined.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::declined
 * @see app/Http/Controllers/Auth/TermsPendingController.php:185
 * @route '/terms/declined'
 */
        declinedForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: declined.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::declined
 * @see app/Http/Controllers/Auth/TermsPendingController.php:185
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
const TermsPendingController = { show, download, accept, decline, declined }

export default TermsPendingController