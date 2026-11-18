<?php
/**
 * Status badge tests.
 *
 * @package SugarCommerce\StatusBadge
 */

declare( strict_types=1 );

namespace SugarCommerce\StatusBadge\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SugarCommerce\StatusBadge\StatusBadge;

/**
 * A badge is a word, a dot and a colour, and the whole library is a lookup
 * table deciding which colour.
 *
 * That makes it look too simple to test, which is exactly why it is worth
 * testing: the failure mode is not a crash, it is a refund showing green.
 * The other half is escaping. A status is a database value, and it goes
 * into a class attribute and into text.
 */
final class StatusBadgeTest extends TestCase {

	/**
	 * Forget what was registered and enqueued.
	 */
	protected function setUp(): void {
		$GLOBALS['sb_enqueued']     = [];
		$GLOBALS['sb_registered']   = [];
		$GLOBALS['wp_plugin_paths'] = [];
	}

	/**
	 * A status maps to the type its meaning demands.
	 *
	 * @param string $status   A status value.
	 * @param string $expected The type it should be.
	 */
	#[DataProvider( 'statusProvider' )]
	public function test_a_status_maps_to_its_type( string $status, string $expected ): void {
		$this->assertSame( $expected, ( new StatusBadge() )->get_type( $status ) );
	}

	/**
	 * One status per group, and the ones most likely to be got wrong.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function statusProvider(): array {
		return [
			'active is good'             => [ 'active', StatusBadge::SUCCESS ],
			'paid is good'               => [ 'paid', StatusBadge::SUCCESS ],
			'pending is not yet'         => [ 'pending', StatusBadge::WARNING ],
			'processing is not yet'      => [ 'processing', StatusBadge::WARNING ],
			'failed is bad'              => [ 'failed', StatusBadge::DANGER ],

			// A refund is a normal business event and still not a green
			// badge: somebody scanning orders for problems needs it to
			// stand out.
			'refunded is bad'            => [ 'refunded', StatusBadge::DANGER ],
			'new is neutral news'        => [ 'new', StatusBadge::INFO ],
			'archived is just off'       => [ 'archived', StatusBadge::DEFAULT ],
			'inactive is just off'       => [ 'inactive', StatusBadge::DEFAULT ],
			'cancelled, two ells'        => [ 'cancelled', StatusBadge::DANGER ],
			'canceled, one ell'          => [ 'canceled', StatusBadge::DANGER ],
			'on-hold with a hyphen'      => [ 'on-hold', StatusBadge::WARNING ],
			'on_hold with an underscore' => [ 'on_hold', StatusBadge::WARNING ],
		];
	}

	/**
	 * An unknown status is grey rather than a guess.
	 */
	public function test_an_unknown_status_is_grey(): void {
		$this->assertSame( StatusBadge::DEFAULT, ( new StatusBadge() )->get_type( 'flurgle' ) );
	}

	/**
	 * Case and stray spaces do not change a status.
	 */
	public function test_a_status_is_matched_loosely(): void {
		$badge = new StatusBadge();

		$this->assertSame( StatusBadge::SUCCESS, $badge->get_type( 'ACTIVE' ) );
		$this->assertSame( StatusBadge::SUCCESS, $badge->get_type( '  Active  ' ) );
	}

	/**
	 * A caller's mapping wins over a default, and adds to the rest.
	 */
	public function test_a_custom_mapping_overrides_a_default(): void {
		$badge = new StatusBadge( [ 'refunded' => 'info', 'churned' => 'danger' ] );

		$this->assertSame( StatusBadge::INFO, $badge->get_type( 'refunded' ) );
		$this->assertSame( StatusBadge::DANGER, $badge->get_type( 'churned' ) );
		$this->assertSame( StatusBadge::SUCCESS, $badge->get_type( 'active' ) );
	}

	/**
	 * A custom status written in capitals still matches.
	 *
	 * Statuses are matched in lower case, so a key that was not could
	 * never be found.
	 */
	public function test_a_custom_mapping_is_case_blind(): void {
		$this->assertSame( StatusBadge::WARNING, ( new StatusBadge( [ 'Past_Due' => 'warning' ] ) )->get_type( 'past_due' ) );
	}

	/**
	 * A custom mapping to a type that does not exist is dropped.
	 *
	 * It would draw a badge with no colour, read as a status the library
	 * does not know; the default, where there is one, is kept.
	 */
	public function test_a_custom_mapping_to_no_type_is_dropped(): void {
		$badge = new StatusBadge( [ 'paid' => 'greenish', 'odd' => 'purple' ] );

		$this->assertSame( StatusBadge::SUCCESS, $badge->get_type( 'paid' ) );
		$this->assertArrayNotHasKey( 'odd', $badge->get_map() );
	}

	/**
	 * A type can be forced for one badge without changing the map.
	 */
	public function test_a_type_can_be_overridden_for_one_badge(): void {
		$badge = new StatusBadge();

		$this->assertStringContainsString( 'wp-status-badge--danger', $badge->render( 'active', 'danger' ) );
		$this->assertSame( StatusBadge::SUCCESS, $badge->get_type( 'active' ), 'The override leaked into the map.' );
	}

	/**
	 * A machine status reads as a label, in sentence case.
	 */
	public function test_a_label_is_readable(): void {
		$this->assertSame( 'In progress', StatusBadge::format_label( 'in_progress' ) );
		$this->assertSame( 'On hold', StatusBadge::format_label( 'on-hold' ) );
		$this->assertStringContainsString( '>Awaiting payment</span>', ( new StatusBadge() )->render( 'pending', null, 'Awaiting payment' ) );
		$this->assertStringEndsWith( '</svg>Partially refunded</span>', ( new StatusBadge() )->render( 'partially_refunded' ) );
	}

