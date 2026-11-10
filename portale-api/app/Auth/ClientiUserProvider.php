<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Provider dei clienti B2B per il reset password: considera solo i clienti che hanno già una password.
 * Più anagrafiche ERP possono condividere la stessa email: il link va a quella che ha l'accesso,
 * e un cliente senza accesso non può attivarsi da solo con questa procedura.
 */
class ClientiUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $credentials = array_filter($credentials, fn ($key) => ! str_contains($key, 'password'), ARRAY_FILTER_USE_KEY);

        if ($credentials === []) {
            return null;
        }

        $query = $this->newModelQuery()->whereNotNull('password');

        foreach ($credentials as $key => $value) {
            $query->where($key, $value);
        }

        return $query->first();
    }
}
