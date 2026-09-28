<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\HmacSigner;
use PHPUnit\Framework\TestCase;

class HmacSignerTest extends TestCase
{
    private HmacSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signer = new HmacSigner;
    }

    public function test_signature_matches_fixed_reference_vector(): void
    {
        $signature = $this->signer->sign(
            'POST',
            '/api/v1/wordpress/connect',
            '1700000000',
            'nonce_0123456789abcdef',
            '{"a":1}',
            'test-secret',
        );

        // hmac_sha256("test-secret",
        //   "POST\n/api/v1/wordpress/connect\n1700000000\nnonce_0123456789abcdef\n"
        //   . sha256('{"a":1}'))
        // Pins the canonical payload format for both directions.
        $this->assertSame(
            'd4f24096e5ef5ef7e54ee7842ce1df32bfad0d4987839db4dbac12802c8a0902',
            $signature,
        );
    }

    public function test_payload_joins_with_newlines_uppercases_method_and_hashes_body(): void
    {
        $payload = $this->signer->payload('post', '/path', '1700000000', 'n0123456789abcdef', 'body');

        $this->assertSame(
            "POST\n/path\n1700000000\nn0123456789abcdef\n".hash('sha256', 'body'),
            $payload,
        );
    }

    public function test_tampering_with_any_component_changes_the_signature(): void
    {
        $baseline = $this->signer->sign('POST', '/p', '1700000000', 'n0123456789abcdef', '{}', 's');

        $this->assertNotSame($baseline, $this->signer->sign('GET', '/p', '1700000000', 'n0123456789abcdef', '{}', 's'));
        $this->assertNotSame($baseline, $this->signer->sign('POST', '/other', '1700000000', 'n0123456789abcdef', '{}', 's'));
        $this->assertNotSame($baseline, $this->signer->sign('POST', '/p', '1700000001', 'n0123456789abcdef', '{}', 's'));
        $this->assertNotSame($baseline, $this->signer->sign('POST', '/p', '1700000000', 'n1123456789abcdef', '{}', 's'));
        $this->assertNotSame($baseline, $this->signer->sign('POST', '/p', '1700000000', 'n0123456789abcdef', '{"x":1}', 's'));
        $this->assertNotSame($baseline, $this->signer->sign('POST', '/p', '1700000000', 'n0123456789abcdef', '{}', 'other-secret'));
    }

    public function test_verify_accepts_a_correct_signature_and_rejects_tampering(): void
    {
        $signature = $this->signer->sign('POST', '/p', '1700000000', 'n0123456789abcdef', '{"ok":true}', 'secret');

        $this->assertTrue($this->signer->verify('POST', '/p', '1700000000', 'n0123456789abcdef', '{"ok":true}', 'secret', $signature));
        $this->assertFalse($this->signer->verify('POST', '/p', '1700000000', 'n0123456789abcdef', '{"ok":false}', 'secret', $signature));
        $this->assertFalse($this->signer->verify('POST', '/p', '1700000000', 'n0123456789abcdef', '{"ok":true}', 'wrong', $signature));
        $this->assertFalse($this->signer->verify('POST', '/p', '1700000000', 'n0123456789abcdef', '{"ok":true}', 'secret', 'deadbeef'));
    }
}
