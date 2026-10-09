<?php
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {
	public function test_primary_host_then_fallback_while_unreachable() {
		$GLOBALS['dialogue_test_transients'] = array();
		$this->assertSame( 'https://plugin.nowdialogue.com', Dialogue_WC_Settings::api_base() );
		$this->assertTrue( Dialogue_WC_Settings::can_fall_back() );
		Dialogue_WC_Settings::use_fallback();
		$this->assertSame( 'https://dashboard.nowdialogue.com', Dialogue_WC_Settings::api_base() );
		$this->assertFalse( Dialogue_WC_Settings::can_fall_back() );
	}

	public function test_connect_page_hosts_are_dialogue_only() {
		$hosts = Dialogue_WC_Settings::dialogue_hosts();
		$this->assertContains( 'plugin.nowdialogue.com', $hosts );
		$this->assertContains( 'dashboard.nowdialogue.com', $hosts );
		$this->assertNotContains( 'evil.test', $hosts );
	}
}
