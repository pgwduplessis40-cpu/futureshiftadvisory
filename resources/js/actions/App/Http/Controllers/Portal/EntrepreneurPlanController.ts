import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::readiness
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:50
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
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:50
* @route '/portal/entrepreneur/readiness'
*/
readiness.url = (options?: RouteQueryOptions) => {
    return readiness.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::readiness
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:50
* @route '/portal/entrepreneur/readiness'
*/
readiness.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: readiness.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::readiness
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:50
* @route '/portal/entrepreneur/readiness'
*/
const readinessForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: readiness.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::readiness
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:50
* @route '/portal/entrepreneur/readiness'
*/
readinessForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: readiness.url(options),
    method: 'post',
})

readiness.form = readinessForm

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::ideaValidation
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:68
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
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:68
* @route '/portal/entrepreneur/idea-validation'
*/
ideaValidation.url = (options?: RouteQueryOptions) => {
    return ideaValidation.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::ideaValidation
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:68
* @route '/portal/entrepreneur/idea-validation'
*/
ideaValidation.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ideaValidation.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::ideaValidation
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:68
* @route '/portal/entrepreneur/idea-validation'
*/
const ideaValidationForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: ideaValidation.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::ideaValidation
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:68
* @route '/portal/entrepreneur/idea-validation'
*/
ideaValidationForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: ideaValidation.url(options),
    method: 'post',
})

ideaValidation.form = ideaValidationForm

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::recallIdeaValidation
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:90
* @route '/portal/entrepreneur/idea-validation/recall'
*/
export const recallIdeaValidation = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: recallIdeaValidation.url(options),
    method: 'post',
})

recallIdeaValidation.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/idea-validation/recall',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::recallIdeaValidation
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:90
* @route '/portal/entrepreneur/idea-validation/recall'
*/
recallIdeaValidation.url = (options?: RouteQueryOptions) => {
    return recallIdeaValidation.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::recallIdeaValidation
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:90
* @route '/portal/entrepreneur/idea-validation/recall'
*/
recallIdeaValidation.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: recallIdeaValidation.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::recallIdeaValidation
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:90
* @route '/portal/entrepreneur/idea-validation/recall'
*/
const recallIdeaValidationForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: recallIdeaValidation.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::recallIdeaValidation
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:90
* @route '/portal/entrepreneur/idea-validation/recall'
*/
recallIdeaValidationForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: recallIdeaValidation.url(options),
    method: 'post',
})

recallIdeaValidation.form = recallIdeaValidationForm

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::restoreIdeaValidation
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:106
* @route '/portal/entrepreneur/idea-validation/{ideaValidation}/restore'
*/
export const restoreIdeaValidation = (args: { ideaValidation: string | { id: string } } | [ideaValidation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: restoreIdeaValidation.url(args, options),
    method: 'post',
})

restoreIdeaValidation.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/idea-validation/{ideaValidation}/restore',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::restoreIdeaValidation
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:106
* @route '/portal/entrepreneur/idea-validation/{ideaValidation}/restore'
*/
restoreIdeaValidation.url = (args: { ideaValidation: string | { id: string } } | [ideaValidation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ideaValidation: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { ideaValidation: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            ideaValidation: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ideaValidation: typeof args.ideaValidation === 'object'
        ? args.ideaValidation.id
        : args.ideaValidation,
    }

    return restoreIdeaValidation.definition.url
            .replace('{ideaValidation}', parsedArgs.ideaValidation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::restoreIdeaValidation
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:106
* @route '/portal/entrepreneur/idea-validation/{ideaValidation}/restore'
*/
restoreIdeaValidation.post = (args: { ideaValidation: string | { id: string } } | [ideaValidation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: restoreIdeaValidation.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::restoreIdeaValidation
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:106
* @route '/portal/entrepreneur/idea-validation/{ideaValidation}/restore'
*/
const restoreIdeaValidationForm = (args: { ideaValidation: string | { id: string } } | [ideaValidation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: restoreIdeaValidation.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::restoreIdeaValidation
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:106
* @route '/portal/entrepreneur/idea-validation/{ideaValidation}/restore'
*/
restoreIdeaValidationForm.post = (args: { ideaValidation: string | { id: string } } | [ideaValidation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: restoreIdeaValidation.url(args, options),
    method: 'post',
})

restoreIdeaValidation.form = restoreIdeaValidationForm

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::start
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:124
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
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:124
* @route '/portal/entrepreneur/plan/start'
*/
start.url = (options?: RouteQueryOptions) => {
    return start.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::start
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:124
* @route '/portal/entrepreneur/plan/start'
*/
start.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: start.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::start
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:124
* @route '/portal/entrepreneur/plan/start'
*/
const startForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: start.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::start
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:124
* @route '/portal/entrepreneur/plan/start'
*/
startForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: start.url(options),
    method: 'post',
})

start.form = startForm

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::updateCompanyName
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:167
* @route '/portal/entrepreneur/plan/company-name'
*/
export const updateCompanyName = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateCompanyName.url(options),
    method: 'post',
})

updateCompanyName.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/company-name',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::updateCompanyName
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:167
* @route '/portal/entrepreneur/plan/company-name'
*/
updateCompanyName.url = (options?: RouteQueryOptions) => {
    return updateCompanyName.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::updateCompanyName
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:167
* @route '/portal/entrepreneur/plan/company-name'
*/
updateCompanyName.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateCompanyName.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::updateCompanyName
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:167
* @route '/portal/entrepreneur/plan/company-name'
*/
const updateCompanyNameForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateCompanyName.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::updateCompanyName
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:167
* @route '/portal/entrepreneur/plan/company-name'
*/
updateCompanyNameForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateCompanyName.url(options),
    method: 'post',
})

updateCompanyName.form = updateCompanyNameForm

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::assistRequirement
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:276
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
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:276
* @route '/portal/entrepreneur/plan/requirements/assist'
*/
assistRequirement.url = (options?: RouteQueryOptions) => {
    return assistRequirement.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::assistRequirement
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:276
* @route '/portal/entrepreneur/plan/requirements/assist'
*/
assistRequirement.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: assistRequirement.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::assistRequirement
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:276
* @route '/portal/entrepreneur/plan/requirements/assist'
*/
const assistRequirementForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: assistRequirement.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::assistRequirement
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:276
* @route '/portal/entrepreneur/plan/requirements/assist'
*/
assistRequirementForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: assistRequirement.url(options),
    method: 'post',
})

assistRequirement.form = assistRequirementForm

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::generateExecutiveSummary
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:306
* @route '/portal/entrepreneur/plan/executive-summary'
*/
export const generateExecutiveSummary = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generateExecutiveSummary.url(options),
    method: 'post',
})

