<?php

namespace Crater\Http\Middleware;

use Fideloper\Proxy\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array|string|null
     */
    protected $proxies;

    /**
     * The current proxy header mappings.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_ALL;

    public function __construct()
    {
        // Trust all proxies by default (for Docker/nginx setup)
        $this->proxies = config('trustedproxy.proxies', '*');
    }
}
