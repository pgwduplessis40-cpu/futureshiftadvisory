import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
import latest from './latest'
import budgetPack from './budget-pack'
import assessments from './assessments'
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::preview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:460
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview'
*/
export const preview = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})

preview.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::preview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:460
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview'
*/
preview.url = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions) => {
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

    return preview.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{businessPlan}', parsedArgs.businessPlan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::preview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:460
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview'
*/
preview.get = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::preview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:460
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview'
*/
preview.head = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: preview.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::preview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:460
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview'
*/
const previewForm = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: preview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::preview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:460
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview'
*/
previewForm.get = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: preview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::preview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:460
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/preview'
*/
previewForm.head = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: preview.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

preview.form = previewForm
const plans = {
    latest: Object.assign(latest, latest),
    preview: Object.assign(preview, preview),
    budgetPack: Object.assign(budgetPack, budgetPack),
    assessments: Object.assign(assessments, assessments),
}

export default plans