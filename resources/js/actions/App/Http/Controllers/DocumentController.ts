import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\DocumentController::__invoke
 * @see app/Http/Controllers/DocumentController.php:26
 * @route '/portal/documents'
 */
const DocumentController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: DocumentController.url(options),
    method: 'post',
})

DocumentController.definition = {
    methods: ["post"],
    url: '/portal/documents',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\DocumentController::__invoke
 * @see app/Http/Controllers/DocumentController.php:26
 * @route '/portal/documents'
 */
DocumentController.url = (options?: RouteQueryOptions) => {
    return DocumentController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DocumentController::__invoke
 * @see app/Http/Controllers/DocumentController.php:26
 * @route '/portal/documents'
 */
DocumentController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: DocumentController.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\DocumentController::__invoke
 * @see app/Http/Controllers/DocumentController.php:26
 * @route '/portal/documents'
 */
    const DocumentControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: DocumentController.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\DocumentController::__invoke
 * @see app/Http/Controllers/DocumentController.php:26
 * @route '/portal/documents'
 */
        DocumentControllerForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: DocumentController.url(options),
            method: 'post',
        })
    
    DocumentController.form = DocumentControllerForm
export default DocumentController