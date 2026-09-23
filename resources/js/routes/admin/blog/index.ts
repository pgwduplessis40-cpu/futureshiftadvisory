import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\BlogPostController::index
* @see [unknown]:0
* @route '/admin/blog'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/blog',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\BlogPostController::index
* @see [unknown]:0
* @route '/admin/blog'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\BlogPostController::index
* @see [unknown]:0
* @route '/admin/blog'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::index
* @see [unknown]:0
* @route '/admin/blog'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::index
* @see [unknown]:0
* @route '/admin/blog'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::index
* @see [unknown]:0
* @route '/admin/blog'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::index
* @see [unknown]:0
* @route '/admin/blog'
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
* @see \App\Http\Controllers\Admin\BlogPostController::create
* @see [unknown]:0
* @route '/admin/blog/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/admin/blog/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\BlogPostController::create
* @see [unknown]:0
* @route '/admin/blog/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\BlogPostController::create
* @see [unknown]:0
* @route '/admin/blog/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::create
* @see [unknown]:0
* @route '/admin/blog/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::create
* @see [unknown]:0
* @route '/admin/blog/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::create
* @see [unknown]:0
* @route '/admin/blog/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::create
* @see [unknown]:0
* @route '/admin/blog/create'
*/
createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

create.form = createForm

/**
* @see \App\Http\Controllers\Admin\BlogPostController::importMethod
* @see [unknown]:0
* @route '/admin/blog/import'
*/
export const importMethod = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importMethod.url(options),
    method: 'post',
})

importMethod.definition = {
    methods: ["post"],
    url: '/admin/blog/import',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\BlogPostController::importMethod
* @see [unknown]:0
* @route '/admin/blog/import'
*/
importMethod.url = (options?: RouteQueryOptions) => {
    return importMethod.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\BlogPostController::importMethod
* @see [unknown]:0
* @route '/admin/blog/import'
*/
importMethod.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importMethod.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::importMethod
* @see [unknown]:0
* @route '/admin/blog/import'
*/
const importMethodForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: importMethod.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::importMethod
* @see [unknown]:0
* @route '/admin/blog/import'
*/
importMethodForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: importMethod.url(options),
    method: 'post',
})

importMethod.form = importMethodForm

/**
* @see \App\Http\Controllers\Admin\BlogPostController::store
* @see [unknown]:0
* @route '/admin/blog'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/blog',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\BlogPostController::store
* @see [unknown]:0
* @route '/admin/blog'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\BlogPostController::store
* @see [unknown]:0
* @route '/admin/blog'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::store
* @see [unknown]:0
* @route '/admin/blog'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::store
* @see [unknown]:0
* @route '/admin/blog'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Admin\BlogPostController::edit
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/edit'
*/
export const edit = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/blog/{blogPost}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\BlogPostController::edit
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/edit'
*/
edit.url = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { blogPost: args }
    }

    if (Array.isArray(args)) {
        args = {
            blogPost: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        blogPost: args.blogPost,
    }

    return edit.definition.url
            .replace('{blogPost}', parsedArgs.blogPost.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\BlogPostController::edit
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/edit'
*/
edit.get = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::edit
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/edit'
*/
edit.head = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::edit
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/edit'
*/
const editForm = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::edit
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/edit'
*/
editForm.get = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::edit
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/edit'
*/
editForm.head = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

edit.form = editForm

/**
* @see \App\Http\Controllers\Admin\BlogPostController::update
* @see [unknown]:0
* @route '/admin/blog/{blogPost}'
*/
export const update = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/admin/blog/{blogPost}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\BlogPostController::update
* @see [unknown]:0
* @route '/admin/blog/{blogPost}'
*/
update.url = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { blogPost: args }
    }

    if (Array.isArray(args)) {
        args = {
            blogPost: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        blogPost: args.blogPost,
    }

    return update.definition.url
            .replace('{blogPost}', parsedArgs.blogPost.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\BlogPostController::update
* @see [unknown]:0
* @route '/admin/blog/{blogPost}'
*/
update.patch = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::update
* @see [unknown]:0
* @route '/admin/blog/{blogPost}'
*/
const updateForm = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::update
* @see [unknown]:0
* @route '/admin/blog/{blogPost}'
*/
updateForm.patch = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Admin\BlogPostController::preview
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/preview'
*/
export const preview = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})

preview.definition = {
    methods: ["get","head"],
    url: '/admin/blog/{blogPost}/preview',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\BlogPostController::preview
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/preview'
*/
preview.url = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { blogPost: args }
    }

    if (Array.isArray(args)) {
        args = {
            blogPost: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        blogPost: args.blogPost,
    }

    return preview.definition.url
            .replace('{blogPost}', parsedArgs.blogPost.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\BlogPostController::preview
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/preview'
*/
preview.get = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::preview
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/preview'
*/
preview.head = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: preview.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::preview
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/preview'
*/
const previewForm = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: preview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::preview
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/preview'
*/
previewForm.get = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: preview.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::preview
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/preview'
*/
previewForm.head = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: preview.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

preview.form = previewForm

/**
* @see \App\Http\Controllers\Admin\BlogPostController::publish
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/publish'
*/
export const publish = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publish.url(args, options),
    method: 'post',
})

publish.definition = {
    methods: ["post"],
    url: '/admin/blog/{blogPost}/publish',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\BlogPostController::publish
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/publish'
*/
publish.url = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { blogPost: args }
    }

    if (Array.isArray(args)) {
        args = {
            blogPost: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        blogPost: args.blogPost,
    }

    return publish.definition.url
            .replace('{blogPost}', parsedArgs.blogPost.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\BlogPostController::publish
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/publish'
*/
publish.post = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publish.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::publish
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/publish'
*/
const publishForm = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: publish.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::publish
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/publish'
*/
publishForm.post = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: publish.url(args, options),
    method: 'post',
})

publish.form = publishForm

/**
* @see \App\Http\Controllers\Admin\BlogPostController::unpublish
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/unpublish'
*/
export const unpublish = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: unpublish.url(args, options),
    method: 'post',
})