generateExecutiveSummary.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/executive-summary',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::generateExecutiveSummary
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:306
* @route '/portal/entrepreneur/plan/executive-summary'
*/
generateExecutiveSummary.url = (options?: RouteQueryOptions) => {
    return generateExecutiveSummary.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::generateExecutiveSummary
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:306
* @route '/portal/entrepreneur/plan/executive-summary'
*/
generateExecutiveSummary.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generateExecutiveSummary.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::generateExecutiveSummary
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:306
* @route '/portal/entrepreneur/plan/executive-summary'
*/
const generateExecutiveSummaryForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: generateExecutiveSummary.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::generateExecutiveSummary
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:306
* @route '/portal/entrepreneur/plan/executive-summary'
*/
generateExecutiveSummaryForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: generateExecutiveSummary.url(options),
    method: 'post',
})

generateExecutiveSummary.form = generateExecutiveSummaryForm

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::section
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:188
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
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:188
* @route '/portal/entrepreneur/plan/sections'
*/
section.url = (options?: RouteQueryOptions) => {
    return section.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::section
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:188
* @route '/portal/entrepreneur/plan/sections'
*/
section.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: section.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::section
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:188
* @route '/portal/entrepreneur/plan/sections'
*/
const sectionForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: section.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::section
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:188
* @route '/portal/entrepreneur/plan/sections'
*/
sectionForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: section.url(options),
    method: 'post',
})

section.form = sectionForm

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::guidance
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:327
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
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:327
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
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:327
* @route '/portal/entrepreneur/plan/sections/{planSection}/guidance'
*/
guidance.post = (args: { planSection: string | { id: string } } | [planSection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: guidance.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::guidance
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:327
* @route '/portal/entrepreneur/plan/sections/{planSection}/guidance'
*/
const guidanceForm = (args: { planSection: string | { id: string } } | [planSection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: guidance.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::guidance
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:327
* @route '/portal/entrepreneur/plan/sections/{planSection}/guidance'
*/
guidanceForm.post = (args: { planSection: string | { id: string } } | [planSection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: guidance.url(args, options),
    method: 'post',
})

guidance.form = guidanceForm

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::submit
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:339
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
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:339
* @route '/portal/entrepreneur/plan/submit'
*/
submit.url = (options?: RouteQueryOptions) => {
    return submit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::submit
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:339
* @route '/portal/entrepreneur/plan/submit'
*/
submit.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::submit
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:339
* @route '/portal/entrepreneur/plan/submit'
*/
const submitForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: submit.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::submit
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:339
* @route '/portal/entrepreneur/plan/submit'
*/
submitForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: submit.url(options),
    method: 'post',
})

submit.form = submitForm

const EntrepreneurPlanController = { readiness, ideaValidation, recallIdeaValidation, restoreIdeaValidation, start, updateCompanyName, assistRequirement, generateExecutiveSummary, section, guidance, submit }

export default EntrepreneurPlanController
