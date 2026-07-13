import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import integrationScopes from './integration-scopes'
import integrationScoping from './integration-scoping'
import invite9ae5a1 from './invite'
import offboarding from './offboarding'
import lifecycle from './lifecycle'
import knowledgeAssessments from './knowledge-assessments'
import knowledgeDrafts from './knowledge-drafts'
import goals from './goals'
import proposals from './proposals'
import strategicBudget from './strategic-budget'
import reports from './reports'
import surveyAssignments from './survey-assignments'
import standardAdvisory from './standard-advisory'
import healthRadar from './health-radar'
import meetings from './meetings'
import email from './email'
import messages from './messages'
import accounting from './accounting'
import testimonials from './testimonials'
import voiceNotes from './voice-notes'
import callLogs from './call-logs'
/**
* @see \App\Http\Controllers\Advisor\ClientController::index
 * @see app/Http/Controllers/Advisor/ClientController.php:82
 * @route '/advisor/clients'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/advisor/clients',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ClientController::index
 * @see app/Http/Controllers/Advisor/ClientController.php:82
 * @route '/advisor/clients'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientController::index
 * @see app/Http/Controllers/Advisor/ClientController.php:82
 * @route '/advisor/clients'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ClientController::index
 * @see app/Http/Controllers/Advisor/ClientController.php:82
 * @route '/advisor/clients'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientController::index
 * @see app/Http/Controllers/Advisor/ClientController.php:82
 * @route '/advisor/clients'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientController::index
 * @see app/Http/Controllers/Advisor/ClientController.php:82
 * @route '/advisor/clients'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ClientController::index
 * @see app/Http/Controllers/Advisor/ClientController.php:82
 * @route '/advisor/clients'
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
* @see \App\Http\Controllers\Advisor\ClientController::create
 * @see app/Http/Controllers/Advisor/ClientController.php:171
 * @route '/advisor/clients/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/advisor/clients/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ClientController::create
 * @see app/Http/Controllers/Advisor/ClientController.php:171
 * @route '/advisor/clients/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientController::create
 * @see app/Http/Controllers/Advisor/ClientController.php:171
 * @route '/advisor/clients/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ClientController::create
 * @see app/Http/Controllers/Advisor/ClientController.php:171
 * @route '/advisor/clients/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientController::create
 * @see app/Http/Controllers/Advisor/ClientController.php:171
 * @route '/advisor/clients/create'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientController::create
 * @see app/Http/Controllers/Advisor/ClientController.php:171
 * @route '/advisor/clients/create'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ClientController::create
 * @see app/Http/Controllers/Advisor/ClientController.php:171
 * @route '/advisor/clients/create'
 */
        createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    create.form = createForm
/**
* @see \App\Http\Controllers\Advisor\ClientController::invite
 * @see app/Http/Controllers/Advisor/ClientController.php:178
 * @route '/advisor/clients/invite'
 */
export const invite = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: invite.url(options),
    method: 'get',
})

invite.definition = {
    methods: ["get","head"],
    url: '/advisor/clients/invite',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ClientController::invite
 * @see app/Http/Controllers/Advisor/ClientController.php:178
 * @route '/advisor/clients/invite'
 */
invite.url = (options?: RouteQueryOptions) => {
    return invite.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientController::invite
 * @see app/Http/Controllers/Advisor/ClientController.php:178
 * @route '/advisor/clients/invite'
 */
invite.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: invite.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ClientController::invite
 * @see app/Http/Controllers/Advisor/ClientController.php:178
 * @route '/advisor/clients/invite'
 */
invite.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: invite.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientController::invite
 * @see app/Http/Controllers/Advisor/ClientController.php:178
 * @route '/advisor/clients/invite'
 */
    const inviteForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: invite.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientController::invite
 * @see app/Http/Controllers/Advisor/ClientController.php:178
 * @route '/advisor/clients/invite'
 */
        inviteForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: invite.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ClientController::invite
 * @see app/Http/Controllers/Advisor/ClientController.php:178
 * @route '/advisor/clients/invite'
 */
        inviteForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: invite.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    invite.form = inviteForm
/**
* @see \App\Http\Controllers\Advisor\ClientController::lookupNzbn
 * @see app/Http/Controllers/Advisor/ClientController.php:251
 * @route '/advisor/clients/lookup-nzbn'
 */
export const lookupNzbn = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: lookupNzbn.url(options),
    method: 'post',
})

lookupNzbn.definition = {
    methods: ["post"],
    url: '/advisor/clients/lookup-nzbn',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ClientController::lookupNzbn
 * @see app/Http/Controllers/Advisor/ClientController.php:251
 * @route '/advisor/clients/lookup-nzbn'
 */
lookupNzbn.url = (options?: RouteQueryOptions) => {
    return lookupNzbn.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientController::lookupNzbn
 * @see app/Http/Controllers/Advisor/ClientController.php:251
 * @route '/advisor/clients/lookup-nzbn'
 */
lookupNzbn.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: lookupNzbn.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientController::lookupNzbn
 * @see app/Http/Controllers/Advisor/ClientController.php:251
 * @route '/advisor/clients/lookup-nzbn'
 */
    const lookupNzbnForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: lookupNzbn.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientController::lookupNzbn
 * @see app/Http/Controllers/Advisor/ClientController.php:251
 * @route '/advisor/clients/lookup-nzbn'
 */
        lookupNzbnForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: lookupNzbn.url(options),
            method: 'post',
        })
    
    lookupNzbn.form = lookupNzbnForm
