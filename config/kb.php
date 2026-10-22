<?php

return [
    /*
     * Titles for Windows KB articles are read from the public Microsoft Update
     * Catalog. There is no API for it, so a small HTML page is parsed and the
     * result cached in the kb_catalog table.
     */
    'catalog_url' => env('KB_CATALOG_URL', 'https://www.catalog.update.microsoft.com/Search.aspx'),

    /*
     * Same escape hatch as OPENSEARCH_TLS_VERIFY: some local PHP builds ship a
     * php.ini whose curl.cainfo points at a missing bundle, which makes every
     * HTTPS call fail. Leave this true in production.
     */
    'tls_verify' => filter_var(env('KB_CATALOG_TLS_VERIFY', true), FILTER_VALIDATE_BOOLEAN),

    'timeout' => (int) env('KB_CATALOG_TIMEOUT', 8),

    /* Lookups per background batch when an agent page asks for missing titles. */
    'batch' => (int) env('KB_CATALOG_BATCH', 5),
];
