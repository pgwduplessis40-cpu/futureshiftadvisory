import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::gateIdea
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:31
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate'
 */
export const gateIdea = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: gateIdea.url(args, options),
    method: 'patch',
})

gateIdea.definition = {
    methods: ["patch"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::gateIdea
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:31
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate'
 */
gateIdea.url = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                    ideaValidation: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                                ideaValidation: typeof args.ideaValidation === 'object'
                ? args.ideaValidation.id
                : args.ideaValidation,
                }

    return gateIdea.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{ideaValidation}', parsedArgs.ideaValidation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::gateIdea
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:31
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate'
 */
gateIdea.patch = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: gateIdea.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::gateIdea
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:31
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate'
 */
    const gateIdeaForm = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: gateIdea.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::gateIdea
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:31
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate'
 */
        gateIdeaForm.patch = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: gateIdea.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    gateIdea.form = gateIdeaForm
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::requestIdeaChanges
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:65
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/request-changes'
 */
export const requestIdeaChanges = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: requestIdeaChanges.url(args, options),
    method: 'patch',
})

requestIdeaChanges.definition = {
    methods: ["patch"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/request-changes',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::requestIdeaChanges
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:65
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/request-changes'
 */
requestIdeaChanges.url = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                    ideaValidation: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                                ideaValidation: typeof args.ideaValidation === 'object'
                ? args.ideaValidation.id
                : args.ideaValidation,
                }

    return requestIdeaChanges.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{ideaValidation}', parsedArgs.ideaValidation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::requestIdeaChanges
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:65
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/request-changes'
 */
requestIdeaChanges.patch = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: requestIdeaChanges.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::requestIdeaChanges
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:65
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/request-changes'
 */
    const requestIdeaChangesForm = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: requestIdeaChanges.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::requestIdeaChanges
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:65
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/request-changes'
 */
        requestIdeaChangesForm.patch = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: requestIdeaChanges.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    requestIdeaChanges.form = requestIdeaChangesForm
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::refreshIdea
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:49
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/refresh'
 */
export const refreshIdea = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: refreshIdea.url(args, options),
    method: 'post',
})

refreshIdea.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/refresh',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::refreshIdea
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:49
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/refresh'
 */
refreshIdea.url = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                    ideaValidation: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                                ideaValidation: typeof args.ideaValidation === 'object'
                ? args.ideaValidation.id
                : args.ideaValidation,
                }

    return refreshIdea.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{ideaValidation}', parsedArgs.ideaValidation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::refreshIdea
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:49
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/refresh'
 */
refreshIdea.post = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: refreshIdea.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::refreshIdea
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:49
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/refresh'
 */
    const refreshIdeaForm = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: refreshIdea.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::refreshIdea
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:49
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/refresh'
 */
        refreshIdeaForm.post = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: refreshIdea.url(args, options),
            method: 'post',
        })
    
    refreshIdea.form = refreshIdeaForm
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::assess
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:83
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/assessments'
 */
export const assess = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: assess.url(args, options),
    method: 'post',
})

assess.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/assessments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::assess
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:83
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/assessments'
 */
assess.url = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                    businessPlan: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                                businessPlan: typeof args.businessPlan === 'object'
                ? args.businessPlan.id
                : args.businessPlan,
                }

    return assess.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{businessPlan}', parsedArgs.businessPlan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::assess
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:83
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/assessments'
 */
assess.post = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: assess.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::assess
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:83
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/assessments'
 */
    const assessForm = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: assess.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::assess
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:83
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/assessments'
 */
        assessForm.post = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: assess.url(args, options),
            method: 'post',
        })
    
    assess.form = assessForm
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::finalise
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:100
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/finalise'
 */
export const finalise = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: finalise.url(args, options),
    method: 'patch',
})

finalise.definition = {
    methods: ["patch"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/finalise',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::finalise
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:100
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/finalise'
 */
finalise.url = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                    planAssessment: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                                planAssessment: typeof args.planAssessment === 'object'
                ? args.planAssessment.id
                : args.planAssessment,
                }

    return finalise.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{planAssessment}', parsedArgs.planAssessment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::finalise
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:100
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/finalise'
 */
finalise.patch = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: finalise.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::finalise
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:100
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/finalise'
 */
    const finaliseForm = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: finalise.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::finalise
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:100
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/finalise'
 */
        finaliseForm.patch = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: finalise.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    finalise.form = finaliseForm
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
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::setGamification
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:155
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/gamification'
 */
export const setGamification = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: setGamification.url(args, options),
    method: 'patch',
})

setGamification.definition = {
    methods: ["patch"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/gamification',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::setGamification
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:155
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/gamification'
 */
setGamification.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return setGamification.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::setGamification
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:155
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/gamification'
 */
setGamification.patch = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: setGamification.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::setGamification
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:155
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/gamification'
 */
    const setGamificationForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: setGamification.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::setGamification
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:155
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/gamification'
 */
        setGamificationForm.patch = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: setGamification.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    setGamification.form = setGamificationForm
const EntrepreneurActionController = { gateIdea, requestIdeaChanges, refreshIdea, assess, finalise, convert, setGamification }

export default EntrepreneurActionController
