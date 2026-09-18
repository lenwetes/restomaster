<?php

/**
 * IDE Helper Stubs for Intelephense / VS Code.
 * This file is not loaded at runtime and serves only for IDE code intelligence.
 */

namespace Illuminate\Contracts\Auth {
    use App\Models\Role;

    /**
     * @method \App\Models\User|null user()
     * @method int|string|null id()
     * @method bool check()
     * @method bool guest()
     * @method bool hasUser()
     * @method void logout()
     * @method bool validate(array $credentials = [])
     * @method bool attempt(array $credentials = [], bool $remember = false)
     */
    interface Factory {}

    /**
     * @method \App\Models\User|null user()
     * @method int|string|null id()
     * @method bool check()
     * @method bool guest()
     */
    interface Guard {}

    /**
     * @property int $id
     * @property string $name
     * @property string $email
     * @property int|null $role_id
     * @property int|null $sucursal_id
     * @property-read Role|null $role
     *
     * @method bool isAdmin()
     * @method bool isMesero()
     * @method bool can(string|iterable $abilities, array|mixed $arguments = [])
     * @method bool cannot(string|iterable $abilities, array|mixed $arguments = [])
     */
    interface Authenticatable {}
}

namespace Illuminate\Support\Facades {
    /**
     * @method static \App\Models\User|null user()
     * @method static int|string|null id()
     * @method static bool check()
     * @method static bool guest()
     */
    class Auth {}
}

namespace {
    use App\Models\User;
    use Illuminate\Contracts\Auth\Factory;
    use Illuminate\Contracts\Auth\Guard;

    if (! function_exists('auth')) {
        /**
         * Get the available auth instance.
         *
         * @param  string|null  $guard
         * @return Factory|Guard|User
         */
        function auth($guard = null) {}
    }
}
