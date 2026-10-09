<?php
use PHPUnit\Framework\TestCase;

final class CatalogSchemaTest extends TestCase {
	public function test_detects_swatch_plugin_meta() {
		$this->assertSame( array( 'color' => '#ff0000', 'image' => null ), Dialogue_WC_Catalog_Schema::detect_swatch( array( 'product_attribute_color' => '#FF0000' ) ) );
		$this->assertSame( array( 'color' => '#aabbcc', 'image' => null ), Dialogue_WC_Catalog_Schema::detect_swatch( array( 'cfvsw_color' => 'abc' ) ) );
		$this->assertSame( array( 'color' => null, 'image' => '123' ), Dialogue_WC_Catalog_Schema::detect_swatch( array( 'product_attribute_image' => '123' ) ) );
		$this->assertSame( array( 'color' => '#00ff00', 'image' => null ), Dialogue_WC_Catalog_Schema::detect_swatch( array( 'my_hex' => '#00ff00', 'color' => '#111111' ), 'my_hex' ) );
		$this->assertSame( array( 'color' => null, 'image' => null ), Dialogue_WC_Catalog_Schema::detect_swatch( array( 'order' => '3' ) ) );
	}

	public function test_value_kind() {
		$this->assertSame( 'color', Dialogue_WC_Catalog_Schema::value_kind( '#fff' ) );
		$this->assertSame( 'color', Dialogue_WC_Catalog_Schema::value_kind( 'a1b2c3' ) );
		$this->assertSame( 'attachment', Dialogue_WC_Catalog_Schema::value_kind( '123456' ), 'digits only = attachment id' );
		$this->assertSame( 'url', Dialogue_WC_Catalog_Schema::value_kind( 'https://x.test/a.png' ) );
		$this->assertSame( 'text', Dialogue_WC_Catalog_Schema::value_kind( 'Red' ) );
	}
}
