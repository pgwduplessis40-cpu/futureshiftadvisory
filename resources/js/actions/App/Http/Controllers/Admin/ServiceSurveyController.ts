import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../../../wayfinder';
/**
 * @see \App\Http\Controllers\Admin\ServiceSurveyController::index
 * @see app/Http/Controllers/Admin/ServiceSurveyController.php:28
 * @route '/admin/service-surveys'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});

index.definition = {
    methods: ['get', 'head'],
    url: '/admin/service-surveys',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\Admin\ServiceSurveyController::index
 * @see app/Http/Controllers/Admin/ServiceSurveyController.php:28
 * @route '/admin/service-surveys'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\Admin\ServiceSurveyController::index
 * @see app/Http/Controllers/Admin/ServiceSurveyController.php:28
 * @route '/admin/service-surveys'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\ServiceSurveyController::index
 * @see app/Http/Controllers/Admin/ServiceSurveyController.php:28
 * @route '/admin/service-surveys'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\Admin\ServiceSurveyController::index
 * @see app/Http/Controllers/Admin/ServiceSurveyController.php:28
 * @route '/admin/service-surveys'
 */
const indexForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\Admin\ServiceSurveyController::index
 * @see app/Http/Controllers/Admin/ServiceSurveyController.php:28
 * @route '/admin/service-surveys'
 */
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\Admin\ServiceSurveyController::index
 * @see app/Http/Controllers/Admin/ServiceSurveyController.php:28
 * @route '/admin/service-surveys'
 */
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

index.form = indexForm;
/**
 * @see \App\Http\Controllers\Admin\ServiceSurveyController::store
 * @see app/Http/Controllers/Admin/ServiceSurveyController.php:82
 * @route '/admin/service-surveys/{serviceActivation}'
 */
export const store = (
    args:
        | { serviceActivation: string | { id: string } }
        | [serviceActivation: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
});

store.definition = {
    methods: ['post'],
    url: '/admin/service-surveys/{serviceActivation}',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\Admin\ServiceSurveyController::store
 * @see app/Http/Controllers/Admin/ServiceSurveyController.php:82
 * @route '/admin/service-surveys/{serviceActivation}'
 */
store.url = (
    args:
        | { serviceActivation: string | { id: string } }
        | [serviceActivation: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { serviceActivation: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { serviceActivation: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            serviceActivation: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        serviceActivation:
            typeof args.serviceActivation === 'object'
                ? args.serviceActivation.id
                : args.serviceActivation,
    };

    return (
        store.definition.url
            .replace(
                '{serviceActivation}',
                parsedArgs.serviceActivation.toString(),
            )
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Admin\ServiceSurveyController::store
 * @see app/Http/Controllers/Admin/ServiceSurveyController.php:82
 * @route '/admin/service-surveys/{serviceActivation}'
 */
store.post = (
    args:
        | { serviceActivation: string | { id: string } }
        | [serviceActivation: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\ServiceSurveyController::store
 * @see app/Http/Controllers/Admin/ServiceSurveyController.php:82
 * @route '/admin/service-surveys/{serviceActivation}'
 */
const storeForm = (
    args:
        | { serviceActivation: string | { id: string } }
        | [serviceActivation: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\ServiceSurveyController::store
 * @see app/Http/Controllers/Admin/ServiceSurveyController.php:82
 * @route '/admin/service-surveys/{serviceActivation}'
 */
storeForm.post = (
    args:
        | { serviceActivation: string | { id: string } }
        | [serviceActivation: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
});

store.form = storeForm;
/**
 * @see \App\Http\Controllers\Admin\ServiceSurveyController::storeForEntrepreneur
 * @see app/Http/Controllers/Admin/ServiceSurveyController.php:115
 * @route '/admin/service-surveys/entrepreneurs/{entrepreneurProfile}'
 */
export const storeForEntrepreneur = (
    args:
        | { entrepreneurProfile: string | { id: string } }
        | [entrepreneurProfile: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: storeForEntrepreneur.url(args, options),
    method: 'post',
});

storeForEntrepreneur.definition = {
    methods: ['post'],
    url: '/admin/service-surveys/entrepreneurs/{entrepreneurProfile}',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\Admin\ServiceSurveyController::storeForEntrepreneur
 * @see app/Http/Controllers/Admin/ServiceSurveyController.php:115
 * @route '/admin/service-surveys/entrepreneurs/{entrepreneurProfile}'
 */
storeForEntrepreneur.url = (
    args:
        | { entrepreneurProfile: string | { id: string } }
        | [entrepreneurProfile: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { entrepreneurProfile: args };
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { entrepreneurProfile: args.id };
    }

    if (Array.isArray(args)) {
        args = {
            entrepreneurProfile: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        entrepreneurProfile:
            typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
    };

    return (
        storeForEntrepreneur.definition.url
            .replace(
                '{entrepreneurProfile}',
                parsedArgs.entrepreneurProfile.toString(),
            )
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Admin\ServiceSurveyController::storeForEntrepreneur
 * @see app/Http/Controllers/Admin/ServiceSurveyController.php:115
 * @route '/admin/service-surveys/entrepreneurs/{entrepreneurProfile}'
 */
storeForEntrepreneur.post = (
    args:
        | { entrepreneurProfile: string | { id: string } }
        | [entrepreneurProfile: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: storeForEntrepreneur.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\ServiceSurveyController::storeForEntrepreneur
 * @see app/Http/Controllers/Admin/ServiceSurveyController.php:115
 * @route '/admin/service-surveys/entrepreneurs/{entrepreneurProfile}'
 */
const storeForEntrepreneurForm = (
    args:
        | { entrepreneurProfile: string | { id: string } }
        | [entrepreneurProfile: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: storeForEntrepreneur.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\ServiceSurveyController::storeForEntrepreneur
 * @see app/Http/Controllers/Admin/ServiceSurveyController.php:115
 * @route '/admin/service-surveys/entrepreneurs/{entrepreneurProfile}'
 */
storeForEntrepreneurForm.post = (
    args:
        | { entrepreneurProfile: string | { id: string } }
        | [entrepreneurProfile: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: storeForEntrepreneur.url(args, options),
    method: 'post',
});

storeForEntrepreneur.form = storeForEntrepreneurForm;
const ServiceSurveyController = { index, store, storeForEntrepreneur };

export default ServiceSurveyController;
