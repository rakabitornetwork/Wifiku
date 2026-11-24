<?php

namespace App\Http\Middleware;

use App\Support\Permissions;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => $request->user('web')
                ? [
                    'user' => [
                        'id' => $request->user('web')->id,
                        'name' => $request->user('web')->name,
                        'email' => $request->user('web')->email,
                        'avatar_url' => $request->user('web')->avatarUrl(),
                        'roles' => $request->user('web')->getRoleNames()->all(),
                        'permissions' => $request->user('web')->hasRole('Owner')
                            ? Permissions::all()
                            : $request->user('web')->getAllPermissions()->pluck('name')->all(),
                    ],
                ]
                : null,
        ];
    }
}
