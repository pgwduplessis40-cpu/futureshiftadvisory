import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::analysis
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:20
 * @route '/advisor/clients/{client}/standard-advisory/analysis'
 */
export const analysis = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: analysis.url(args, options),
    method: 'post',
})

analysis.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/standard-advisory/analysis',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::analysis
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:20
 * @route '/advisor/clients/{client}/standard-advisory/analysis'
 */
analysis.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return analysis.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::analysis
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:20
 * @route '/advisor/clients/{client}/standard-advisory/analysis'
 */
analysis.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: analysis.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::analysis
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:20
 * @route '/advisor/clients/{client}/standard-advisory/analysis'
 */
    const analysisForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: analysis.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::analysis
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:20
 * @route '/advisor/clients/{client}/standard-advisory/analysis'
 */
        analysisForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: analysis.url(args, options),
            method: 'post',
        })
    
    analysis.form = analysisForm
/**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::pack
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:32
 * @route '/advisor/clients/{client}/standard-advisory/pack'
 */
export const pack = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pack.url(args, options),
    method: 'post',
})

pack.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/standard-advisory/pack',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::pack
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:32
 * @route '/advisor/clients/{client}/standard-advisory/pack'
 */
pack.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return pack.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::pack
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:32
 * @route '/advisor/clients/{client}/standard-advisory/pack'
 */
pack.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pack.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::pack
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:32
 * @route '/advisor/clients/{client}/standard-advisory/pack'
 */
    const packForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: pack.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\StandardAdvisoryController::pack
 * @see app/Http/Controllers/Advisor/StandardAdvisoryController.php:32
 * @route '/advisor/clients/{client}/standard-advisory/pack'
 */
        packForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: pack.url(args, options),
            method: 'post',
        })
    
    pack.form = packForm
const standardAdvisory = {
    analysis: Object.assign(analysis, analysis),
pack: Object.assign(pack, pack),
}

export default standardAdvisory