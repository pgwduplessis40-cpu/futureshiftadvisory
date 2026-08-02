import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../../wayfinder';
/**
 * @see \App\Http\Controllers\Admin\PilotFeeWaiverController::update
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:110
 * @route '/admin/pilot-fee-waivers/entrepreneurs/{entrepreneurProfile}'
 */
export const update = (
    args:
        | { entrepreneurProfile: string | { id: string } }
        | [entrepreneurProfile: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
});

update.definition = {
    methods: ['patch'],
    url: '/admin/pilot-fee-waivers/entrepreneurs/{entrepreneurProfile}',
} satisfies RouteDefinition<['patch']>;

/**
 * @see \App\Http\Controllers\Admin\PilotFeeWaiverController::update
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:110
 * @route '/admin/pilot-fee-waivers/entrepreneurs/{entrepreneurProfile}'
 */
update.url = (
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
        update.definition.url
            .replace(
                '{entrepreneurProfile}',
                parsedArgs.entrepreneurProfile.toString(),
            )
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\Admin\PilotFeeWaiverController::update
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:110
 * @route '/admin/pilot-fee-waivers/entrepreneurs/{entrepreneurProfile}'
 */
update.patch = (
    args:
        | { entrepreneurProfile: string | { id: string } }
        | [entrepreneurProfile: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\Admin\PilotFeeWaiverController::update
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:110
 * @route '/admin/pilot-fee-waivers/entrepreneurs/{entrepreneurProfile}'
 */
const updateForm = (
    args:
        | { entrepreneurProfile: string | { id: string } }
        | [entrepreneurProfile: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\Admin\PilotFeeWaiverController::update
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:110
 * @route '/admin/pilot-fee-waivers/entrepreneurs/{entrepreneurProfile}'
 */
updateForm.patch = (
    args:
        | { entrepreneurProfile: string | { id: string } }
        | [entrepreneurProfile: string | { id: string }]
        | string
        | { id: string },
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

update.form = updateForm;
const entrepreneurs = {
    update: Object.assign(update, update),
};

export default entrepreneurs;
