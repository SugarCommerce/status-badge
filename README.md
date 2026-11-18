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

`completed` comes out green, `failed` red, `pending` amber, a status nobody
mapped grey -- without the calling code knowing which is which. Keep one
renderer per plugin: it holds the map.

## The rest

```php
$badges->render( 'pending', label: __( 'Awaiting payment', 'my-plugin' ) ); // your words
$badges->render( 'risk_high', 'danger', __( 'High', 'my-plugin' ) );         // a type for one badge
$badges->get_type( 'Refunded ' );   // 'danger': case and spaces do not matter
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
type, and hidden by the stylesheet -- the dot is the mark. Show it with:

```css
.wp-status-badge__icon { display: inline-block; width: 14px; height: 14px; }
```

`get_icon( $type )` names it (`heroicons-outline:check-circle`) for drawing
the same glyph elsewhere. The stylesheet's handle is derived from the
namespace, so two plugins bundling their own prefixed copies never share one.

## Moving from arraypress/wp-*

From `arraypress/wp-status-badge` (`ArrayPress\StatusBadge`). Every method
keeps its name; it no longer needs `wp-composer-assets` or `wp-field-kit`.

| Old | New |
|---|---|
| `ArrayPress\StatusBadge\StatusBadge` | `SugarCommerce\StatusBadge\StatusBadge` (now `final`) |
| `render()`, `enqueue()`, `get_type()`, `get_icon()`, `is_*()`, `get_map()`, `get_types()`, `handle()` | unchanged |
| `format_label( 'on-hold' )` = "On Hold" | "On hold": sentence case, by `sugarcommerce/text` |
| glyph from wp-field-kit, class `arraypress-icon`, none without the kit | always drawn, class `wp-status-badge__icon` |
| handle `wp-status-badge` (or `{root}-wp-status-badge` prefixed) | `sugarcommerce-status-badge` (or `{prefixed-namespace}-status-badge`) |
| a custom key in capitals never matched; a custom type that does not exist drew a colourless badge | keys matched in lower case; unknown types dropped |
| markup classes `wp-status-badge`, `wp-status-badge--{type}` | unchanged |

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
