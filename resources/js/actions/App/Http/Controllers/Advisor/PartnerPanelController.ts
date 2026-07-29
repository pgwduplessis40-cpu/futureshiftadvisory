import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::brokers
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:45
 * @route '/advisor/partners/brokers'
 */
export const brokers = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: brokers.url(options),
    method: 'get',
})

brokers.definition = {
    methods: ["get","head"],
    url: '/advisor/partners/brokers',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::brokers
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:45
 * @route '/advisor/partners/brokers'
 */
brokers.url = (options?: RouteQueryOptions) => {
    return brokers.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::brokers
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:45
 * @route '/advisor/partners/brokers'
 */
brokers.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: brokers.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::brokers
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:45
 * @route '/advisor/partners/brokers'
 */
brokers.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: brokers.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::brokers
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:45
 * @route '/advisor/partners/brokers'
 */
    const brokersForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: brokers.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::brokers
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:45
 * @route '/advisor/partners/brokers'
 */
        brokersForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: brokers.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::brokers
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:45
 * @route '/advisor/partners/brokers'
 */
        brokersForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: brokers.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    brokers.form = brokersForm
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::createBroker
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:50
 * @route '/advisor/partners/brokers/invite'
 */
export const createBroker = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: createBroker.url(options),
    method: 'get',
})

createBroker.definition = {
    methods: ["get","head"],
    url: '/advisor/partners/brokers/invite',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::createBroker
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:50
 * @route '/advisor/partners/brokers/invite'
 */
createBroker.url = (options?: RouteQueryOptions) => {
    return createBroker.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::createBroker
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:50
 * @route '/advisor/partners/brokers/invite'
 */
createBroker.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: createBroker.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::createBroker
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:50
 * @route '/advisor/partners/brokers/invite'
 */
createBroker.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: createBroker.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::createBroker
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:50
 * @route '/advisor/partners/brokers/invite'
 */
    const createBrokerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: createBroker.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::createBroker
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:50
 * @route '/advisor/partners/brokers/invite'
 */
        createBrokerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: createBroker.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::createBroker
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:50
 * @route '/advisor/partners/brokers/invite'
 */
        createBrokerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: createBroker.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    createBroker.form = createBrokerForm
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::storeBroker
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:55
 * @route '/advisor/partners/brokers/invite'
 */
export const storeBroker = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeBroker.url(options),
    method: 'post',
})

storeBroker.definition = {
    methods: ["post"],
    url: '/advisor/partners/brokers/invite',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::storeBroker
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:55
 * @route '/advisor/partners/brokers/invite'
 */
storeBroker.url = (options?: RouteQueryOptions) => {
    return storeBroker.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::storeBroker
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:55
 * @route '/advisor/partners/brokers/invite'
 */
storeBroker.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeBroker.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::storeBroker
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:55
 * @route '/advisor/partners/brokers/invite'
 */
    const storeBrokerForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: storeBroker.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::storeBroker
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:55
 * @route '/advisor/partners/brokers/invite'
 */
        storeBrokerForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: storeBroker.url(options),
            method: 'post',
        })

    storeBroker.form = storeBrokerForm
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::coaches
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:60
 * @route '/advisor/partners/coaches'
 */
export const coaches = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: coaches.url(options),
    method: 'get',
})

coaches.definition = {
    methods: ["get","head"],
    url: '/advisor/partners/coaches',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::coaches
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:60
 * @route '/advisor/partners/coaches'
 */
coaches.url = (options?: RouteQueryOptions) => {
    return coaches.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::coaches
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:60
 * @route '/advisor/partners/coaches'
 */
coaches.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: coaches.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::coaches
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:60
 * @route '/advisor/partners/coaches'
 */
coaches.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: coaches.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::coaches
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:60
 * @route '/advisor/partners/coaches'
 */
    const coachesForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: coaches.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::coaches
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:60
 * @route '/advisor/partners/coaches'
 */
        coachesForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: coaches.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::coaches
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:60
 * @route '/advisor/partners/coaches'
 */
        coachesForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: coaches.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    coaches.form = coachesForm
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::createCoach
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:65
 * @route '/advisor/partners/coaches/invite'
 */
export const createCoach = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: createCoach.url(options),
    method: 'get',
})

createCoach.definition = {
    methods: ["get","head"],
    url: '/advisor/partners/coaches/invite',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::createCoach
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:65
 * @route '/advisor/partners/coaches/invite'
 */
createCoach.url = (options?: RouteQueryOptions) => {
    return createCoach.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::createCoach
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:65
 * @route '/advisor/partners/coaches/invite'
 */
createCoach.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: createCoach.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::createCoach
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:65
 * @route '/advisor/partners/coaches/invite'
 */
createCoach.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: createCoach.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::createCoach
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:65
 * @route '/advisor/partners/coaches/invite'
 */
    const createCoachForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: createCoach.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::createCoach
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:65
 * @route '/advisor/partners/coaches/invite'
 */
        createCoachForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: createCoach.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::createCoach
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:65
 * @route '/advisor/partners/coaches/invite'
 */
        createCoachForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: createCoach.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    createCoach.form = createCoachForm
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::storeCoach
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:70
 * @route '/advisor/partners/coaches/invite'
 */
export const storeCoach = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeCoach.url(options),
    method: 'post',
})

