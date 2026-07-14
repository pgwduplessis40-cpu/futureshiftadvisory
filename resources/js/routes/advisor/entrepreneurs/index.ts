import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import messages from './messages'
import ideaValidations from './idea-validations'
import plans from './plans'
import assessments from './assessments'
import gamification from './gamification'
import surveyAssignments from './survey-assignments'
import documents from './documents'
import invite from './invite'
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:54
 * @route '/advisor/entrepreneurs'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:54
 * @route '/advisor/entrepreneurs'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:54
 * @route '/advisor/entrepreneurs'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:54
 * @route '/advisor/entrepreneurs'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:54
 * @route '/advisor/entrepreneurs'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:54
 * @route '/advisor/entrepreneurs'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:54
 * @route '/advisor/entrepreneurs'
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:71
 * @route '/advisor/entrepreneurs/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:71
 * @route '/advisor/entrepreneurs/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:71
 * @route '/advisor/entrepreneurs/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:71
 * @route '/advisor/entrepreneurs/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:71
 * @route '/advisor/entrepreneurs/create'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:71
 * @route '/advisor/entrepreneurs/create'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:71
 * @route '/advisor/entrepreneurs/create'
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::createManual
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:82
 * @route '/advisor/entrepreneurs/create/manual'
 */
export const createManual = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: createManual.url(options),
    method: 'get',
})

createManual.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/create/manual',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::createManual
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:82
 * @route '/advisor/entrepreneurs/create/manual'
 */
createManual.url = (options?: RouteQueryOptions) => {
    return createManual.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::createManual
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:82
 * @route '/advisor/entrepreneurs/create/manual'
 */
createManual.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: createManual.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::createManual
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:82
 * @route '/advisor/entrepreneurs/create/manual'
 */
createManual.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: createManual.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::createManual
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:82
 * @route '/advisor/entrepreneurs/create/manual'
 */
    const createManualForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: createManual.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::createManual
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:82
 * @route '/advisor/entrepreneurs/create/manual'
 */
        createManualForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: createManual.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::createManual
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:82
 * @route '/advisor/entrepreneurs/create/manual'
 */
        createManualForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: createManual.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    createManual.form = createManualForm
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:93
 * @route '/advisor/entrepreneurs'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:93
 * @route '/advisor/entrepreneurs'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:93
 * @route '/advisor/entrepreneurs'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:93
 * @route '/advisor/entrepreneurs'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:93
 * @route '/advisor/entrepreneurs'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::storeManual
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:161
 * @route '/advisor/entrepreneurs/manual'
 */
export const storeManual = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeManual.url(options),
    method: 'post',
})

storeManual.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs/manual',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::storeManual
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:161
 * @route '/advisor/entrepreneurs/manual'
 */
storeManual.url = (options?: RouteQueryOptions) => {
    return storeManual.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::storeManual
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:161
 * @route '/advisor/entrepreneurs/manual'
 */
storeManual.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeManual.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::storeManual
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:161
 * @route '/advisor/entrepreneurs/manual'
 */
    const storeManualForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: storeManual.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::storeManual
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:161
 * @route '/advisor/entrepreneurs/manual'
 */
        storeManualForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: storeManual.url(options),
            method: 'post',
        })

    storeManual.form = storeManualForm
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::convert
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:127
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/convert'
 */
export const convert = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: convert.url(args, options),
    method: 'post',
})

convert.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/convert',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::convert
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:127
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/convert'
 */
convert.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { entrepreneurProfile: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { entrepreneurProfile: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                }

    return convert.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::convert
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:127
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/convert'
 */
convert.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: convert.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::convert
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:127
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/convert'
 */
    const convertForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: convert.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::convert
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:127
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/convert'
 */
        convertForm.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: convert.url(args, options),
            method: 'post',
        })

    convert.form = convertForm
/**
* @see \App\Http\Controllers\Advisor\SurveyResultController::surveys
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:39
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/surveys'
 */
export const surveys = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: surveys.url(args, options),
    method: 'get',
})

surveys.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/surveys',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\SurveyResultController::surveys
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:39
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/surveys'
 */
surveys.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { entrepreneurProfile: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { entrepreneurProfile: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                }

    return surveys.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\SurveyResultController::surveys
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:39
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/surveys'
 */
surveys.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: surveys.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\SurveyResultController::surveys
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:39
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/surveys'
 */
surveys.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: surveys.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\SurveyResultController::surveys
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:39
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/surveys'
 */
    const surveysForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: surveys.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\SurveyResultController::surveys
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:39
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/surveys'
 */
        surveysForm.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: surveys.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\SurveyResultController::surveys
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:39
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/surveys'
 */
        surveysForm.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:366
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}'
 */
export const show = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:366
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}'
 */
show.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { entrepreneurProfile: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { entrepreneurProfile: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                }

    return show.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:366
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}'
 */
show.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:366
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}'
 */
show.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:366
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}'
 */
    const showForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:366
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}'
 */
        showForm.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:366
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}'
 */
        showForm.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    show.form = showForm
const entrepreneurs = {
    index: Object.assign(index, index),
create: Object.assign(create, create),
createManual: Object.assign(createManual, createManual),
store: Object.assign(store, store),
storeManual: Object.assign(storeManual, storeManual),
messages: Object.assign(messages, messages),
ideaValidations: Object.assign(ideaValidations, ideaValidations),
plans: Object.assign(plans, plans),
assessments: Object.assign(assessments, assessments),
convert: Object.assign(convert, convert),
gamification: Object.assign(gamification, gamification),
surveys: Object.assign(surveys, surveys),
surveyAssignments: Object.assign(surveyAssignments, surveyAssignments),
documents: Object.assign(documents, documents),
invite: Object.assign(invite, invite),
show: Object.assign(show, show),
}

export default entrepreneurs