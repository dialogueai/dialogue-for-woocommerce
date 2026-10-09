<?php
use PHPUnit\Framework\TestCase;

final class SignerTest extends TestCase {
	public function test_shared_vector_matches_backend() {
		// Same vector is asserted in dialogue-backend-1 woocommerce.signature.spec.ts.
		$this->assertSame(
			'195b5fd5264a0b1c76e3177d43b0e5c017443d371695a15d19a81aa6f4b90d43',
			Dialogue_WC_Signer::sign( 'test-secret', 'site_abc', 1700000000, '{"order_id":"42"}' )
		);
	}

	public function test_verify_accepts_valid_and_rejects_tampering() {
		$now  = 1700000000;
		$body = '{"x":1}';
		$h    = Dialogue_WC_Signer::headers( 's3cret', 'site_1', $body, $now );
		$this->assertTrue( Dialogue_WC_Signer::verify( 's3cret', 'site_1', $h['X-Dialogue-Site'], $h['X-Dialogue-Timestamp'], $h['X-Dialogue-Signature'], $body, $now ) );
		$this->assertTrue( Dialogue_WC_Signer::verify( 's3cret', 'site_1', 'site_1', $h['X-Dialogue-Timestamp'], strtoupper( $h['X-Dialogue-Signature'] ), $body, $now ) );
		$this->assertFalse( Dialogue_WC_Signer::verify( 's3cret', 'site_1', 'site_1', $h['X-Dialogue-Timestamp'], $h['X-Dialogue-Signature'], '{"x":2}', $now ), 'body changed' );
		$this->assertFalse( Dialogue_WC_Signer::verify( 'other', 'site_1', 'site_1', $h['X-Dialogue-Timestamp'], $h['X-Dialogue-Signature'], $body, $now ), 'wrong secret' );
		$this->assertFalse( Dialogue_WC_Signer::verify( 's3cret', 'site_1', 'site_2', $h['X-Dialogue-Timestamp'], $h['X-Dialogue-Signature'], $body, $now ), 'other site' );
		$this->assertFalse( Dialogue_WC_Signer::verify( 's3cret', 'site_1', 'site_1', $h['X-Dialogue-Timestamp'], $h['X-Dialogue-Signature'], $body, $now + 301 ), 'too old' );
		$this->assertFalse( Dialogue_WC_Signer::verify( '', 'site_1', 'site_1', $h['X-Dialogue-Timestamp'], $h['X-Dialogue-Signature'], $body, $now ), 'no secret' );
		$this->assertFalse( Dialogue_WC_Signer::verify( 's3cret', 'site_1', 'site_1', 'abc', $h['X-Dialogue-Signature'], $body, $now ), 'bad timestamp' );
		$this->assertFalse( Dialogue_WC_Signer::verify( 's3cret', 'site_1', 'site_1', $h['X-Dialogue-Timestamp'], '', $body, $now ), 'no signature' );
	}
}
