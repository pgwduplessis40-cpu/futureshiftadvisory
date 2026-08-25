import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
import budgetPack from './budget-pack'
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::preview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:462
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview'
*/
export const preview = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})

preview.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::preview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:462
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview'
*/
preview.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return preview.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::preview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:462
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview'
*/
preview.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::preview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:462
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview'
*/
preview.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: preview.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::preview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:462
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview'
*/
const previewForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: preview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::preview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:462
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview'
*/
previewForm.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: preview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::preview
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:462
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/preview'
*/
previewForm.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: preview.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

preview.form = previewForm

const latest = {
    preview: Object.assign(preview, preview),
    budgetPack: Object.assign(budgetPack, budgetPack),
}

export default latest
