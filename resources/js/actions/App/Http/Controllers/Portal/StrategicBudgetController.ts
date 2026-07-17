import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::show
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:36
 * @route '/portal/business-plan-budget'
 */
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/portal/business-plan-budget',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::show
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:36
 * @route '/portal/business-plan-budget'
 */
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::show
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:36
 * @route '/portal/business-plan-budget'
 */
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::show
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:36
 * @route '/portal/business-plan-budget'
 */
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::show
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:36
 * @route '/portal/business-plan-budget'
 */
    const showForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::show
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:36
 * @route '/portal/business-plan-budget'
 */
        showForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::show
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:36
 * @route '/portal/business-plan-budget'
 */
        showForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::document
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:51
 * @route '/portal/business-plan-budget/document'
 */
export const document = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: document.url(options),
    method: 'get',
})

document.definition = {
    methods: ["get","head"],
    url: '/portal/business-plan-budget/document',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::document
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:51
 * @route '/portal/business-plan-budget/document'
 */
document.url = (options?: RouteQueryOptions) => {
    return document.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::document
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:51
 * @route '/portal/business-plan-budget/document'
 */
document.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: document.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::document
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:51
 * @route '/portal/business-plan-budget/document'
 */
document.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: document.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::document
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:51
 * @route '/portal/business-plan-budget/document'
 */
    const documentForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: document.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::document
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:51
 * @route '/portal/business-plan-budget/document'
 */
        documentForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: document.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::document
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:51
 * @route '/portal/business-plan-budget/document'
 */
        documentForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: document.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    document.form = documentForm
/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:65
 * @route '/portal/business-plan-budget/pdf'
 */
export const pdf = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pdf.url(options),
    method: 'get',
})

pdf.definition = {
    methods: ["get","head"],
    url: '/portal/business-plan-budget/pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:65
 * @route '/portal/business-plan-budget/pdf'
 */
pdf.url = (options?: RouteQueryOptions) => {
    return pdf.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:65
 * @route '/portal/business-plan-budget/pdf'
 */
pdf.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pdf.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:65
 * @route '/portal/business-plan-budget/pdf'
 */
pdf.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pdf.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:65
 * @route '/portal/business-plan-budget/pdf'
 */
    const pdfForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: pdf.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:65
 * @route '/portal/business-plan-budget/pdf'
 */
        pdfForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: pdf.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::pdf
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:65
 * @route '/portal/business-plan-budget/pdf'
 */
        pdfForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: pdf.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    pdf.form = pdfForm
/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::update
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:81
 * @route '/portal/business-plan-budget'
 */
export const update = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(options),
    method: 'post',
})

update.definition = {
    methods: ["post"],
    url: '/portal/business-plan-budget',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::update
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:81
 * @route '/portal/business-plan-budget'
 */
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::update
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:81
 * @route '/portal/business-plan-budget'
 */
update.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::update
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:81
 * @route '/portal/business-plan-budget'
 */
    const updateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::update
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:81
 * @route '/portal/business-plan-budget'
 */
        updateForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(options),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::submit
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:94
 * @route '/portal/business-plan-budget/submit'
 */
export const submit = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(options),
    method: 'post',
})

submit.definition = {
    methods: ["post"],
    url: '/portal/business-plan-budget/submit',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::submit
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:94
 * @route '/portal/business-plan-budget/submit'
 */
submit.url = (options?: RouteQueryOptions) => {
    return submit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::submit
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:94
 * @route '/portal/business-plan-budget/submit'
 */
submit.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::submit
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:94
 * @route '/portal/business-plan-budget/submit'
 */
    const submitForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: submit.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::submit
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:94
 * @route '/portal/business-plan-budget/submit'
 */
        submitForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: submit.url(options),
            method: 'post',
        })
    
    submit.form = submitForm
/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::exportMethod
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:117
 * @route '/portal/business-plan-budget/export'
 */
export const exportMethod = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(options),
    method: 'get',
})

exportMethod.definition = {
    methods: ["get","head"],
    url: '/portal/business-plan-budget/export',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::exportMethod
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:117
 * @route '/portal/business-plan-budget/export'
 */
exportMethod.url = (options?: RouteQueryOptions) => {
    return exportMethod.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::exportMethod
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:117
 * @route '/portal/business-plan-budget/export'
 */
exportMethod.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::exportMethod
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:117
 * @route '/portal/business-plan-budget/export'
 */
exportMethod.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportMethod.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::exportMethod
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:117
 * @route '/portal/business-plan-budget/export'
 */
    const exportMethodForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: exportMethod.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::exportMethod
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:117
 * @route '/portal/business-plan-budget/export'
 */
        exportMethodForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: exportMethod.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::exportMethod
 * @see app/Http/Controllers/Portal/StrategicBudgetController.php:117
 * @route '/portal/business-plan-budget/export'
 */
        exportMethodForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: exportMethod.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    exportMethod.form = exportMethodForm
const StrategicBudgetController = { show, document, pdf, update, submit, exportMethod, export: exportMethod }

export default StrategicBudgetController