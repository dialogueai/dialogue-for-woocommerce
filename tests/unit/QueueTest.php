<?php
use PHPUnit\Framework\TestCase;

final class QueueTest extends TestCase {
	public function test_backoff() {
		$this->assertSame( 60, Dialogue_WC_Queue::retry_delay( 1 ) );
		$this->assertSame( 120, Dialogue_WC_Queue::retry_delay( 2 ) );
		$this->assertSame( 6 * 3600, Dialogue_WC_Queue::retry_delay( 20 ) );
		$this->assertTrue( Dialogue_WC_Queue::should_retry( 7 ) );
		$this->assertFalse( Dialogue_WC_Queue::should_retry( 8 ) );
	}
}
