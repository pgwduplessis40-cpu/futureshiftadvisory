import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::runAnalysis
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:20
 * @route '/advisor/clients/{client}/standard-advisory/analysis'
 */
export const runAnalysis = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: runAnalysis.url(args, options),
    method: 'post',
})

runAnalysis.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/standard-advisory/analysis',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::runAnalysis
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:20
 * @route '/advisor/clients/{client}/standard-advisory/analysis'
 */
runAnalysis.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { client: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { client: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                }

    return runAnalysis.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::runAnalysis
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:20
 * @route '/advisor/clients/{client}/standard-advisory/analysis'
 */
runAnalysis.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: runAnalysis.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::runAnalysis
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:20
 * @route '/advisor/clients/{client}/standard-advisory/analysis'
 */
    const runAnalysisForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: runAnalysis.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::runAnalysis
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:20
 * @route '/advisor/clients/{client}/standard-advisory/analysis'
 */
        runAnalysisForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: runAnalysis.url(args, options),
            method: 'post',
        })
    
    runAnalysis.form = runAnalysisForm
/**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::generatePack
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:32
 * @route '/advisor/clients/{client}/standard-advisory/pack'
 */
export const generatePack = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generatePack.url(args, options),
    method: 'post',
})

generatePack.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/standard-advisory/pack',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::generatePack
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:32
 * @route '/advisor/clients/{client}/standard-advisory/pack'
 */
generatePack.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { client: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { client: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                }

    return generatePack.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::generatePack
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:32
 * @route '/advisor/clients/{client}/standard-advisory/pack'
 */
generatePack.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generatePack.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::generatePack
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:32
 * @route '/advisor/clients/{client}/standard-advisory/pack'
 */
    const generatePackForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: generatePack.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::generatePack
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:32
 * @route '/advisor/clients/{client}/standard-advisory/pack'
 */
        generatePackForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: generatePack.url(args, options),
            method: 'post',
        })
    
    generatePack.form = generatePackForm
const StandardAdvisoryController = { runAnalysis, generatePack }

export default StandardAdvisoryController