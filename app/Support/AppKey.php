<?php

namespace App\Support;

class AppKey
{
    /**
     * Sessions, cookies and the encrypted indexer password all need a key.
     * A fresh clone has none, and writing .env while a dev server is serving a
     * request would restart it mid-response, so the generated key lives in
     * storage instead. An APP_KEY in .env always wins.
     */
    public static function ensure(): void
    {
        if ((string) config('app.key') !== '') {
            return;
        }

        $path = storage_path('framework/app-key');
        $key = is_file($path) ? trim((string) file_get_contents($path)) : '';

        if (preg_match('/^base64:[A-Za-z0-9+\/]+=*$/', $key) !== 1) {
            $key = 'base64:'.base64_encode(random_bytes(32));

            if (! is_dir(dirname($path))) {
                @mkdir(dirname($path), 0775, true);
            }

            @file_put_contents($path, $key, LOCK_EX);
        }

        config(['app.key' => $key]);
    }
}
