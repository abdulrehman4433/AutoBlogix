<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifyWordPressSignature;
use App\Http\Requests\ConnectRequest;
use App\Http\Requests\HeartbeatRequest;
use App\Http\Requests\PublishResultRequest;
use App\Models\Website;
use App\Services\PublishingService;
use App\Services\WordPressConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound WordPress API (HMAC-signed). Responses use the { "data": ... }
 * success envelope; errors use { "message", "error" }.
 */
class WordPressApiController extends Controller
{
    public function __construct(
        private readonly WordPressConnectionService $connections,
        private readonly PublishingService $publishing,
    ) {}

    public function connect(ConnectRequest $request): JsonResponse
    {
        $data = $this->connections->connect(
            $this->website($request),
            $request->validated(),
            $request->ip(),
        );

        return response()->json(['data' => $data]);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $this->connections->verify($this->website($request), $request->ip());

        return response()->json(['data' => $data]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $data = $this->connections->disconnect($this->website($request), $request->ip());

        return response()->json(['data' => $data]);
    }

    public function heartbeat(HeartbeatRequest $request): JsonResponse
    {
        $data = $this->connections->heartbeat(
            $this->website($request),
            $request->validated(),
            $request->ip(),
        );

        return response()->json(['data' => $data]);
    }

    public function publishResult(PublishResultRequest $request): JsonResponse
    {
        $result = $this->publishing->recordPluginResult(
            $this->website($request),
            $request->validated(),
        );

        if ($result === null) {
            return response()->json([
                'message' => 'No post matches the supplied idempotency key for this website.',
                'error' => 'unknown_idempotency_key',
            ], 422);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * The website authenticated by the signature middleware.
     */
    private function website(Request $request): Website
    {
        return VerifyWordPressSignature::website($request);
    }
}
