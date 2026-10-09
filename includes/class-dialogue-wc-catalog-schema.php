<?php
/**
 * What the catalog offers for field mapping in the Dialogue dashboard: product fields, global and
 * custom attributes, product meta keys (incl. ACF / brand plugins) and taxonomies, each with sample
 * values; plus color / image swatches set by popular swatch plugins on attribute terms.
 *
 * Read only, returned to Dialogue over signed REST calls.
 *
 * @package Dialogue_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Catalog schema and swatches.
 */
class Dialogue_WC_Catalog_Schema {

	/** Term meta keys used by common swatch plugins, best first. */
	const COLOR_META_KEYS = array(
		'product_attribute_color', // Variation Swatches for WooCommerce (Emran Ahmed / GetWooPlugins).
		'cfvsw_color',             // Variation Swatches by CartFlows.
		'swatches_id_color',
		'color',
		'_swatch_color',
		'pa_color',
		'term_color',
	);

	/** Term meta keys holding a swatch image (attachment id or URL). */
	const IMAGE_META_KEYS = array(
		'product_attribute_image',
		'cfvsw_image',
		'swatches_id_photo',
		'image',
		'thumbnail_id',
		'_swatch_image',
	);

	const BRAND_TAXONOMIES = array( 'product_brand', 'pwb-brand', 'yith_product_brand', 'berocket_brand', 'brand' );

	const SAMPLE_PRODUCTS = 40;

	/** WooCommerce internal taxonomies (not useful for mapping). */
	const INTERNAL_TAXONOMIES = array( 'product_type', 'product_visibility', 'product_shipping_class', 'pos_product_visibility' );

