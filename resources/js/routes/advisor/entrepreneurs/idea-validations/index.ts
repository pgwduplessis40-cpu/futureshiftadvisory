import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::gate
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:30
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate'
 */
export const gate = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: gate.url(args, options),
    method: 'patch',
})

gate.definition = {
    methods: ["patch"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::gate
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:30
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate'
 */
gate.url = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions) => {
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

    return gate.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{ideaValidation}', parsedArgs.ideaValidation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::gate
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:30
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate'
 */
gate.patch = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: gate.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::gate
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:30
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate'
 */
    const gateForm = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: gate.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::gate
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:30
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate'
 */
        gateForm.patch = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: gate.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    gate.form = gateForm
const ideaValidations = {
    gate: Object.assign(gate, gate),
}

export default ideaValidations