	/**
	 * The markup is the pill, the glyph and the word, with the classes it always had.
	 */
	public function test_the_markup(): void {
		$this->assertMatchesRegularExpression(
			'#^<span class="wp-status-badge wp-status-badge--success"><svg class="wp-status-badge__icon" [^>]*aria-hidden="true"[^>]*><path [^>]*d="M9 12\.75 [^"]+"/></svg>Active</span>$#',
			( new StatusBadge() )->render( 'active' )
		);
	}

	/**
	 * Every type has its own glyph, so a type never depends on colour alone.
	 */
	public function test_every_type_has_its_own_glyph(): void {
		$badge  = new StatusBadge();
		$glyphs = [];
		$names  = [];

		foreach ( StatusBadge::get_types() as $type ) {
			preg_match( '# d="([^"]+)"#', $badge->render( 'x', $type ), $m );

			$glyphs[] = $m[1] ?? '';
			$names[]  = $badge->get_icon( $type );

			$this->assertStringStartsWith( 'heroicons-outline:', $badge->get_icon( $type ) );
		}

		$this->assertSame( $glyphs, array_unique( $glyphs ), 'Two types draw the same glyph.' );
		$this->assertNotContains( '', $glyphs );
		$this->assertSame( $names, array_unique( $names ) );
		$this->assertSame( 'heroicons-outline:minus-circle', $badge->get_icon( 'nonsense' ) );
	}

	/**
	 * A status out of a database is escaped.
	 */
	public function test_a_status_is_escaped(): void {
		$html = ( new StatusBadge() )->render( '"><script>alert(1)</script>' );

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringContainsString( '&lt;b&gt;', ( new StatusBadge() )->render( 'x', null, '<b>Bold</b>' ) );
	}

	/**
	 * A forced type that is not a type is refused, for the status's own.
	 */
	public function test_a_forced_type_that_is_not_a_type_is_refused(): void {
		$html = ( new StatusBadge() )->render( 'active', '" onmouseover="alert(1)' );

		$this->assertStringNotContainsString( 'onmouseover', $html );
		$this->assertStringContainsString( 'wp-status-badge--success', $html );
	}

	/**
	 * Every mapped type is one the library can draw.
	 */
	public function test_every_mapped_type_is_a_real_type(): void {
		$this->assertSame( [], array_values( array_diff( ( new StatusBadge() )->get_map(), StatusBadge::get_types() ) ) );
	}

	/**
	 * The yes/no helpers agree with the map.
	 */
	public function test_the_type_questions(): void {
		$badge = new StatusBadge();

		$this->assertTrue( $badge->is_success( 'paid' ) );
		$this->assertTrue( $badge->is_warning( 'pending' ) );
		$this->assertTrue( $badge->is_danger( 'failed' ) );
		$this->assertTrue( $badge->is_info( 'new' ) );
		$this->assertFalse( $badge->is_success( 'failed' ) );
		$this->assertTrue( $badge->is_type( 'archived', StatusBadge::DEFAULT ) );
	}

	/**
	 * Rendering registers the stylesheet and enqueues it.
	 */
	public function test_rendering_loads_the_stylesheet(): void {
		$badge = new StatusBadge();

		$badge->render( 'active' );
		$badge->render( 'failed' );

		$registered = $GLOBALS['sb_registered'][ StatusBadge::handle() ] ?? null;

		$this->assertNotNull( $registered );
		$this->assertSame( [], $registered['deps'], 'The glyph is inline SVG: no icon font.' );
		$this->assertContains( StatusBadge::handle(), $GLOBALS['sb_enqueued'] );
	}

	/**
	 * The stylesheet's URL is worked out from where the package sits.
	 */
	public function test_the_stylesheet_url_follows_the_package(): void {
		$package = dirname( __DIR__ );

		$GLOBALS['wp_plugin_paths'] = [ WP_CONTENT_DIR . '/plugins/shop/vendor/sugarcommerce/status-badge' => $package ];

		( new StatusBadge() )->enqueue();

		$this->assertSame(
			'https://example.test/wp-content/plugins/shop/vendor/sugarcommerce/status-badge/assets/css/status-badge.css',
			$GLOBALS['sb_registered'][ StatusBadge::handle() ]['src']
		);
		$this->assertMatchesRegularExpression( '/^\d+$/', $GLOBALS['sb_registered'][ StatusBadge::handle() ]['ver'] );
	}

	/**
	 * The handle is derived from the namespace, so prefixed copies differ.
	 *
	 * Strauss renames namespaces and not strings: two plugins each bundling
	 * a copy would otherwise register one handle, and whichever loaded
	 * first would decide which CSS the other got. Checked on a copy
	 * prefixed the way Strauss does it.
	 */
	public function test_the_stylesheet_handle_is_derived_from_the_namespace(): void {
		$this->assertSame( 'sugarcommerce-status-badge', StatusBadge::handle() );

		$copy = sys_get_temp_dir() . '/status-badge-prefixed-' . bin2hex( random_bytes( 4 ) ) . '.php';

		file_put_contents(
			$copy,
			str_replace(
				[ 'namespace SugarCommerce\\', 'use SugarCommerce\\' ],
				[ 'namespace MyShop\\Vendor\\SugarCommerce\\', 'use SugarCommerce\\' ],
				(string) file_get_contents( dirname( __DIR__ ) . '/src/StatusBadge.php' )
			)
		);

		try {
			require $copy;
		} finally {
			unlink( $copy );
		}

		$this->assertSame( 'myshop-vendor-sugarcommerce-status-badge', \MyShop\Vendor\SugarCommerce\StatusBadge\StatusBadge::handle() );
	}
}
