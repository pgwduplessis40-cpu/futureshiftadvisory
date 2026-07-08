import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\PaymentController::retry
 * @see app/Http/Controllers/Advisor/PaymentController.php:20
 * @route '/advisor/payments/{payment}/retry'
 */
export const retry = (args: { payment: string | number } | [payment: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retry.url(args, options),
    method: 'post',
})

retry.definition = {
    methods: ["post"],
    url: '/advisor/payments/{payment}/retry',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\PaymentController::retry
 * @see app/Http/Controllers/Advisor/PaymentController.php:20
 * @route '/advisor/payments/{payment}/retry'
 */
retry.url = (args: { payment: string | number } | [payment: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { payment: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    payment: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        payment: args.payment,
                }

    return retry.definition.url
            .replace('{payment}', parsedArgs.payment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PaymentController::retry
 * @see app/Http/Controllers/Advisor/PaymentController.php:20
 * @route '/advisor/payments/{payment}/retry'
 */
retry.post = (args: { payment: string | number } | [payment: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retry.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\PaymentController::retry
 * @see app/Http/Controllers/Advisor/PaymentController.php:20
 * @route '/advisor/payments/{payment}/retry'
 */
    const retryForm = (args: { payment: string | number } | [payment: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: retry.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\PaymentController::retry
 * @see app/Http/Controllers/Advisor/PaymentController.php:20
 * @route '/advisor/payments/{payment}/retry'
 */
        retryForm.post = (args: { payment: string | number } | [payment: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: retry.url(args, options),
            method: 'post',
        })
    
    retry.form = retryForm
const payments = {
    retry: Object.assign(retry, retry),
}

export default payments