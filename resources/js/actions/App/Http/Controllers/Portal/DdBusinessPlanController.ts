import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::show
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:66
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
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:66
* @route '/portal/acquisition-plan'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::show
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:66
* @route '/portal/acquisition-plan'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::show
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:66
* @route '/portal/acquisition-plan'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::show
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:66
* @route '/portal/acquisition-plan'
*/
const showForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::show
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:66
* @route '/portal/acquisition-plan'
*/
showForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::show
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:66
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
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:129
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
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:129
* @route '/portal/acquisition-plan/preview'
*/
preview.url = (options?: RouteQueryOptions) => {
    return preview.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::preview
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:129
* @route '/portal/acquisition-plan/preview'
*/
preview.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::preview
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:129
* @route '/portal/acquisition-plan/preview'
*/
preview.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: preview.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::preview
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:129
* @route '/portal/acquisition-plan/preview'
*/
const previewForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: preview.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::preview
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:129
* @route '/portal/acquisition-plan/preview'
*/
previewForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: preview.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::preview
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:129
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
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:152
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
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:152
* @route '/portal/acquisition-plan'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:152
* @route '/portal/acquisition-plan'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:152
* @route '/portal/acquisition-plan'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:152
* @route '/portal/acquisition-plan'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm
/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::supportLevel
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:103
* @route '/portal/acquisition-plan/support-level'
*/
export const supportLevel = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: supportLevel.url(options),
    method: 'post',
})

supportLevel.definition = {
    methods: ["post"],
    url: '/portal/acquisition-plan/support-level',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::supportLevel
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:103
* @route '/portal/acquisition-plan/support-level'
*/
supportLevel.url = (options?: RouteQueryOptions) => {
    return supportLevel.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::supportLevel
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:103
* @route '/portal/acquisition-plan/support-level'
*/
supportLevel.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: supportLevel.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::supportLevel
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:103
* @route '/portal/acquisition-plan/support-level'
*/
const supportLevelForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: supportLevel.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::supportLevel
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:103
* @route '/portal/acquisition-plan/support-level'
*/
supportLevelForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: supportLevel.url(options),
    method: 'post',
})

supportLevel.form = supportLevelForm
/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::questionnaire
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:165
* @route '/portal/acquisition-plan/questionnaire'
*/
export const questionnaire = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: questionnaire.url(options),
    method: 'post',
})

questionnaire.definition = {
    methods: ["post"],
    url: '/portal/acquisition-plan/questionnaire',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::questionnaire
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:165
* @route '/portal/acquisition-plan/questionnaire'
*/
questionnaire.url = (options?: RouteQueryOptions) => {
    return questionnaire.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::questionnaire
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:165
* @route '/portal/acquisition-plan/questionnaire'
*/
questionnaire.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: questionnaire.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::questionnaire
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:165
* @route '/portal/acquisition-plan/questionnaire'
*/
const questionnaireForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: questionnaire.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::questionnaire
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:165
* @route '/portal/acquisition-plan/questionnaire'
*/
questionnaireForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: questionnaire.url(options),
    method: 'post',
})

questionnaire.form = questionnaireForm
/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::section
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:177
* @route '/portal/acquisition-plan/sections'
*/
export const section = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: section.url(options),
    method: 'post',
})

section.definition = {
    methods: ["post"],
    url: '/portal/acquisition-plan/sections',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::section
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:177
* @route '/portal/acquisition-plan/sections'
*/
section.url = (options?: RouteQueryOptions) => {
    return section.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::section
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:177
* @route '/portal/acquisition-plan/sections'
*/
section.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: section.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::section
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:177
* @route '/portal/acquisition-plan/sections'
*/
const sectionForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: section.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::section
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:177
* @route '/portal/acquisition-plan/sections'
*/
sectionForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: section.url(options),
    method: 'post',
})

section.form = sectionForm
/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::guidance
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:222
* @route '/portal/acquisition-plan/sections/{planSection}/guidance'
*/
export const guidance = (args: { planSection: string | { id: string } } | [planSection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: guidance.url(args, options),
    method: 'post',
})

guidance.definition = {
    methods: ["post"],
    url: '/portal/acquisition-plan/sections/{planSection}/guidance',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::guidance
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:222
* @route '/portal/acquisition-plan/sections/{planSection}/guidance'
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
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::guidance
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:222
* @route '/portal/acquisition-plan/sections/{planSection}/guidance'
*/
guidance.post = (args: { planSection: string | { id: string } } | [planSection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: guidance.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::guidance
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:222
* @route '/portal/acquisition-plan/sections/{planSection}/guidance'
*/
const guidanceForm = (args: { planSection: string | { id: string } } | [planSection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: guidance.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::guidance
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:222
* @route '/portal/acquisition-plan/sections/{planSection}/guidance'
*/
guidanceForm.post = (args: { planSection: string | { id: string } } | [planSection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: guidance.url(args, options),
    method: 'post',
})

guidance.form = guidanceForm
/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::complete
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:235
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
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:235
* @route '/portal/acquisition-plan/complete'
*/
complete.url = (options?: RouteQueryOptions) => {
    return complete.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::complete
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:235
* @route '/portal/acquisition-plan/complete'
*/
complete.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::complete
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:235
* @route '/portal/acquisition-plan/complete'
*/
const completeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: complete.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::complete
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:235
* @route '/portal/acquisition-plan/complete'
*/
completeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: complete.url(options),
    method: 'post',
})

complete.form = completeForm
/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::requestAdvice
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:262
* @route '/portal/acquisition-plan/business-advice'
*/
export const requestAdvice = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: requestAdvice.url(options),
    method: 'post',
})

requestAdvice.definition = {
    methods: ["post"],
    url: '/portal/acquisition-plan/business-advice',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::requestAdvice
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:262
* @route '/portal/acquisition-plan/business-advice'
*/
requestAdvice.url = (options?: RouteQueryOptions) => {
    return requestAdvice.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::requestAdvice
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:262
* @route '/portal/acquisition-plan/business-advice'
*/
requestAdvice.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: requestAdvice.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::requestAdvice
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:262
* @route '/portal/acquisition-plan/business-advice'
*/
const requestAdviceForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: requestAdvice.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::requestAdvice
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:262
* @route '/portal/acquisition-plan/business-advice'
*/
requestAdviceForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: requestAdvice.url(options),
    method: 'post',
})

requestAdvice.form = requestAdviceForm
const DdBusinessPlanController = { show, preview, store, supportLevel, questionnaire, section, guidance, complete, requestAdvice }

export default DdBusinessPlanController