import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::pdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:482
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf'
*/
export const pdf = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pdf.url(args, options),
    method: 'get',
})

pdf.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::pdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:482
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf'
*/
pdf.url = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions) => {
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

    return pdf.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{businessPlan}', parsedArgs.businessPlan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::pdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:482
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf'
*/
pdf.get = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::pdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:482
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf'
*/
pdf.head = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pdf.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::pdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:482
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf'
*/
const pdfForm = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: pdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::pdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:482
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf'
*/
pdfForm.get = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: pdf.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::pdf
* @see app/Http/Controllers/Advisor/EntrepreneurController.php:482
* @route '/advisor/entrepreneurs/{entrepreneurProfile}/plans/{businessPlan}/budget-pack/pdf'
*/
pdfForm.head = (args: { entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } } | [entrepreneurProfile: string | { id: string }, businessPlan: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: pdf.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

pdf.form = pdfForm

const budgetPack = {
    pdf: Object.assign(pdf, pdf),
}

export default budgetPack
