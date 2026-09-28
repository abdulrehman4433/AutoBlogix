<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\WordPressAuthenticationException;
use App\Models\Website;
use App\Services\WordPressAuthenticationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the X-ABX-* HMAC headers on WordPress API routes and converts
 * authentication failures into the documented JSON error envelope.
 * On success the authenticated website is attached to the request attributes.
 */
class VerifyWordPressSignature
{
    public function __construct(private readonly WordPressAuthenticationService $authentication) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $website = $this->authentication->authenticate($request);
        } catch (WordPressAuthenticationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->errorCode,
            ], $exception->status);
        }

        $request->attributes->set('abx_website', $website);

        return $next($request);
    }

    /**
     * The authenticated website, set by handle() on every API request.
     */
    public static function website(Request $request): Website
    {
        $website = $request->attributes->get('abx_website');

        abort_unless($website instanceof Website, 500, 'Authenticated website missing from request.');

        return $website;
    }
}