	/**
	 * Term names are stored HTML-escaped ("Hoodies &amp; Sweats"): plain text for Dialogue.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function decode( $name ) {
		return html_entity_decode( (string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * The whole schema.
	 *
	 * @return array
	 */
	public static function get() {
		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => self::SAMPLE_PRODUCTS,
				'orderby' => 'modified',
				'order'   => 'DESC',
			)
		);
		return array(
			'fields'            => self::product_fields(),
			'global_attributes' => self::global_attributes(),
			'custom_attributes' => self::custom_attributes( $products ),
			'meta_keys'         => self::meta_keys( $products ),
			'taxonomies'        => self::taxonomies(),
			'swatch_plugins'    => self::detected_swatch_plugins(),
		);
	}

	/**
	 * Built-in product fields usable as a mapping source.
	 *
	 * @return array
	 */
	public static function product_fields() {
		$out = array();
		foreach ( array( 'name', 'slug', 'sku', 'short_description', 'description', 'permalink', 'type', 'global_unique_id', 'weight', 'menu_order' ) as $f ) {
			$out[] = array(
				'source' => 'field:' . $f,
				'label'  => ucwords( str_replace( '_', ' ', $f ) ),
			);
		}
		return $out;
	}

	/**
	 * Global attributes (pa_*) with sample terms and detected swatch meta.
	 *
	 * @return array
	 */
	public static function global_attributes() {
		$out = array();
		foreach ( wc_get_attribute_taxonomies() as $tax ) {
			$taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );
			$terms    = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => 12,
				)
			);
			$samples  = array();
			$meta     = array();
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$samples[] = self::decode( $term->name );
					foreach ( (array) get_term_meta( $term->term_id ) as $key => $values ) {
						if ( ! isset( $meta[ $key ] ) && isset( $values[0] ) && is_scalar( $values[0] ) && '' !== (string) $values[0] ) {
							$meta[ $key ] = substr( (string) $values[0], 0, 80 );
						}
					}
				}
			}
			$meta_list = array();
			foreach ( $meta as $key => $sample ) {
				$meta_list[] = array(
					'key'    => (string) $key,
					'sample' => $sample,
					'kind'   => self::value_kind( $sample ),
				);
			}
			$out[] = array(
				'source'    => 'attr:' . $taxonomy,
				'taxonomy'  => $taxonomy,
				'label'     => $tax->attribute_label,
				'type'      => $tax->attribute_type,
				'samples'   => $samples,
				'term_meta' => $meta_list,
			);
		}
		return $out;
	}

	/**
	 * Custom (per product) attributes seen on recent products.
	 *
	 * @param WC_Product[] $products Sample.
	 * @return array
	 */
	public static function custom_attributes( $products ) {
		$seen = array();
		foreach ( (array) $products as $product ) {
			foreach ( $product->get_attributes() as $attr ) {
				if ( ! is_object( $attr ) || $attr->is_taxonomy() ) {
					continue;
				}
				$name = (string) $attr->get_name();
				if ( ! isset( $seen[ $name ] ) ) {
					$seen[ $name ] = array();
				}
				$seen[ $name ] = array_slice( array_unique( array_merge( $seen[ $name ], array_map( 'strval', $attr->get_options() ) ) ), 0, 8 );
			}
		}
		$out = array();
		foreach ( $seen as $name => $samples ) {
			$out[] = array(
				'source'  => 'attr:' . $name,
				'label'   => $name,
				'samples' => $samples,
			);
		}
		return $out;
	}

	/**
	 * Product meta keys (public ones plus ACF fields) seen on recent products, with a sample value.
	 *
	 * @param WC_Product[] $products Sample.
	 * @return array
	 */
	public static function meta_keys( $products ) {
		$seen = array();
		foreach ( (array) $products as $product ) {
			foreach ( $product->get_meta_data() as $meta ) {
				$data = $meta->get_data();
				$key  = (string) $data['key'];
				if ( '' === $key || '_' === $key[0] || isset( $seen[ $key ] ) ) {
					continue;
				}
				if ( is_scalar( $data['value'] ) && '' !== (string) $data['value'] ) {
					$seen[ $key ] = substr( wp_strip_all_tags( (string) $data['value'] ), 0, 80 );
				}
			}
		}
		$out = array();
		foreach ( $seen as $key => $sample ) {
			$out[] = array(
				'source' => 'meta:' . $key,
				'label'  => $key,
				'sample' => $sample,
			);
		}
		return $out;
	}

	/**
	 * Product taxonomies other than attributes (categories, tags, brands, plugin taxonomies).
	 *
	 * @return array
	 */
	public static function taxonomies() {
		$out = array();
		foreach ( get_object_taxonomies( 'product', 'objects' ) as $tax ) {
			if ( in_array( $tax->name, self::INTERNAL_TAXONOMIES, true ) || 0 === strpos( $tax->name, 'pa_' ) ) {
				continue;
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $tax->name,
					'hide_empty' => true,
					'number'     => 8,
					'fields'     => 'names',
				)
			);
			$out[] = array(
				'source'   => 'tax:' . $tax->name,
				'label'    => $tax->label,
				'is_brand' => in_array( $tax->name, self::BRAND_TAXONOMIES, true ),
				'samples'  => is_array( $terms ) ? array_map( array( __CLASS__, 'decode' ), array_values( $terms ) ) : array(),
			);
		}
		return $out;
	}

	/**
	 * Which swatch plugins look active.
	 *
	 * @return string[]
	 */
	public static function detected_swatch_plugins() {
		$found = array();
		if ( class_exists( 'Woo_Variation_Swatches' ) || function_exists( 'woo_variation_swatches' ) ) {
			$found[] = 'woo-variation-swatches';
		}
		if ( defined( 'CFVSW_VER' ) || class_exists( 'CFVSW\\Plugin_Loader' ) ) {
			$found[] = 'cartflows-variation-swatches';
		}
		if ( class_exists( 'TA_WC_Variation_Swatches' ) ) {
			$found[] = 'variation-swatches-for-woocommerce';
		}
		return $found;
	}

	/**
	 * Swatch per term of an attribute taxonomy.
	 *
	 * @param string $taxonomy  pa_* taxonomy.
	 * @param string $color_key Term meta key holding the color ('' = detect).
	 * @param string $image_key Term meta key holding the image ('' = detect).
	 * @return array
	 */
	public static function swatches( $taxonomy, $color_key = '', $image_key = '' ) {
		if ( ! taxonomy_exists( $taxonomy ) || 0 !== strpos( $taxonomy, 'pa_' ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 500,
			)
		);
		$out   = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$meta = array();
			foreach ( (array) get_term_meta( $term->term_id ) as $key => $values ) {
				$meta[ $key ] = isset( $values[0] ) ? $values[0] : '';
			}
			$sw = self::detect_swatch( $meta, $color_key, $image_key );
			if ( $sw['image'] && ctype_digit( (string) $sw['image'] ) ) {
				$url         = wp_get_attachment_image_url( (int) $sw['image'], 'thumbnail' );
				$sw['image'] = $url ? $url : null;
			}
			$out[] = array(
				'slug'  => $term->slug,
				'name'  => self::decode( $term->name ),
				'color' => $sw['color'],
				'image' => $sw['image'],
			);
		}
		return $out;
	}

	/**
	 * Find the color / image in a term's meta (pure; unit tested).
	 *
	 * @param array  $meta      key => value.
	 * @param string $color_key Preferred color key.
	 * @param string $image_key Preferred image key.
	 * @return array{color: ?string, image: ?string}
	 */
	public static function detect_swatch( array $meta, $color_key = '', $image_key = '' ) {
		$color = null;
		$image = null;
		$keys  = $color_key ? array( $color_key ) : self::COLOR_META_KEYS;
		foreach ( $keys as $k ) {
			if ( isset( $meta[ $k ] ) && 'color' === self::value_kind( $meta[ $k ] ) ) {
				$color = self::normalize_hex( $meta[ $k ] );
				break;
			}
		}
		$keys = $image_key ? array( $image_key ) : self::IMAGE_META_KEYS;
		foreach ( $keys as $k ) {
			if ( isset( $meta[ $k ] ) && in_array( self::value_kind( $meta[ $k ] ), array( 'url', 'attachment' ), true ) ) {
				$image = (string) $meta[ $k ];
				break;
			}
		}
		return array(
			'color' => $color,
			'image' => $image,
		);
	}

	/**
	 * What a meta value looks like: color | url | attachment | text.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function value_kind( $value ) {
		$v = trim( (string) $value );
		if ( preg_match( '/^#?([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $v ) && ( '#' === substr( $v, 0, 1 ) || preg_match( '/[a-f]/i', $v ) ) ) {
			return 'color';
		}
		if ( preg_match( '#^https?://#i', $v ) ) {
			return 'url';
		}
		if ( ctype_digit( $v ) && (int) $v > 0 ) {
			return 'attachment';
		}
		return 'text';
	}

	/**
	 * "#ABC" / "aabbcc" → "#aabbcc".
	 *
	 * @param string $v Value.
	 * @return string
	 */
	public static function normalize_hex( $v ) {
		$v = ltrim( strtolower( trim( (string) $v ) ), '#' );
		if ( 3 === strlen( $v ) ) {
			$v = $v[0] . $v[0] . $v[1] . $v[1] . $v[2] . $v[2];
		}
		return '#' . $v;
	}
}
