<?php
/**
 * A coloured pill for a status, with the colours already decided.
 *
 * @package   SugarCommerce\StatusBadge
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\StatusBadge;

use SugarCommerce\Plugin\Assets;
use SugarCommerce\Text\Text;

/**
 * Maps a status to a type, and a type to a badge.
 *
 *     $badges = new StatusBadge( [ 'past_due' => 'warning' ] );
 *
 *     echo $badges->render( 'completed' );                 // green
 *     echo $badges->render( 'something', 'info' );         // one-off type
 *     echo $badges->render( 'pending', label: 'Awaiting payment' );
 *
 * Types: success, warning, danger, info, default. A status nobody mapped
 * is default (grey): an unknown word in a neutral pill reads as "a status
 * this screen does not colour", where a guess would read as a verdict.
 *
 * The markup keeps the `wp-status-badge` classes it has always had, so a
 * plugin's own stylesheet that adjusts badges keeps working.
 */
final readonly class StatusBadge {

	/**
	 * The badge types.
	 */
	public const string SUCCESS = 'success';
	public const string WARNING = 'warning';
	public const string DANGER = 'danger';
	public const string INFO = 'info';
	public const string DEFAULT = 'default';

	/**
	 * Status to type, for the statuses WordPress, commerce and membership
	 * plugins use -- Easy Digital Downloads' `complete` as well as
	 * WooCommerce's `completed`, and Stripe's `succeeded`, `past_due` and
	 * `disputed`.
	 *
	 * @var array<string, string>
	 */
	private const array DEFAULTS = [
		// Success (green).
		'active'             => self::SUCCESS,
		'approved'           => self::SUCCESS,
		'complete'           => self::SUCCESS,
		'completed'          => self::SUCCESS,
		'confirmed'          => self::SUCCESS,
		'connected'          => self::SUCCESS,
		'delivered'          => self::SUCCESS,
		'enabled'            => self::SUCCESS,
		'live'               => self::SUCCESS,
		'open'               => self::SUCCESS,
		'paid'               => self::SUCCESS,
		'processed'          => self::SUCCESS,
		'published'          => self::SUCCESS,
		'resolved'           => self::SUCCESS,
		'sent'               => self::SUCCESS,
		'succeeded'          => self::SUCCESS,
		'valid'              => self::SUCCESS,
		'verified'           => self::SUCCESS,
		'won'                => self::SUCCESS,
		'yes'                => self::SUCCESS,

		// Warning (amber).
		'awaiting'           => self::WARNING,
		'draft'              => self::WARNING,
		'expiring'           => self::WARNING,
		'failing'            => self::WARNING,
		'incomplete'         => self::WARNING,
		'needs_response'     => self::WARNING,
		'on-hold'            => self::WARNING,
		'on_hold'            => self::WARNING,
		'partially_refunded' => self::WARNING,
		'past_due'           => self::WARNING,
		'pending'            => self::WARNING,
		'preapproval'        => self::WARNING,
		'processing'         => self::WARNING,
		'requires_action'    => self::WARNING,
		'review'             => self::WARNING,
		'scheduled'          => self::WARNING,
		'trial'              => self::WARNING,
		'trialing'           => self::WARNING,
		'trialling'          => self::WARNING,
		'under_review'       => self::WARNING,
		'unpaid'             => self::WARNING,

		// Danger (red).
		'banned'             => self::DANGER,
		'blocked'            => self::DANGER,
		'bounced'            => self::DANGER,
		'cancelled'          => self::DANGER,
		'canceled'           => self::DANGER,
		'chargeback'         => self::DANGER,
		'declined'           => self::DANGER,
		'disputed'           => self::DANGER,
		'error'              => self::DANGER,
		'expired'            => self::DANGER,
		'failed'             => self::DANGER,
		'incomplete_expired' => self::DANGER,
		'invalid'            => self::DANGER,
		'lost'               => self::DANGER,
		'overdue'            => self::DANGER,
		'refunded'           => self::DANGER,
		'rejected'           => self::DANGER,
		'revoked'            => self::DANGER,
		'spam'               => self::DANGER,
		'suspended'          => self::DANGER,
		'terminated'         => self::DANGER,

		// Info (blue).
		'importing'          => self::INFO,
		'in_progress'        => self::INFO,
		'info'               => self::INFO,
		'new'                => self::INFO,
		'notice'             => self::INFO,
		'queued'             => self::INFO,
		'running'            => self::INFO,
		'syncing'            => self::INFO,
		'updated'            => self::INFO,

		// Default (grey). An abandoned cart is not a failure of the shop's.
		'abandoned'          => self::DEFAULT,
		'archived'           => self::DEFAULT,
		'closed'             => self::DEFAULT,
		'disabled'           => self::DEFAULT,
		'hidden'             => self::DEFAULT,
		'inactive'           => self::DEFAULT,
		'no'                 => self::DEFAULT,
		'none'               => self::DEFAULT,
		'paused'             => self::DEFAULT,
		'trashed'            => self::DEFAULT,
		'unknown'            => self::DEFAULT,
	];

	/**
	 * Each type's glyph: its Heroicons outline name, and the path drawn.
	 *
	 * Drawn here rather than by an icon library, because a badge is a word
	 * and a dot, and pulling an icon set into every plugin that shows a
	 * status would be a lot to carry for a glyph the stylesheet hides. Each
	 * type has a different shape, so the type never depends on colour alone
	 * for anybody who shows the glyphs.
	 *
	 * Heroicons, MIT licence, https://heroicons.com.
	 *
	 * @var array<string, array{0: string, 1: string}>
	 */
	private const array ICONS = [
		self::SUCCESS => [ 'heroicons-outline:check-circle', 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' ],
		self::WARNING => [ 'heroicons-outline:clock', 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' ],
		self::DANGER  => [ 'heroicons-outline:x-circle', 'm9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' ],
		self::INFO    => [ 'heroicons-outline:information-circle', 'm11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z' ],
		self::DEFAULT => [ 'heroicons-outline:minus-circle', 'M15 12H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' ],
	];

	/**
	 * The stylesheet, from the package root.
	 */
	private const string STYLESHEET = 'css/status-badge.css';

	/**
	 * Status to type: the defaults with the caller's over them.
	 *
	 * @var array<string, string>
	 */
	private array $map;

	/**
	 * Whether a badge draws its glyph in place of the dot.
	 *
	 * @var bool
	 */
	private bool $icons;

	/**
	 * Set up a badge renderer.
	 *
	 * One per plugin is plenty: it holds the map, and building a new one
	 * per row rebuilds the map for every cell in a column.
	 *
	 * @param array<string, string> $custom Status to type, merged over the defaults. A
	 *                                      type that does not exist is dropped.
	 * @param bool                  $icons  Draw each badge's glyph in place of its dot
	 *                                      (class `wp-status-badge--icon`). Never both:
	 *                                      two marks before one word read as two things.
	 */
	public function __construct( array $custom = [], bool $icons = false ) {
		$custom      = array_change_key_case( $custom, CASE_LOWER );
		$this->icons = $icons;

		$this->map = array_merge(
			self::DEFAULTS,
			array_filter( $custom, static fn( $type ): bool => in_array( $type, self::get_types(), true ) )
		);
	}

	/**
	 * A status badge.
	 *
	 * @param string      $status The status (`completed`, `on-hold`).
	 * @param string|null $type   A type for this badge only. One that does not exist is ignored.
	 * @param string|null $label  What it says. The status, made readable, by default.
	 *
	 * @return string
	 */
	public function render( string $status, ?string $type = null, ?string $label = null ): string {
		$this->enqueue();

		// A type the caller named has to be one that exists. It goes into a
		// class attribute and into an icon lookup, and an unrecognised one
		// gives a badge with no colour -- which reads as a status this
		// library does not know rather than as a typo in the call, so the
		// mistake gets blamed on the data.
		$type = null !== $type && in_array( $type, self::get_types(), true ) ? $type : $this->get_type( $status );

		return sprintf(
			'<span class="wp-status-badge wp-status-badge--%s%s">%s%s</span>',
			esc_attr( $type ),
			$this->icons ? ' wp-status-badge--icon' : '',
			self::glyph( $type ),
			esc_html( $label ?? self::format_label( $status ) )
		);
	}

	/**
	 * Load the stylesheet.
	 *
	 * render() does this itself. Call it early to have the stylesheet in
	 * the head, or on a screen that draws badges in a script.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		$handle = self::handle();

		if ( ! wp_style_is( $handle, 'registered' ) ) {
			Assets::register_style( $handle, __FILE__, self::STYLESHEET );
		}

		wp_enqueue_style( $handle );
	}

	/**
	 * The stylesheet handle for this copy of the library.
	 *
	 * Derived from the namespace rather than written as a literal, because
	 * Strauss renames namespaces and does not rename strings. Two plugins
	 * each bundling a prefixed copy would otherwise both register one
	 * handle; the second registration is a silent no-op, so whichever
	 * plugin loaded first decides which version of the CSS the other gets.
	 *
	 * @return string `sugarcommerce-status-badge`, or e.g.
	 *                `myplugin-vendor-sugarcommerce-status-badge` once prefixed.
	 */
	public static function handle(): string {
		$root = substr( __NAMESPACE__, 0, (int) strrpos( __NAMESPACE__, '\\' ) );

		return trim( strtolower( (string) preg_replace( '/[^A-Za-z0-9]+/', '-', $root ) ), '-' ) . '-status-badge';
	}

	/**
	 * A status's type.
	 *
	 * Matched without regard to case or surrounding space, and a hyphen
	 * matches an underscore.
	 *
	 * @param string $status The status.
	 *
	 * @return string One of get_types(); default for a status nobody mapped.
	 */
	public function get_type( string $status ): string {
		$status = strtolower( trim( $status ) );

		// `past-due` is `past_due`: plugins differ only in the joining mark.
		return $this->map[ $status ] ?? $this->map[ str_replace( '-', '_', $status ) ] ?? self::DEFAULT;
	}

	/**
	 * A type's glyph, as `set:name`.
	 *
	 * For a caller drawing the same glyph somewhere else with an icon
	 * library of its own.
	 *
	 * @param string $type The type.
	 *
	 * @return string e.g. `heroicons-outline:check-circle`.
	 */
	public function get_icon( string $type ): string {
		return ( self::ICONS[ $type ] ?? self::ICONS[ self::DEFAULT ] )[0];
	}

	/**
	 * Whether a status is of a type.
	 *
	 * @param string $status The status.
	 * @param string $type   The type.
	 *
	 * @return bool
	 */
	public function is_type( string $status, string $type ): bool {
		return $this->get_type( $status ) === $type;
	}

	/**
	 * Whether a status is a success.
	 *
	 * @param string $status The status.
	 *
	 * @return bool
	 */
	public function is_success( string $status ): bool {
		return $this->is_type( $status, self::SUCCESS );
	}

	/**
	 * Whether a status is a warning.
	 *
	 * @param string $status The status.
	 *
	 * @return bool
	 */
	public function is_warning( string $status ): bool {
		return $this->is_type( $status, self::WARNING );
	}

	/**
	 * Whether a status is a danger.
	 *
	 * @param string $status The status.
	 *
	 * @return bool
	 */
	public function is_danger( string $status ): bool {
		return $this->is_type( $status, self::DANGER );
	}

	/**
	 * Whether a status is information.
	 *
	 * @param string $status The status.
	 *
	 * @return bool
	 */
	public function is_info( string $status ): bool {
		return $this->is_type( $status, self::INFO );
	}

	/**
	 * Every status this renderer knows, with its type.
	 *
	 * @return array<string, string>
	 */
	public function get_map(): array {
		return $this->map;
	}

	/**
	 * The badge types.
	 *
	 * @return string[]
	 */
	public static function get_types(): array {
		return [ self::SUCCESS, self::WARNING, self::DANGER, self::INFO, self::DEFAULT ];
	}

	/**
	 * A machine status as a label: `on-hold` is "On hold".
	 *
	 * Sentence case, as WordPress writes its own statuses ("Pending
	 * review"). A real label, translated, is always better; this is what
	 * stands in.
	 *
	 * @param string $status The status.
	 *
	 * @return string
	 */
	public static function format_label( string $status ): string {
		return Text::humanize( $status );
	}

	/**
	 * A type's glyph, as inline SVG.
	 *
	 * In the markup but hidden by the stylesheet: the dot is the mark. A
	 * renderer made with `icons: true` shows it instead of the dot.
	 *
	 * @param string $type The type.
	 *
	 * @return string
	 */
	private static function glyph( string $type ): string {
		return sprintf(
			'<svg class="wp-status-badge__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="%s"/></svg>',
			esc_attr( ( self::ICONS[ $type ] ?? self::ICONS[ self::DEFAULT ] )[1] )
		);
	}
}
