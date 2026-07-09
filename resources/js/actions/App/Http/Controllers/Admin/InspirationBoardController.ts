import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::index
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:26
 * @route '/admin/inspiration-board'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/inspiration-board',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::index
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:26
 * @route '/admin/inspiration-board'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::index
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:26
 * @route '/admin/inspiration-board'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::index
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:26
 * @route '/admin/inspiration-board'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\InspirationBoardController::index
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:26
 * @route '/admin/inspiration-board'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\InspirationBoardController::index
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:26
 * @route '/admin/inspiration-board'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\InspirationBoardController::index
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:26
 * @route '/admin/inspiration-board'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::store
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:36
 * @route '/admin/inspiration-board'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/inspiration-board',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::store
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:36
 * @route '/admin/inspiration-board'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::store
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:36
 * @route '/admin/inspiration-board'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\InspirationBoardController::store
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:36
 * @route '/admin/inspiration-board'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\InspirationBoardController::store
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:36
 * @route '/admin/inspiration-board'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::update
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:76
 * @route '/admin/inspiration-board/{boardPost}'
 */
export const update = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/admin/inspiration-board/{boardPost}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::update
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:76
 * @route '/admin/inspiration-board/{boardPost}'
 */
update.url = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { boardPost: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { boardPost: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    boardPost: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        boardPost: typeof args.boardPost === 'object'
                ? args.boardPost.id
                : args.boardPost,
                }

    return update.definition.url
            .replace('{boardPost}', parsedArgs.boardPost.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::update
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:76
 * @route '/admin/inspiration-board/{boardPost}'
 */
update.patch = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\InspirationBoardController::update
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:76
 * @route '/admin/inspiration-board/{boardPost}'
 */
    const updateForm = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\InspirationBoardController::update
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:76
 * @route '/admin/inspiration-board/{boardPost}'
 */
        updateForm.patch = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::publish
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:89
 * @route '/admin/inspiration-board/{boardPost}/publish'
 */
export const publish = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publish.url(args, options),
    method: 'post',
})

publish.definition = {
    methods: ["post"],
    url: '/admin/inspiration-board/{boardPost}/publish',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::publish
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:89
 * @route '/admin/inspiration-board/{boardPost}/publish'
 */
publish.url = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { boardPost: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { boardPost: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    boardPost: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        boardPost: typeof args.boardPost === 'object'
                ? args.boardPost.id
                : args.boardPost,
                }

    return publish.definition.url
            .replace('{boardPost}', parsedArgs.boardPost.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::publish
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:89
 * @route '/admin/inspiration-board/{boardPost}/publish'
 */
publish.post = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publish.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\InspirationBoardController::publish
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:89
 * @route '/admin/inspiration-board/{boardPost}/publish'
 */
    const publishForm = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: publish.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\InspirationBoardController::publish
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:89
 * @route '/admin/inspiration-board/{boardPost}/publish'
 */
        publishForm.post = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: publish.url(args, options),
            method: 'post',
        })
    
    publish.form = publishForm
/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::archive
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:100
 * @route '/admin/inspiration-board/{boardPost}/archive'
 */
export const archive = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: archive.url(args, options),
    method: 'post',
})

archive.definition = {
    methods: ["post"],
    url: '/admin/inspiration-board/{boardPost}/archive',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::archive
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:100
 * @route '/admin/inspiration-board/{boardPost}/archive'
 */
archive.url = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { boardPost: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { boardPost: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    boardPost: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        boardPost: typeof args.boardPost === 'object'
                ? args.boardPost.id
                : args.boardPost,
                }

    return archive.definition.url
            .replace('{boardPost}', parsedArgs.boardPost.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::archive
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:100
 * @route '/admin/inspiration-board/{boardPost}/archive'
 */
archive.post = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: archive.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\InspirationBoardController::archive
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:100
 * @route '/admin/inspiration-board/{boardPost}/archive'
 */
    const archiveForm = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: archive.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\InspirationBoardController::archive
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:100
 * @route '/admin/inspiration-board/{boardPost}/archive'
 */
        archiveForm.post = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: archive.url(args, options),
            method: 'post',
        })
    
    archive.form = archiveForm
/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::pin
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:107
 * @route '/admin/inspiration-board/{boardPost}/pin'
 */
export const pin = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pin.url(args, options),
    method: 'post',
})

pin.definition = {
    methods: ["post"],
    url: '/admin/inspiration-board/{boardPost}/pin',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::pin
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:107
 * @route '/admin/inspiration-board/{boardPost}/pin'
 */
pin.url = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { boardPost: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { boardPost: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    boardPost: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        boardPost: typeof args.boardPost === 'object'
                ? args.boardPost.id
                : args.boardPost,
                }

    return pin.definition.url
            .replace('{boardPost}', parsedArgs.boardPost.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::pin
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:107
 * @route '/admin/inspiration-board/{boardPost}/pin'
 */
pin.post = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pin.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\InspirationBoardController::pin
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:107
 * @route '/admin/inspiration-board/{boardPost}/pin'
 */
    const pinForm = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: pin.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\InspirationBoardController::pin
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:107
 * @route '/admin/inspiration-board/{boardPost}/pin'
 */
        pinForm.post = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: pin.url(args, options),
            method: 'post',
        })
    
    pin.form = pinForm
/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::unpin
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:114
 * @route '/admin/inspiration-board/{boardPost}/unpin'
 */
export const unpin = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: unpin.url(args, options),
    method: 'post',
})

unpin.definition = {
    methods: ["post"],
    url: '/admin/inspiration-board/{boardPost}/unpin',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::unpin
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:114
 * @route '/admin/inspiration-board/{boardPost}/unpin'
 */
unpin.url = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { boardPost: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { boardPost: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    boardPost: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        boardPost: typeof args.boardPost === 'object'
                ? args.boardPost.id
                : args.boardPost,
                }

    return unpin.definition.url
            .replace('{boardPost}', parsedArgs.boardPost.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::unpin
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:114
 * @route '/admin/inspiration-board/{boardPost}/unpin'
 */
unpin.post = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: unpin.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\InspirationBoardController::unpin
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:114
 * @route '/admin/inspiration-board/{boardPost}/unpin'
 */
    const unpinForm = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: unpin.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\InspirationBoardController::unpin
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:114
 * @route '/admin/inspiration-board/{boardPost}/unpin'
 */
        unpinForm.post = (args: { boardPost: string | { id: string } } | [boardPost: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: unpin.url(args, options),
            method: 'post',
        })
    
    unpin.form = unpinForm
const InspirationBoardController = { index, store, update, publish, archive, pin, unpin }

export default InspirationBoardController