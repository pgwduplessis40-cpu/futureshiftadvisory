import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:87
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
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:87
 * @route '/portal/entrepreneur/plan'
 */
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:87
 * @route '/portal/entrepreneur/plan'
 */
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:87
 * @route '/portal/entrepreneur/plan'
 */
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:87
 * @route '/portal/entrepreneur/plan'
 */
    const showForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:87
 * @route '/portal/entrepreneur/plan'
 */
        showForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::show
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:87
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
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:129
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
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:129
 * @route '/portal/entrepreneur/plan/preview'
 */
preview.url = (options?: RouteQueryOptions) => {
    return preview.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::preview
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:129
 * @route '/portal/entrepreneur/plan/preview'
 */
preview.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::preview
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:129
 * @route '/portal/entrepreneur/plan/preview'
 */
preview.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: preview.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::preview
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:129
 * @route '/portal/entrepreneur/plan/preview'
 */
    const previewForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: preview.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::preview
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:129
 * @route '/portal/entrepreneur/plan/preview'
 */
        previewForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: preview.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::preview
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:129
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
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::readiness
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:190
 * @route '/portal/entrepreneur/readiness'
 */
export const readiness = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: readiness.url(options),
    method: 'post',
})

readiness.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/readiness',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::readiness
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:190
 * @route '/portal/entrepreneur/readiness'
 */
readiness.url = (options?: RouteQueryOptions) => {
    return readiness.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::readiness
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:190
 * @route '/portal/entrepreneur/readiness'
 */
readiness.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: readiness.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::readiness
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:190
 * @route '/portal/entrepreneur/readiness'
 */
    const readinessForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: readiness.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::readiness
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:190
 * @route '/portal/entrepreneur/readiness'
 */
        readinessForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: readiness.url(options),
            method: 'post',
        })
    
    readiness.form = readinessForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::ideaValidation
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:208
 * @route '/portal/entrepreneur/idea-validation'
 */
export const ideaValidation = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ideaValidation.url(options),
    method: 'post',
})

ideaValidation.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/idea-validation',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::ideaValidation
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:208
 * @route '/portal/entrepreneur/idea-validation'
 */
ideaValidation.url = (options?: RouteQueryOptions) => {
    return ideaValidation.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::ideaValidation
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:208
 * @route '/portal/entrepreneur/idea-validation'
 */
ideaValidation.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ideaValidation.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::ideaValidation
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:208
 * @route '/portal/entrepreneur/idea-validation'
 */
    const ideaValidationForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: ideaValidation.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::ideaValidation
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:208
 * @route '/portal/entrepreneur/idea-validation'
 */
        ideaValidationForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: ideaValidation.url(options),
            method: 'post',
        })
    
    ideaValidation.form = ideaValidationForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::start
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:230
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
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:230
 * @route '/portal/entrepreneur/plan/start'
 */
start.url = (options?: RouteQueryOptions) => {
    return start.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::start
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:230
 * @route '/portal/entrepreneur/plan/start'
 */
start.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: start.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::start
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:230
 * @route '/portal/entrepreneur/plan/start'
 */
    const startForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: start.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::start
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:230
 * @route '/portal/entrepreneur/plan/start'
 */
        startForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: start.url(options),
            method: 'post',
        })
    
    start.form = startForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::assistRequirement
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:447
 * @route '/portal/entrepreneur/plan/requirements/assist'
 */
export const assistRequirement = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: assistRequirement.url(options),
    method: 'post',
})

assistRequirement.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/requirements/assist',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::assistRequirement
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:447
 * @route '/portal/entrepreneur/plan/requirements/assist'
 */
assistRequirement.url = (options?: RouteQueryOptions) => {
    return assistRequirement.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::assistRequirement
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:447
 * @route '/portal/entrepreneur/plan/requirements/assist'
 */
assistRequirement.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: assistRequirement.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::assistRequirement
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:447
 * @route '/portal/entrepreneur/plan/requirements/assist'
 */
    const assistRequirementForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: assistRequirement.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::assistRequirement
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:447
 * @route '/portal/entrepreneur/plan/requirements/assist'
 */
        assistRequirementForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: assistRequirement.url(options),
            method: 'post',
        })
    
    assistRequirement.form = assistRequirementForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::section
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:273
 * @route '/portal/entrepreneur/plan/sections'
 */
export const section = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: section.url(options),
    method: 'post',
})

section.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/sections',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::section
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:273
 * @route '/portal/entrepreneur/plan/sections'
 */
section.url = (options?: RouteQueryOptions) => {
    return section.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::section
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:273
 * @route '/portal/entrepreneur/plan/sections'
 */
section.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: section.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::section
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:273
 * @route '/portal/entrepreneur/plan/sections'
 */
    const sectionForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: section.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::section
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:273
 * @route '/portal/entrepreneur/plan/sections'
 */
        sectionForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: section.url(options),
            method: 'post',
        })
    
    section.form = sectionForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::guidance
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:477
 * @route '/portal/entrepreneur/plan/sections/{planSection}/guidance'
 */
