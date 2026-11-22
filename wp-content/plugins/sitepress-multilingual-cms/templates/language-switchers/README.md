# Language switcher templates

Each folder here is one switcher template. `template.twig` renders with the
model `WPML_LS_Model_Build::get()` builds for the switcher's slot (the footer,
a menu, a widget area, the shortcode, the post translations).

## The landmark's accessible name

Every wrapper template prints `role="navigation"` with
`aria-label="{{ ls_aria_label }}"`. Landmarks of one type on a page need unique
accessible names (WCAG), so the name is decided where the switcher PRINTS, from
one per-request list in render order
(`WPML\LanguageSwitcher\LandmarkLabels`):

- the first printed landmark keeps the plain label, `Language Switcher`;
- each later one names its slot: `Language switcher (footer)`,
  `Language switcher (menu)`, `Language switcher (widget)`,
  `Language switcher (shortcode)`;
- two switchers of one kind get an instance index on top:
  `Language switcher (widget) 2`, `Language switcher (widget) 3`, ...

Only a switcher that prints its landmark takes a name: a menu switcher (its
items join the theme's own nav), the post translations switcher (its span
carries no aria-label), a switcher hidden because there is nothing to switch
to and an empty one take none, so they never move another switcher's label.
The Language Switcher block takes its name from the same list (it does not
know its placement, so its later instances get the index), and a block whose
saved markup already carries a label keeps it — and that name is taken too.

### `wpml_ls_aria_label`

A site sets its own name per slot with the `wpml_ls_aria_label` filter:

```php
add_filter( 'wpml_ls_aria_label', function ( $label, $slot, $instance ) {
	if ( $slot && $slot->is_footer() ) {
		return 'Footer language switcher';
	}

	return $label;
}, 10, 3 );
```

- `$label` (string) — the name WPML computed (above).
- `$slot` (`WPML_LS_Slot|null`) — the switcher being rendered; `null` for the
  Language Switcher block.
- `$instance` (int) — the landmark's position among the page's printed
  switcher landmarks, in render order (1 is the first). A switcher that
  prints no landmark takes no number.
