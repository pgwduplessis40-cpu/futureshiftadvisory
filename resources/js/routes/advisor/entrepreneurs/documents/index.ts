import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurDocumentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurDocumentController.php:18
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/documents/{document}'
 */
export const show = (args: { entrepreneurProfile: string | { id: string }, document: string | { id: string } } | [entrepreneurProfile: string | { id: string }, document: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/documents/{document}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurDocumentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurDocumentController.php:18
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/documents/{document}'
 */
show.url = (args: { entrepreneurProfile: string | { id: string }, document: string | { id: string } } | [entrepreneurProfile: string | { id: string }, document: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                    document: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                                document: typeof args.document === 'object'
                ? args.document.id
                : args.document,
                }

    return show.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{document}', parsedArgs.document.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurDocumentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurDocumentController.php:18
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/documents/{document}'
 */
show.get = (args: { entrepreneurProfile: string | { id: string }, document: string | { id: string } } | [entrepreneurProfile: string | { id: string }, document: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurDocumentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurDocumentController.php:18
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/documents/{document}'
 */
show.head = (args: { entrepreneurProfile: string | { id: string }, document: string | { id: string } } | [entrepreneurProfile: string | { id: string }, document: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurDocumentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurDocumentController.php:18
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/documents/{document}'
 */
    const showForm = (args: { entrepreneurProfile: string | { id: string }, document: string | { id: string } } | [entrepreneurProfile: string | { id: string }, document: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurDocumentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurDocumentController.php:18
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/documents/{document}'
 */
        showForm.get = (args: { entrepreneurProfile: string | { id: string }, document: string | { id: string } } | [entrepreneurProfile: string | { id: string }, document: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurDocumentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurDocumentController.php:18
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/documents/{document}'
 */
        showForm.head = (args: { entrepreneurProfile: string | { id: string }, document: string | { id: string } } | [entrepreneurProfile: string | { id: string }, document: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    show.form = showForm
const documents = {
    show: Object.assign(show, show),
}

export default documents