export const guidance = (args: { planSection: string | { id: string } } | [planSection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: guidance.url(args, options),
    method: 'post',
})

guidance.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/sections/{planSection}/guidance',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::guidance
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:477
 * @route '/portal/entrepreneur/plan/sections/{planSection}/guidance'
 */
guidance.url = (args: { planSection: string | { id: string } } | [planSection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planSection: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { planSection: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    planSection: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        planSection: typeof args.planSection === 'object'
                ? args.planSection.id
                : args.planSection,
                }

    return guidance.definition.url
            .replace('{planSection}', parsedArgs.planSection.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::guidance
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:477
 * @route '/portal/entrepreneur/plan/sections/{planSection}/guidance'
 */
guidance.post = (args: { planSection: string | { id: string } } | [planSection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: guidance.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::guidance
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:477
 * @route '/portal/entrepreneur/plan/sections/{planSection}/guidance'
 */
    const guidanceForm = (args: { planSection: string | { id: string } } | [planSection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: guidance.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::guidance
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:477
 * @route '/portal/entrepreneur/plan/sections/{planSection}/guidance'
 */
        guidanceForm.post = (args: { planSection: string | { id: string } } | [planSection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: guidance.url(args, options),
            method: 'post',
        })
    
    guidance.form = guidanceForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budget
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:331
 * @route '/portal/entrepreneur/plan/budget'
 */
export const budget = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: budget.url(options),
    method: 'post',
})

budget.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/budget',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budget
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:331
 * @route '/portal/entrepreneur/plan/budget'
 */
budget.url = (options?: RouteQueryOptions) => {
    return budget.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budget
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:331
 * @route '/portal/entrepreneur/plan/budget'
 */
budget.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: budget.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budget
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:331
 * @route '/portal/entrepreneur/plan/budget'
 */
    const budgetForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: budget.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budget
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:331
 * @route '/portal/entrepreneur/plan/budget'
 */
        budgetForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: budget.url(options),
            method: 'post',
        })
    
    budget.form = budgetForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budgetPack
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:149
 * @route '/portal/entrepreneur/plan/budget-pack'
 */
export const budgetPack = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: budgetPack.url(options),
    method: 'get',
})

budgetPack.definition = {
    methods: ["get","head"],
    url: '/portal/entrepreneur/plan/budget-pack',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budgetPack
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:149
 * @route '/portal/entrepreneur/plan/budget-pack'
 */
budgetPack.url = (options?: RouteQueryOptions) => {
    return budgetPack.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budgetPack
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:149
 * @route '/portal/entrepreneur/plan/budget-pack'
 */
budgetPack.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: budgetPack.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budgetPack
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:149
 * @route '/portal/entrepreneur/plan/budget-pack'
 */
budgetPack.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: budgetPack.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budgetPack
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:149
 * @route '/portal/entrepreneur/plan/budget-pack'
 */
    const budgetPackForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: budgetPack.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budgetPack
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:149
 * @route '/portal/entrepreneur/plan/budget-pack'
 */
        budgetPackForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: budgetPack.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budgetPack
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:149
 * @route '/portal/entrepreneur/plan/budget-pack'
 */
        budgetPackForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: budgetPack.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    budgetPack.form = budgetPackForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budgetPackPdf
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:172
 * @route '/portal/entrepreneur/plan/budget-pack/pdf'
 */
export const budgetPackPdf = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: budgetPackPdf.url(options),
    method: 'get',
})

budgetPackPdf.definition = {
    methods: ["get","head"],
    url: '/portal/entrepreneur/plan/budget-pack/pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budgetPackPdf
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:172
 * @route '/portal/entrepreneur/plan/budget-pack/pdf'
 */
budgetPackPdf.url = (options?: RouteQueryOptions) => {
    return budgetPackPdf.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budgetPackPdf
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:172
 * @route '/portal/entrepreneur/plan/budget-pack/pdf'
 */
budgetPackPdf.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: budgetPackPdf.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budgetPackPdf
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:172
 * @route '/portal/entrepreneur/plan/budget-pack/pdf'
 */
budgetPackPdf.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: budgetPackPdf.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budgetPackPdf
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:172
 * @route '/portal/entrepreneur/plan/budget-pack/pdf'
 */
    const budgetPackPdfForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: budgetPackPdf.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budgetPackPdf
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:172
 * @route '/portal/entrepreneur/plan/budget-pack/pdf'
 */
        budgetPackPdfForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: budgetPackPdf.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::budgetPackPdf
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:172
 * @route '/portal/entrepreneur/plan/budget-pack/pdf'
 */
        budgetPackPdfForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: budgetPackPdf.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    budgetPackPdf.form = budgetPackPdfForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::acknowledgeBudgetFlag
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:406
 * @route '/portal/entrepreneur/plan/budget/flags/acknowledge'
 */
export const acknowledgeBudgetFlag = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: acknowledgeBudgetFlag.url(options),
    method: 'post',
})

acknowledgeBudgetFlag.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/budget/flags/acknowledge',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::acknowledgeBudgetFlag
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:406
 * @route '/portal/entrepreneur/plan/budget/flags/acknowledge'
 */
acknowledgeBudgetFlag.url = (options?: RouteQueryOptions) => {
    return acknowledgeBudgetFlag.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::acknowledgeBudgetFlag
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:406
 * @route '/portal/entrepreneur/plan/budget/flags/acknowledge'
 */
acknowledgeBudgetFlag.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: acknowledgeBudgetFlag.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::acknowledgeBudgetFlag
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:406
 * @route '/portal/entrepreneur/plan/budget/flags/acknowledge'
 */
    const acknowledgeBudgetFlagForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: acknowledgeBudgetFlag.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::acknowledgeBudgetFlag
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:406
 * @route '/portal/entrepreneur/plan/budget/flags/acknowledge'
 */
        acknowledgeBudgetFlagForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: acknowledgeBudgetFlag.url(options),
            method: 'post',
        })
    
    acknowledgeBudgetFlag.form = acknowledgeBudgetFlagForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::dismissBudgetAdvisorNudge
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:428
 * @route '/portal/entrepreneur/plan/budget/advisor-nudge/dismiss'
 */
export const dismissBudgetAdvisorNudge = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: dismissBudgetAdvisorNudge.url(options),
    method: 'post',
})

