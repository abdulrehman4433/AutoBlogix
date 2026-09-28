<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\WebsiteCredentialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WebsiteCredentialController extends Controller
{
    public function __construct(private readonly WebsiteCredentialService $credentialService) {}

    /**
     * Issue a new credential pair; the old one stops working immediately.
     * The plaintext secret is flashed for exactly one request.
     */
    public function rotate(Request $request, Website $website): RedirectResponse
    {
        Gate::authorize('rotateCredentials', $website);

        $secret = $this->credentialService->rotateCredentials($website, $request->ip());

        return redirect()
            ->route('websites.show', $website)
            ->with('plaintext_secret', $secret)
            ->with('success', 'Credentials rotated. The previous key and secret stopped working immediately.');
    }

    /**
     * Remove the credential pair so the plugin can no longer authenticate.
     */
    public function revoke(Request $request, Website $website): RedirectResponse
    {
        Gate::authorize('revokeCredentials', $website);

        $this->credentialService->revokeCredentials($website, $request->ip());

        return redirect()
            ->route('websites.show', $website)
            ->with('success', 'Credentials revoked. The plugin can no longer connect.');
    }
}
