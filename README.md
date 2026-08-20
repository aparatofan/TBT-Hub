# TBT Hub

Central admin menu and index page for all TBT plugins, and the canonical source
of the shared TBT design system.

Other plugins add themselves to the Overview page through the `tbt_hub_items`
filter, so the index always reflects what is actually installed and active.

---

## The shared design system

TBT Hub owns three stylesheet handles:

| Handle | File | Depends on |
|---|---|---|
| `tbt-tokens` | `assets/css/tbt-tokens.css` | — |
| `tbt-components` | `assets/css/tbt-components.css` | `tbt-tokens` |
| `tbt-tree` | `assets/css/tbt-tree.css` | `tbt-tokens` |

`tbt-tree` is deliberately not folded into `tbt-components`: a page that wants
the mark rarely wants the whole component library. It reads `--tbt-blue` for
the leaf stroke, which is why it depends on the tokens.

All three are **registered, never enqueued**, on `wp_enqueue_scripts` at
**priority 5**. A registered handle costs nothing on a page that does not ask
for it, so the design system reaches exactly the pages a tool renders on.
Priority 5 is what lets consumers on the default priority of 10 find the
handles already present.

### Who consumes them

Keep this table current. It is what makes the blast radius of an edit to
`tbt-tokens.css` visible from the Hub itself.

| Plugin | Handles used | Vendored fallback | Container |
|---|---|---|---|
| TBT Notes | `tbt-components` (and `tbt-tokens` through it) | `assets/vendor/tbt/` — both files | `.tbt-tool` |
| TBT Matching Games | `tbt-tokens` | `assets/vendor/tbt/` — tokens only | `.tbt-tool` |
| TBT Swipe | none — private vocabulary, private handle | n/a | `.tbt` |

TBT Swipe is deliberately outside this system. It is isolated behind its own
handle and cannot be affected by a change here. Migrating it is a separate
piece of work with open design questions.

---

## The tree mark

The animated TBT tree is inlined by the `[tbt_tree]` shortcode, so the Divi
header row and any plugin hero render one mark from one source instead of a
pasted copy per surface.

```
[tbt_tree]                             190px, blooms on load
[tbt_tree width="240px"]               wider
[tbt_tree animate="no"]                settled tree, no bloom
[tbt_tree class="tbtmg-hero__mark"]    extra host class
```

The shortcode enqueues `tbt-tree` itself and emits
`<span class="tbt-tree-host …"><svg class="tbt-tree" …>`. The SVG carries no
ids, so two marks on one page stay valid markup. The bloom is pure CSS: each
leaf carries a baked `--tbt-tree-wave` index, six waves centre outward, so
there is no script to load and no flash of an unstyled mark.

**Maintenance.** `assets/img/tbt-tree.svg` is a transformed export, not a raw
one. If the tree is re-exported from Illustrator, two steps must be re-applied
or the mark renders unstyled and unanimated:

1. Rename the export's `.cls-N` classes to the semantic ones
   `tbt-tree__leaf` (`--89` / `--81` for the two translucent variants),
   `tbt-tree__figure--le` / `--gi` / `--pd`, plus `tbt-tree__eo` on the
   `fill-rule: evenodd` paths — and strip the `<defs><style>` block, whose
   rules are document-scoped when inlined and now live in `tbt-tree.css`.
2. Re-bake `style="--tbt-tree-wave:N"` onto each leaf from its bounding-box
   distance to the canopy centre (250.2, 156.9), bucketed into six waves.

Every `id` and `data-name` is stripped as well; nothing in the file references
them.

---

## The rules

**1. TBT Hub owns `tbt-tokens` and `tbt-components`.** No other plugin may
define what those handles point at when Hub is active.

**2. A plugin may vendor a fallback copy, but only behind a `wp_style_is()`
check.** Register under the *same* handle, only when it is not already
registered:

```php
if ( ! wp_style_is( 'tbt-tokens', 'registered' ) ) {
    wp_register_style( 'tbt-tokens', $plugin_url . 'assets/vendor/tbt/tbt-tokens.css', array(), $ver );
}
```

Registering under a *different* handle would put two copies of the vocabulary
on one page. Registering unconditionally would beat Hub to its own handle.

**3. A vendored copy must stay byte-identical to the Hub original.** That is
the whole point: `diff` against this repository is then the drift check, and
it either passes or it does not. Anything plugin-specific belongs in a
`README.txt` beside the copy, not in the CSS.

**4. Never enqueue a shared handle for a file whose contents you do not
control.** If a plugin needs a token the Hub does not define, the token goes
into the Hub — or the plugin uses a private, plugin-prefixed handle for a
private file. Those are the only two options.

**5. Adding a token is for a recurring need,** not to solve one screen. Style
Book §13.

---

## Why these rules exist

TBT Swipe went down in production because `frontend.css` was served a token
file it was never written against — a different plugin had registered the
`tbt-tokens` handle first, pointing it at a vocabulary Swipe did not share.
Swipe 1.4.1 fixed the direct cause by taking its own private handle.

For the record, the plugin that had registered the handle ahead of Swipe was
**TBT Notes' fallback path**, not TBT Hub. That fallback was firing on every
request precisely because Hub — which was supposed to own the handle — shipped
no stylesheet and registered nothing at all. The ownership rule was real in the
code comments and absent from the code. Hub 1.1.0 closed that gap; rule 4 is
what stops the same shape of failure recurring.