storeCoach.definition = {
    methods: ["post"],
    url: '/advisor/partners/coaches/invite',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::storeCoach
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:70
 * @route '/advisor/partners/coaches/invite'
 */
storeCoach.url = (options?: RouteQueryOptions) => {
    return storeCoach.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::storeCoach
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:70
 * @route '/advisor/partners/coaches/invite'
 */
storeCoach.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeCoach.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::storeCoach
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:70
 * @route '/advisor/partners/coaches/invite'
 */
    const storeCoachForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: storeCoach.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::storeCoach
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:70
 * @route '/advisor/partners/coaches/invite'
 */
        storeCoachForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: storeCoach.url(options),
            method: 'post',
        })

    storeCoach.form = storeCoachForm
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::resendInvite
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:75
 * @route '/advisor/partners/{panelMember}/invite/resend'
 */
export const resendInvite = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resendInvite.url(args, options),
    method: 'post',
})

resendInvite.definition = {
    methods: ["post"],
    url: '/advisor/partners/{panelMember}/invite/resend',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::resendInvite
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:75
 * @route '/advisor/partners/{panelMember}/invite/resend'
 */
resendInvite.url = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { panelMember: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { panelMember: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    panelMember: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        panelMember: typeof args.panelMember === 'object'
                ? args.panelMember.id
                : args.panelMember,
                }

    return resendInvite.definition.url
            .replace('{panelMember}', parsedArgs.panelMember.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::resendInvite
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:75
 * @route '/advisor/partners/{panelMember}/invite/resend'
 */
resendInvite.post = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resendInvite.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::resendInvite
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:75
 * @route '/advisor/partners/{panelMember}/invite/resend'
 */
    const resendInviteForm = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: resendInvite.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::resendInvite
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:75
 * @route '/advisor/partners/{panelMember}/invite/resend'
 */
        resendInviteForm.post = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: resendInvite.url(args, options),
            method: 'post',
        })

    resendInvite.form = resendInviteForm
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::cancelInvite
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:129
 * @route '/advisor/partners/{panelMember}/invite'
 */
export const cancelInvite = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: cancelInvite.url(args, options),
    method: 'delete',
})

cancelInvite.definition = {
    methods: ["delete"],
    url: '/advisor/partners/{panelMember}/invite',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::cancelInvite
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:129
 * @route '/advisor/partners/{panelMember}/invite'
 */
cancelInvite.url = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { panelMember: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { panelMember: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    panelMember: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        panelMember: typeof args.panelMember === 'object'
                ? args.panelMember.id
                : args.panelMember,
                }

    return cancelInvite.definition.url
            .replace('{panelMember}', parsedArgs.panelMember.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::cancelInvite
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:129
 * @route '/advisor/partners/{panelMember}/invite'
 */
cancelInvite.delete = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: cancelInvite.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::cancelInvite
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:129
 * @route '/advisor/partners/{panelMember}/invite'
 */
    const cancelInviteForm = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: cancelInvite.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::cancelInvite
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:129
 * @route '/advisor/partners/{panelMember}/invite'
 */
        cancelInviteForm.delete = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: cancelInvite.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    cancelInvite.form = cancelInviteForm
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::show
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:174
 * @route '/advisor/partners/{panelMember}'
 */
export const show = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/advisor/partners/{panelMember}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::show
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:174
 * @route '/advisor/partners/{panelMember}'
 */
show.url = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { panelMember: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { panelMember: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    panelMember: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        panelMember: typeof args.panelMember === 'object'
                ? args.panelMember.id
                : args.panelMember,
                }

    return show.definition.url
            .replace('{panelMember}', parsedArgs.panelMember.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::show
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:174
 * @route '/advisor/partners/{panelMember}'
 */
show.get = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::show
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:174
 * @route '/advisor/partners/{panelMember}'
 */
show.head = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::show
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:174
 * @route '/advisor/partners/{panelMember}'
 */
    const showForm = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::show
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:174
 * @route '/advisor/partners/{panelMember}'
 */
        showForm.get = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::show
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:174
 * @route '/advisor/partners/{panelMember}'
 */
        showForm.head = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    show.form = showForm
const PartnerPanelController = { brokers, createBroker, storeBroker, coaches, createCoach, storeCoach, resendInvite, cancelInvite, show }

export default PartnerPanelController