dismissBudgetAdvisorNudge.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/budget/advisor-nudge/dismiss',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::dismissBudgetAdvisorNudge
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:428
 * @route '/portal/entrepreneur/plan/budget/advisor-nudge/dismiss'
 */
dismissBudgetAdvisorNudge.url = (options?: RouteQueryOptions) => {
    return dismissBudgetAdvisorNudge.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::dismissBudgetAdvisorNudge
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:428
 * @route '/portal/entrepreneur/plan/budget/advisor-nudge/dismiss'
 */
dismissBudgetAdvisorNudge.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: dismissBudgetAdvisorNudge.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::dismissBudgetAdvisorNudge
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:428
 * @route '/portal/entrepreneur/plan/budget/advisor-nudge/dismiss'
 */
    const dismissBudgetAdvisorNudgeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: dismissBudgetAdvisorNudge.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::dismissBudgetAdvisorNudge
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:428
 * @route '/portal/entrepreneur/plan/budget/advisor-nudge/dismiss'
 */
        dismissBudgetAdvisorNudgeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: dismissBudgetAdvisorNudge.url(options),
            method: 'post',
        })
    
    dismissBudgetAdvisorNudge.form = dismissBudgetAdvisorNudgeForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::submit
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:489
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
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:489
 * @route '/portal/entrepreneur/plan/submit'
 */
submit.url = (options?: RouteQueryOptions) => {
    return submit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::submit
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:489
 * @route '/portal/entrepreneur/plan/submit'
 */
submit.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::submit
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:489
 * @route '/portal/entrepreneur/plan/submit'
 */
    const submitForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: submit.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::submit
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:489
 * @route '/portal/entrepreneur/plan/submit'
 */
        submitForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: submit.url(options),
            method: 'post',
        })
    
    submit.form = submitForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::requestAdvisory
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:523
 * @route '/portal/entrepreneur/advisory-request'
 */
export const requestAdvisory = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: requestAdvisory.url(options),
    method: 'post',
})

requestAdvisory.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/advisory-request',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::requestAdvisory
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:523
 * @route '/portal/entrepreneur/advisory-request'
 */
requestAdvisory.url = (options?: RouteQueryOptions) => {
    return requestAdvisory.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::requestAdvisory
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:523
 * @route '/portal/entrepreneur/advisory-request'
 */
requestAdvisory.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: requestAdvisory.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::requestAdvisory
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:523
 * @route '/portal/entrepreneur/advisory-request'
 */
    const requestAdvisoryForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: requestAdvisory.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::requestAdvisory
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:523
 * @route '/portal/entrepreneur/advisory-request'
 */
        requestAdvisoryForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: requestAdvisory.url(options),
            method: 'post',
        })
    
    requestAdvisory.form = requestAdvisoryForm
const EntrepreneurPlanController = { show, preview, readiness, ideaValidation, start, assistRequirement, section, guidance, budget, budgetPack, budgetPackPdf, acknowledgeBudgetFlag, dismissBudgetAdvisorNudge, submit, requestAdvisory }

export default EntrepreneurPlanController