/**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:265
 * @route '/advisor/clients'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/clients',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:265
 * @route '/advisor/clients'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:265
 * @route '/advisor/clients'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:265
 * @route '/advisor/clients'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:265
 * @route '/advisor/clients'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Advisor\SurveyResultController::surveys
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:18
 * @route '/advisor/clients/{client}/surveys'
 */
export const surveys = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: surveys.url(args, options),
    method: 'get',
})

surveys.definition = {
    methods: ["get","head"],
    url: '/advisor/clients/{client}/surveys',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\SurveyResultController::surveys
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:18
 * @route '/advisor/clients/{client}/surveys'
 */
surveys.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { client: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { client: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                }

    return surveys.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\SurveyResultController::surveys
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:18
 * @route '/advisor/clients/{client}/surveys'
 */
surveys.get = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: surveys.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\SurveyResultController::surveys
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:18
 * @route '/advisor/clients/{client}/surveys'
 */
surveys.head = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: surveys.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\SurveyResultController::surveys
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:18
 * @route '/advisor/clients/{client}/surveys'
 */
    const surveysForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: surveys.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\SurveyResultController::surveys
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:18
 * @route '/advisor/clients/{client}/surveys'
 */
        surveysForm.get = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: surveys.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\SurveyResultController::surveys
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:18
 * @route '/advisor/clients/{client}/surveys'
 */
        surveysForm.head = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: surveys.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    surveys.form = surveysForm
/**
* @see \App\Http\Controllers\Advisor\ClientEmailController::compose
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:22
 * @route '/advisor/clients/{client}/compose'
 */
export const compose = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: compose.url(args, options),
    method: 'get',
})

compose.definition = {
    methods: ["get","head"],
    url: '/advisor/clients/{client}/compose',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ClientEmailController::compose
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:22
 * @route '/advisor/clients/{client}/compose'
 */
compose.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { client: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { client: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                }

    return compose.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientEmailController::compose
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:22
 * @route '/advisor/clients/{client}/compose'
 */
compose.get = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: compose.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ClientEmailController::compose
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:22
 * @route '/advisor/clients/{client}/compose'
 */
compose.head = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: compose.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientEmailController::compose
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:22
 * @route '/advisor/clients/{client}/compose'
 */
    const composeForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: compose.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientEmailController::compose
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:22
 * @route '/advisor/clients/{client}/compose'
 */
        composeForm.get = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: compose.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ClientEmailController::compose
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:22
 * @route '/advisor/clients/{client}/compose'
 */
        composeForm.head = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: compose.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    compose.form = composeForm
/**
* @see \App\Http\Controllers\Advisor\ClientController::show
 * @see app/Http/Controllers/Advisor/ClientController.php:344
 * @route '/advisor/clients/{client}'
 */
export const show = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/advisor/clients/{client}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ClientController::show
 * @see app/Http/Controllers/Advisor/ClientController.php:344
 * @route '/advisor/clients/{client}'
 */
show.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { client: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { client: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                }

    return show.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientController::show
 * @see app/Http/Controllers/Advisor/ClientController.php:344
 * @route '/advisor/clients/{client}'
 */
show.get = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ClientController::show
 * @see app/Http/Controllers/Advisor/ClientController.php:344
 * @route '/advisor/clients/{client}'
 */
show.head = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientController::show
 * @see app/Http/Controllers/Advisor/ClientController.php:344
 * @route '/advisor/clients/{client}'
 */
    const showForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientController::show
 * @see app/Http/Controllers/Advisor/ClientController.php:344
 * @route '/advisor/clients/{client}'
 */
        showForm.get = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ClientController::show
 * @see app/Http/Controllers/Advisor/ClientController.php:344
 * @route '/advisor/clients/{client}'
 */
        showForm.head = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
const clients = {
    index: Object.assign(index, index),
integrationScopes: Object.assign(integrationScopes, integrationScopes),
integrationScoping: Object.assign(integrationScoping, integrationScoping),
create: Object.assign(create, create),
invite: Object.assign(invite, invite9ae5a1),
lookupNzbn: Object.assign(lookupNzbn, lookupNzbn),
store: Object.assign(store, store),
offboarding: Object.assign(offboarding, offboarding),
lifecycle: Object.assign(lifecycle, lifecycle),
knowledgeAssessments: Object.assign(knowledgeAssessments, knowledgeAssessments),
knowledgeDrafts: Object.assign(knowledgeDrafts, knowledgeDrafts),
goals: Object.assign(goals, goals),
proposals: Object.assign(proposals, proposals),
strategicBudget: Object.assign(strategicBudget, strategicBudget),
reports: Object.assign(reports, reports),
surveys: Object.assign(surveys, surveys),
surveyAssignments: Object.assign(surveyAssignments, surveyAssignments),
standardAdvisory: Object.assign(standardAdvisory, standardAdvisory),
healthRadar: Object.assign(healthRadar, healthRadar),
meetings: Object.assign(meetings, meetings),
compose: Object.assign(compose, compose),
email: Object.assign(email, email),
messages: Object.assign(messages, messages),
accounting: Object.assign(accounting, accounting),
show: Object.assign(show, show),
testimonials: Object.assign(testimonials, testimonials),
voiceNotes: Object.assign(voiceNotes, voiceNotes),
callLogs: Object.assign(callLogs, callLogs),
}

export default clients