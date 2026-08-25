import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:82
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
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:82
* @route '/advisor/entrepreneurs'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:82
* @route '/advisor/entrepreneurs'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:82
* @route '/advisor/entrepreneurs'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:82
* @route '/advisor/entrepreneurs'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:82
* @route '/advisor/entrepreneurs'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:82
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
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:99
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
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:99
* @route '/advisor/entrepreneurs/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:99
* @route '/advisor/entrepreneurs/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:99
* @route '/advisor/entrepreneurs/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:99
* @route '/advisor/entrepreneurs/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:99
* @route '/advisor/entrepreneurs/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:99
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
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:110
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
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:110
* @route '/advisor/entrepreneurs/create/manual'
*/
createManual.url = (options?: RouteQueryOptions) => {
    return createManual.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::createManual
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:110
* @route '/advisor/entrepreneurs/create/manual'
*/
createManual.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: createManual.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::createManual
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:110
* @route '/advisor/entrepreneurs/create/manual'
*/
createManual.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: createManual.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::createManual
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:110
* @route '/advisor/entrepreneurs/create/manual'
*/
const createManualForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: createManual.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::createManual
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:110
* @route '/advisor/entrepreneurs/create/manual'
*/
createManualForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: createManual.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::createManual
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:110
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
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:121
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
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:121
* @route '/advisor/entrepreneurs'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::store
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:121
* @route '/advisor/entrepreneurs'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::store
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:121
* @route '/advisor/entrepreneurs'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::store
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:121
* @route '/advisor/entrepreneurs'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::storeManual
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:189
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
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:189
* @route '/advisor/entrepreneurs/manual'
*/
storeManual.url = (options?: RouteQueryOptions) => {
    return storeManual.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::storeManual
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:189
* @route '/advisor/entrepreneurs/manual'
*/
storeManual.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeManual.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::storeManual
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:189
* @route '/advisor/entrepreneurs/manual'
*/
const storeManualForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: storeManual.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::storeManual
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:189
* @route '/advisor/entrepreneurs/manual'
*/
storeManualForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: storeManual.url(options),
    method: 'post',
})

storeManual.form = storeManualForm

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::latestPlanPreview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:462
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::latestPlanPreview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:462
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::latestPlanPreview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:462
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview'
*/
latestPlanPreview.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: latestPlanPreview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::latestPlanPreview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:462
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview'
*/
latestPlanPreview.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: latestPlanPreview.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::latestPlanPreview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:462
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview'
*/
const latestPlanPreviewForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: latestPlanPreview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::latestPlanPreview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:462
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview'
*/
latestPlanPreviewForm.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: latestPlanPreview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::latestPlanPreview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:462
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::latestBudgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:480
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::latestBudgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:480
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::latestBudgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:480
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/budget-pack/pdf'
*/
latestBudgetPackPdf.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: latestBudgetPackPdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::latestBudgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:480
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/budget-pack/pdf'
*/
latestBudgetPackPdf.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: latestBudgetPackPdf.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::latestBudgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:480
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/budget-pack/pdf'
*/
const latestBudgetPackPdfForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: latestBudgetPackPdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::latestBudgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:480
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/budget-pack/pdf'
*/
latestBudgetPackPdfForm.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: latestBudgetPackPdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::latestBudgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:480
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::planPreview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:472
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::planPreview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:472
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::planPreview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:472
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview'
*/
planPreview.get = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: planPreview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::planPreview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:472
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview'
*/
planPreview.head = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: planPreview.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::planPreview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:472
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview'
*/
const planPreviewForm = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: planPreview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::planPreview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:472
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview'
*/
planPreviewForm.get = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: planPreview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::planPreview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:472
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::budgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:491
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::budgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:491
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::budgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:491
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf'
*/
budgetPackPdf.get = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: budgetPackPdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::budgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:491
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf'
*/
budgetPackPdf.head = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: budgetPackPdf.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::budgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:491
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf'
*/
const budgetPackPdfForm = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: budgetPackPdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::budgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:491
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf'
*/
budgetPackPdfForm.get = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: budgetPackPdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::budgetPackPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:491
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::funderReadyPlanPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:500
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::funderReadyPlanPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:500
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::funderReadyPlanPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:500
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/funder-ready/pdf'
*/
funderReadyPlanPdf.get = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: funderReadyPlanPdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::funderReadyPlanPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:500
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/funder-ready/pdf'
*/
funderReadyPlanPdf.head = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: funderReadyPlanPdf.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::funderReadyPlanPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:500
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/funder-ready/pdf'
*/
const funderReadyPlanPdfForm = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: funderReadyPlanPdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::funderReadyPlanPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:500
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/funder-ready/pdf'
*/
funderReadyPlanPdfForm.get = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: funderReadyPlanPdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::funderReadyPlanPdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:500
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

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::updateInvite
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:277
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
*/
export const updateInvite = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateInvite.url(args, options),
    method: 'patch',
})

updateInvite.definition = {
    methods: ["patch"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/invite',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::updateInvite
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:277
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
*/
updateInvite.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return updateInvite.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::updateInvite
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:277
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
*/
updateInvite.patch = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateInvite.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::updateInvite
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:277
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
*/
const updateInviteForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateInvite.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::updateInvite
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:277
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
*/
updateInviteForm.patch = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateInvite.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

updateInvite.form = updateInviteForm

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::resendInvite
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:228
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite/resend'
*/
export const resendInvite = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resendInvite.url(args, options),
    method: 'post',
})

resendInvite.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/invite/resend',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::resendInvite
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:228
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite/resend'
*/
resendInvite.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return resendInvite.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::resendInvite
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:228
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite/resend'
*/
resendInvite.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resendInvite.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::resendInvite
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:228
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite/resend'
*/
const resendInviteForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: resendInvite.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::resendInvite
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:228
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite/resend'
*/
resendInviteForm.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: resendInvite.url(args, options),
    method: 'post',
})

resendInvite.form = resendInviteForm

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::cancelInvite
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:338
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
*/
export const cancelInvite = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: cancelInvite.url(args, options),
    method: 'delete',
})

cancelInvite.definition = {
    methods: ["delete"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/invite',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::cancelInvite
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:338
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
*/
cancelInvite.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return cancelInvite.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::cancelInvite
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:338
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
*/
cancelInvite.delete = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: cancelInvite.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::cancelInvite
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:338
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
*/
const cancelInviteForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: cancelInvite.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::cancelInvite
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:338
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
*/
cancelInviteForm.delete = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:373
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
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:373
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
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:373
* @route '/advisor/entrepreneurs/{entrepreneurProfile}'
*/
show.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:373
* @route '/advisor/entrepreneurs/{entrepreneurProfile}'
*/
show.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:373
* @route '/advisor/entrepreneurs/{entrepreneurProfile}'
*/
const showForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:373
* @route '/advisor/entrepreneurs/{entrepreneurProfile}'
*/
showForm.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:373
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

const EntrepreneurController = { index, create, createManual, store, storeManual, latestPlanPreview, latestBudgetPackPdf, planPreview, budgetPackPdf, funderReadyPlanPdf, updateInvite, resendInvite, cancelInvite, show }

export default EntrepreneurController
