import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::latestPlanPreview
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:28
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview'
*/
export const latestPlanPreview = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: latestPlanPreview.url(args, options),
    method: 'get',
})

latestPlanPreview.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::latestPlanPreview
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:28
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview'
*/
latestPlanPreview.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return latestPlanPreview.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::latestPlanPreview
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:28
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview'
*/
latestPlanPreview.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: latestPlanPreview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::latestPlanPreview
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:28
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview'
*/
latestPlanPreview.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: latestPlanPreview.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::latestPlanPreview
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:28
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview'
*/
const latestPlanPreviewForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: latestPlanPreview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::latestPlanPreview
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:28
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview'
*/
latestPlanPreviewForm.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: latestPlanPreview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::latestPlanPreview
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:28
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview'
*/
latestPlanPreviewForm.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: latestPlanPreview.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

latestPlanPreview.form = latestPlanPreviewForm

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::latestBudgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:46
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/budget-pack/pdf'
*/
export const latestBudgetPackPdf = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: latestBudgetPackPdf.url(args, options),
    method: 'get',
})

latestBudgetPackPdf.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/plans/budget-pack/pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::latestBudgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:46
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/budget-pack/pdf'
*/
latestBudgetPackPdf.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return latestBudgetPackPdf.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::latestBudgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:46
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/budget-pack/pdf'
*/
latestBudgetPackPdf.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: latestBudgetPackPdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::latestBudgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:46
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/budget-pack/pdf'
*/
latestBudgetPackPdf.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: latestBudgetPackPdf.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::latestBudgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:46
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/budget-pack/pdf'
*/
const latestBudgetPackPdfForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: latestBudgetPackPdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::latestBudgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:46
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/budget-pack/pdf'
*/
latestBudgetPackPdfForm.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: latestBudgetPackPdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::latestBudgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:46
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/budget-pack/pdf'
*/
latestBudgetPackPdfForm.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: latestBudgetPackPdf.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

latestBudgetPackPdf.form = latestBudgetPackPdfForm

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::planPreview
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:38
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview'
*/
export const planPreview = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: planPreview.url(args, options),
    method: 'get',
})

planPreview.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::planPreview
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:38
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview'
*/
planPreview.url = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions) => {
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

    return planPreview.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{businessPlan}', parsedArgs.businessPlan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::planPreview
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:38
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview'
*/
planPreview.get = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: planPreview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::planPreview
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:38
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview'
*/
planPreview.head = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: planPreview.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::planPreview
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:38
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview'
*/
const planPreviewForm = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: planPreview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::planPreview
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:38
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview'
*/
planPreviewForm.get = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: planPreview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::planPreview
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:38
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview'
*/
planPreviewForm.head = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: planPreview.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

planPreview.form = planPreviewForm

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::budgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:57
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf'
*/
export const budgetPackPdf = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: budgetPackPdf.url(args, options),
    method: 'get',
})

budgetPackPdf.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::budgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:57
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf'
*/
budgetPackPdf.url = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions) => {
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

    return budgetPackPdf.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{businessPlan}', parsedArgs.businessPlan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::budgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:57
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf'
*/
budgetPackPdf.get = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: budgetPackPdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::budgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:57
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf'
*/
budgetPackPdf.head = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: budgetPackPdf.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::budgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:57
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf'
*/
const budgetPackPdfForm = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: budgetPackPdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::budgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:57
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf'
*/
budgetPackPdfForm.get = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: budgetPackPdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::budgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:57
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf'
*/
budgetPackPdfForm.head = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: budgetPackPdf.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

budgetPackPdf.form = budgetPackPdfForm

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::funderReadyPlanPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:66
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/funder-ready/pdf'
*/
export const funderReadyPlanPdf = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: funderReadyPlanPdf.url(args, options),
    method: 'get',
})

funderReadyPlanPdf.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/funder-ready/pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::funderReadyPlanPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:66
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/funder-ready/pdf'
*/
funderReadyPlanPdf.url = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions) => {
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

    return funderReadyPlanPdf.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{businessPlan}', parsedArgs.businessPlan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::funderReadyPlanPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:66
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/funder-ready/pdf'
*/
funderReadyPlanPdf.get = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: funderReadyPlanPdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::funderReadyPlanPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:66
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/funder-ready/pdf'
*/
funderReadyPlanPdf.head = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: funderReadyPlanPdf.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::funderReadyPlanPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:66
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/funder-ready/pdf'
*/
const funderReadyPlanPdfForm = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: funderReadyPlanPdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::funderReadyPlanPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:66
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/funder-ready/pdf'
*/
funderReadyPlanPdfForm.get = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: funderReadyPlanPdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurPlanDocumentController::funderReadyPlanPdf
* @see app/Http/Controllers/Advisor/EntrepreneurPlanDocumentController.php:66
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/funder-ready/pdf'
*/
funderReadyPlanPdfForm.head = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: funderReadyPlanPdf.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

funderReadyPlanPdf.form = funderReadyPlanPdfForm

const EntrepreneurPlanDocumentController = { latestPlanPreview, latestBudgetPackPdf, planPreview, budgetPackPdf, funderReadyPlanPdf }

export default EntrepreneurPlanDocumentController
