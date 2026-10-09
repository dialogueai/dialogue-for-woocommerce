<?php
use PHPUnit\Framework\TestCase;

final class ProductsMergeTest extends TestCase {
	public function test_dedupes_and_delete_wins() {
		$p = Dialogue_WC_Products::merge( array(), array( 'updated' => array( 1, 2, 2 ) ) );
		$p = Dialogue_WC_Products::merge( $p, array( 'deleted' => array( 2 ) ) );
		$p = Dialogue_WC_Products::merge( $p, array( 'updated' => array( 2, 3 ) ) );
		$this->assertSame( array( 1, 3 ), $p['updated'], 'a deleted product stays deleted within the batch' );
		$this->assertSame( array( 2 ), $p['deleted'] );
		$this->assertFalse( $p['catalog_changed'] );
	}

	public function test_bulk_edits_turn_into_catalog_reread() {
		$p = Dialogue_WC_Products::merge( array(), array( 'updated' => range( 1, Dialogue_WC_Products::MAX_IDS + 1 ) ) );
		$this->assertSame( array(), $p['updated'] );
		$this->assertTrue( $p['catalog_changed'] );
	}

	public function test_ignores_invalid_ids() {
		$p = Dialogue_WC_Products::merge( array(), array( 'updated' => array( 0, -1, 'x' ), 'deleted' => array( 0 ) ) );
		$this->assertSame( array(), $p['updated'] );
		$this->assertSame( array(), $p['deleted'] );
	}
}