unpublish.definition = {
    methods: ["post"],
    url: '/admin/blog/{blogPost}/unpublish',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\BlogPostController::unpublish
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/unpublish'
*/
unpublish.url = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { blogPost: args }
    }

    if (Array.isArray(args)) {
        args = {
            blogPost: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        blogPost: args.blogPost,
    }

    return unpublish.definition.url
            .replace('{blogPost}', parsedArgs.blogPost.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\BlogPostController::unpublish
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/unpublish'
*/
unpublish.post = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: unpublish.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::unpublish
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/unpublish'
*/
const unpublishForm = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: unpublish.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::unpublish
* @see [unknown]:0
* @route '/admin/blog/{blogPost}/unpublish'
*/
unpublishForm.post = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: unpublish.url(args, options),
    method: 'post',
})

unpublish.form = unpublishForm

/**
* @see \App\Http\Controllers\Admin\BlogPostController::destroy
* @see [unknown]:0
* @route '/admin/blog/{blogPost}'
*/
export const destroy = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/admin/blog/{blogPost}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Admin\BlogPostController::destroy
* @see [unknown]:0
* @route '/admin/blog/{blogPost}'
*/
destroy.url = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { blogPost: args }
    }

    if (Array.isArray(args)) {
        args = {
            blogPost: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        blogPost: args.blogPost,
    }

    return destroy.definition.url
            .replace('{blogPost}', parsedArgs.blogPost.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\BlogPostController::destroy
* @see [unknown]:0
* @route '/admin/blog/{blogPost}'
*/
destroy.delete = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::destroy
* @see [unknown]:0
* @route '/admin/blog/{blogPost}'
*/
const destroyForm = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\BlogPostController::destroy
* @see [unknown]:0
* @route '/admin/blog/{blogPost}'
*/
destroyForm.delete = (args: { blogPost: string | number } | [blogPost: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const blog = {
    index: Object.assign(index, index),
    create: Object.assign(create, create),
    import: Object.assign(importMethod, importMethod),
    store: Object.assign(store, store),
    edit: Object.assign(edit, edit),
    update: Object.assign(update, update),
    preview: Object.assign(preview, preview),
    publish: Object.assign(publish, publish),
    unpublish: Object.assign(unpublish, unpublish),
    destroy: Object.assign(destroy, destroy),
}

export default blog
