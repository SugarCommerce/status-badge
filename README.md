# Status Badge

Render a coloured pill for a status, without inventing the colours each time.

## What it does

Every admin screen ends up showing statuses -- completed, pending, failed,
refunded -- and every plugin picks its own greens and reds for them, so two
plugins on one site disagree about what amber means.

This maps a status name to a type, and the type to a badge in WordPress's
own colours, each pair checked against WCAG AA for small text. You pass the
status you already have; the colour follows.

## Features

* Render a badge from a status name, with no colour decisions to make
* Get sensible defaults for the statuses WordPress and commerce plugins use
* Add your own statuses, or reassign one to a different type
* Ask whether a status is a success, warning, danger or info, for logic elsewhere
* Get a readable label from a machine status, so `on-hold` reads as "On hold"
* Override the label, or the type, for one badge

## Installation

```bash
composer require sugarcommerce/status-badge
```

## Quick start

In a list-table column:

```php
use SugarCommerce\StatusBadge\StatusBadge;

$badges = new StatusBadge( [
	'past_due'  => 'warning',
	'abandoned' => 'info',
] );

echo $badges->render( $order->status );
```

`completed` and `complete` come out green, `failed` red, `pending` amber, a status nobody
mapped grey -- without the calling code knowing which is which. Keep one
renderer per plugin: it holds the map.

## The rest

```php
$badges->render( 'pending', label: __( 'Awaiting payment', 'my-plugin' ) ); // your words
$badges->render( 'risk_high', 'danger', __( 'High', 'my-plugin' ) );         // a type for one badge
$badges->get_type( 'Refunded ' );   // 'danger': case and spaces do not matter, nor - against _
$badges->is_danger( $status );      // also is_success(), is_warning(), is_info(), is_type()
$badges->get_map();                 // every status it knows
$badges->enqueue();                 // the stylesheet early, for badges drawn by a script
StatusBadge::format_label( 'partially_refunded' ); // 'Partially refunded'
```

Types are `success`, `warning`, `danger`, `info` and `default`. A type that
does not exist -- in the map or for one badge -- is ignored rather than
drawn without a colour.

The markup is `<span class="wp-status-badge wp-status-badge--{type}">`, a
glyph and the word. The glyph is a Heroicons outline icon drawn as inline
SVG (`aria-hidden`, class `wp-status-badge__icon`), different for each
type, and hidden by the stylesheet -- the dot is the mark. Ask for the
glyph instead, and it takes the dot's place (never both):

```php
$badges = new StatusBadge( $map, icons: true ); // class wp-status-badge--icon
```

`get_icon( $type )` names it (`heroicons-outline:check-circle`) for drawing
the same glyph elsewhere. The stylesheet's handle is derived from the
namespace, so two plugins bundling their own prefixed copies never share one.
Its handle (`Assets::handle()`) and URL come from `sugarcommerce/plugin`'s
`Assets`, the one asset helper of these packages.

## Security

* Script in a badge: the label and every attribute are escaped, and a type
  is one of the five known, whatever is asked for. Nothing is read from the
  request.

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
