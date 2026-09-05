<?php
/**
 * Plugin Name: TBT Hub
 * Description: Central admin menu and index page for all TBT plugins, and the
 *              canonical source of the shared TBT design system.
 * Version:     1.5.0
 * Author:      Mariusz Mirecki
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */

define( 'TBT_HUB_VERSION', '1.5.0' );
define( 'TBT_HUB_SLUG', 'tbt-hub' );          // other TBT plugins check for this
define( 'TBT_HUB_URL', plugin_dir_url( __FILE__ ) );
define( 'TBT_HUB_DIR', plugin_dir_path( __FILE__ ) );

/* -------------------------------------------------------------------------
 * Shared design system
 *
 * TBT-Hub owns the handles `tbt-tokens` and `tbt-components`. This is the
 * single source of truth: every other TBT plugin declares one of these as a
 * dependency rather than shipping its own copy of the vocabulary.
 *
 * Registered, never enqueued. A handle that is registered but not enqueued
 * costs nothing on a page that does not ask for it, so the design system
 * reaches exactly the pages a tool actually renders on and no others.
 * Enqueuing here instead would put the tokens on every page of the site.
 *
 * Priority 5 because consumers register and enqueue on the default priority
 * of 10 and must find these handles already present — a plugin that looks
 * first and finds nothing falls back to its own bundled copy, which is the
 * drift this ownership rule exists to prevent. See README.md.
 * ---------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', 'tbt_hub_register_shared_styles', 5 );

/**
 * Register the canonical token and component stylesheets, plus the standalone
 * `tbt-tree` and `tbt-rail` handles.
 *
 * @return void
 */
function tbt_hub_register_shared_styles() {
	wp_register_style(
		'tbt-tokens',
		TBT_HUB_URL . 'assets/css/tbt-tokens.css',
		array(),
		tbt_hub_asset_version( 'assets/css/tbt-tokens.css' )
	);

	// Components read the tokens, so the dependency is declared rather than
	// left to whatever order the consuming plugin happens to enqueue in.
	wp_register_style(
		'tbt-components',
		TBT_HUB_URL . 'assets/css/tbt-components.css',
		array( 'tbt-tokens' ),
		tbt_hub_asset_version( 'assets/css/tbt-components.css' )
	);

	// The tree mark. Reads --tbt-blue for the leaf stroke, so it depends on
	// the tokens; it is deliberately NOT part of tbt-components, because a
	// page that wants the mark rarely wants the whole component library.
	wp_register_style(
		'tbt-tree',
		TBT_HUB_URL . 'assets/css/tbt-tree.css',
		array( 'tbt-tokens' ),
		tbt_hub_asset_version( 'assets/css/tbt-tree.css' )
	);

	// The shared navigation rail. Like the tree, deliberately NOT part of
	// tbt-components: a page that renders a rail rarely wants the whole
	// component library, and TBT Swipe consumes neither. Its fallbacks make it
	// safe to enqueue without tbt-tokens, so the dependency array is empty.
	wp_register_style(
		'tbt-rail',
		TBT_HUB_URL . 'assets/css/tbt-rail.css',
		array(),
		tbt_hub_asset_version( 'assets/css/tbt-rail.css' )
	);
}

/**
 * Cache-busting version for a bundled asset.
 *
 * Uses the file's modification time so an edited stylesheet reaches browsers
 * even when TBT_HUB_VERSION was not bumped, and falls back to the plugin
 * version when the file cannot be stat'd.
 *
 * @param string $relative_path Path relative to the plugin directory.
 * @return string
 */
function tbt_hub_asset_version( $relative_path ) {
	$mtime = @filemtime( TBT_HUB_DIR . $relative_path );

	return $mtime ? (string) $mtime : TBT_HUB_VERSION;
}

/* -------------------------------------------------------------------------
 * The tree mark — [tbt_tree]
 *
 * One source for the animated TBT tree, used by the Divi header row and by
 * any plugin that renders its own hero. Before this existed the mark was
 * pasted into a Divi Code Module and would have had to be pasted again into
 * every plugin template that wanted it.
 *
 * The SVG is inlined rather than referenced with <img> because the bloom
 * animates individual leaves, which is impossible across an <img> boundary.
 *
 * The bloom is pure CSS. Each leaf carries a baked `--tbt-tree-wave` index,
 * computed once from the geometry, so there is no script to load, nothing to
 * fail, and no flash of an unstyled mark.
 * ---------------------------------------------------------------------- */

add_shortcode( 'tbt_tree', 'tbt_hub_tree_shortcode' );

/**
 * Render the TBT tree mark.
 *
 * Attributes:
 *   width   CSS length for the rendered mark. Default 190px.
 *   animate 'yes' (default) blooms on load; 'no' renders the settled tree.
 *   class   Extra classes for the host element.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function tbt_hub_tree_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'width'   => '190px',
			'animate' => 'yes',
			'class'   => '',
		),
		$atts,
		'tbt_tree'
	);

	$svg = tbt_hub_tree_svg();

	if ( '' === $svg ) {
		return '';
	}

	wp_enqueue_style( 'tbt-tree' );

	$classes = array( 'tbt-tree-host' );

	if ( 'no' !== strtolower( (string) $atts['animate'] ) ) {
		$classes[] = 'tbt-tree-host--animate';
	}

	if ( '' !== trim( (string) $atts['class'] ) ) {
		$classes[] = sanitize_html_class( trim( (string) $atts['class'] ) );
	}

	// safecss_filter_attr() rejects anything that is not a plain declaration,
	// so a width attribute cannot smuggle markup or a url() into the page.
	$style = safecss_filter_attr( 'width:' . $atts['width'] );

	return sprintf(
		'<span class="%1$s"%2$s>%3$s</span>',
		esc_attr( implode( ' ', $classes ) ),
		$style ? ' style="' . esc_attr( $style ) . '"' : '',
		$svg
	);
}

/**
 * The tree SVG source, read once per request.
 *
 * The file is a trusted asset bundled with this plugin, so it is emitted as
 * written. It carries no ids — a page may render the mark more than once (two
 * games embedded in one lesson) and duplicate ids would be invalid markup.
 *
 * @return string SVG source, or an empty string if the asset is missing.
 */
function tbt_hub_tree_svg() {
	static $svg = null;

	if ( null !== $svg ) {
		return $svg;
	}

	$path = TBT_HUB_DIR . 'assets/img/tbt-tree.svg';
	$svg  = is_readable( $path ) ? (string) file_get_contents( $path ) : '';

	return $svg;
}

/* -------------------------------------------------------------------------
 * Owner-only capability
 *
 * Grants a virtual `tbt_owner` capability to the owner account and strips it
 * from everyone else. Nothing is written to the database, so this cannot be
 * corrupted by role-editing plugins and cannot lock you out.
 *
 * This block is guarded and kept byte-for-byte identical to the copy in TBT
 * Register (mm-register.php). Either plugin can define it standalone —
 * whichever loads first wins — so Register keeps enforcing owner-only access
 * even when TBT Hub is deactivated.
 * ---------------------------------------------------------------------- */

if ( ! defined( 'TBT_OWNER_EMAIL' ) ) {
	define( 'TBT_OWNER_EMAIL', 'mariuszmirecki@gmail.com' );
}

if ( ! function_exists( 'tbt_is_owner' ) ) {
	/**
	 * Grant a virtual `tbt_owner` capability to the owner account and strip it
	 * from everyone else.
	 *
	 * @param array   $allcaps All capabilities of the user.
	 * @param array   $caps    Required capabilities being checked.
	 * @param array   $args    Arguments passed to the check.
	 * @param WP_User $user    The user object.
	 * @return array
	 */
	function tbt_hub_grant_owner_cap( $allcaps, $caps, $args, $user ) {
		$email = isset( $user->user_email ) ? strtolower( $user->user_email ) : '';

		if ( $email && $email === strtolower( TBT_OWNER_EMAIL ) ) {
			$allcaps['tbt_owner'] = true;
		} else {
			unset( $allcaps['tbt_owner'] );
		}

		return $allcaps;
	}
	add_filter( 'user_has_cap', 'tbt_hub_grant_owner_cap', 10, 4 );

	/**
	 * Convenience wrapper for use inside TBT plugins (menus, AJAX, REST).
	 */
	function tbt_is_owner() {
		return current_user_can( 'tbt_owner' );
	}
}

/* -------------------------------------------------------------------------
 * Shared visibility vocabulary
 *
 * TBT Hub owns the words a TBT tool uses to say whether an item is private to
 * its author or shared with other teachers. Consuming plugins must not define
 * their own visibility values: two tools that disagree about what "shared"
 * means cannot be reconciled after the fact, and a school-wide third value is
 * meant to join these two without any consumer changing a single call. That is
 * also why the values are strings and not a boolean — a boolean would force a
 * schema migration in every consuming plugin the day that third value arrives.
 *
 * `tbt_can_view_item()` is deliberately pure and storage-agnostic. It takes an
 * owner ID and a visibility value and answers a question; it never reads a
 * column, a post meta key, or a global. It has to be that way because
 * consumers store ownership differently — TBT Swipe keeps decks in custom
 * tables, TBT Matching Games keeps games as a custom post type with
 * `post_author` ownership — so each caller loads its own data and passes the
 * two values in.
 *
 * Unknown or missing values resolve to private. Existing Swipe deck rows have
 * no visibility column and existing Matching Game posts have no such post
 * meta, so both read as private without a data backfill: failing closed is the
 * point of the default, not an accident of it.
 *
 * A consumer that cannot find these functions must fall back to owner-only
 * behaviour rather than vendoring a copy. This is deliberately unlike the
 * owner-only capability block above, which is mirrored in TBT Register:
 * owner-only access is a security boundary that has to survive TBT Hub being
 * deactivated, so two copies kept in sync earn their cost there. Visibility is
 * a convenience feature — without Hub, sharing simply stops working and
 * everyone still sees their own items — so failing closed to owner-only is
 * both safe and simpler than a second copy that can drift.
 *
 * View only. This answers whether a person may look at an item, never whether
 * they may edit it, and it is not an access gate: membership and the
 * administrator "manage all" checks stay upstream in each consuming plugin.
 * ---------------------------------------------------------------------- */

if ( ! defined( 'TBT_VISIBILITY_PRIVATE' ) ) {
	define( 'TBT_VISIBILITY_PRIVATE', 'private' );
}

if ( ! defined( 'TBT_VISIBILITY_SHARED' ) ) {
	define( 'TBT_VISIBILITY_SHARED', 'shared' );
}

if ( ! function_exists( 'tbt_normalize_visibility' ) ) {
	/**
	 * Reduce any raw stored value to one of the two visibility constants.
	 *
	 * Everything that is not an exact match for `shared` — after trimming and
	 * lowercasing a string input — is private: an empty string, null, an
	 * unknown word, an array, an integer, a missing database column, an absent
	 * post meta key.
	 *
	 * @param mixed $value Raw value as read from storage.
	 * @return string TBT_VISIBILITY_SHARED or TBT_VISIBILITY_PRIVATE.
	 */
	function tbt_normalize_visibility( $value ) {
		if ( is_string( $value ) && strtolower( trim( $value ) ) === TBT_VISIBILITY_SHARED ) {
			return TBT_VISIBILITY_SHARED;
		}

		return TBT_VISIBILITY_PRIVATE;
	}
}

if ( ! function_exists( 'tbt_can_view_item' ) ) {
	/**
	 * Answer whether one teacher may view another teacher's item.
	 *
	 * Pure: the caller loads the owner and the visibility from wherever it
	 * stores them and passes both in. No database access, no `get_post`, no
	 * globals beyond resolving the current user when no viewer is given.
	 *
	 * @param int      $owner_id   ID of the user who owns the item.
	 * @param mixed    $visibility Raw visibility value from storage.
	 * @param int|null $viewer_id  Viewer's user ID, or null for the current user.
	 * @return bool
	 */
	function tbt_can_view_item( $owner_id, $visibility, $viewer_id = null ) {
		$owner_id = (int) $owner_id;

		// An unowned item belongs to nobody, so nobody may view it.
		if ( $owner_id <= 0 ) {
			return false;
		}

		if ( null === $viewer_id ) {
			$viewer_id = get_current_user_id();
		}

		$viewer_id = (int) $viewer_id;

		// Logged-out visitors see nothing through this helper.
		if ( $viewer_id <= 0 ) {
			return false;
		}

		// Owners always see their own items, whatever the visibility says.
		if ( $viewer_id === $owner_id ) {
			return true;
		}

		return tbt_normalize_visibility( $visibility ) === TBT_VISIBILITY_SHARED;
	}
}

/* -------------------------------------------------------------------------
 * Audience
 *
 * One place that answers "is this person a teacher?" for the whole TBT suite.
 * Front-end menu visibility is the first consumer; anything else that needs
 * the same answer should call this rather than repeating the test.
 *
 * The owner is always a teacher, so the menu stays correct even if the
 * capability below is ever renamed or its owning plugin is deactivated.
 *
 * `manage_tbt_notes` is the test because it is the capability teachers have
 * and students do not. If TBT Notes computes that capability virtually — the
 * way the owner block above computes `tbt_owner` — then deactivating TBT
 * Notes would make every teacher except the owner read as a student here.
 * Should that become a problem, replace the capability check with a Hub-owned
 * list of teacher user IDs; this function is the only thing that changes.
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'tbt_hub_is_teacher' ) ) {
	/**
	 * Whether the current user is a TBT teacher.
	 *
	 * @return bool
	 */
	function tbt_hub_is_teacher() {
		if ( function_exists( 'tbt_is_owner' ) && tbt_is_owner() ) {
			return true;
		}

		return current_user_can( 'manage_tbt_notes' );
	}
}

/* -------------------------------------------------------------------------
 * Front-end menu visibility by role
 *
 * "Teacher Tools" and "Student tools" occupy the same slot in the primary
 * menu and are meant for different audiences. Rather than maintaining two
 * whole menus and swapping the menu location, each parent item carries a CSS
 * class in Appearance → Menus and this filter drops whichever one does not
 * apply, along with its children.
 *
 * Tag the parents with:
 *   tbt-role-teacher   visible to teachers only
 *   tbt-role-student   visible to logged-in non-teachers only
 *
 * Children need no class — removal cascades down the subtree. Items carrying
 * neither class are never touched, so the rest of the menu is unaffected.
 *
 * `wp_nav_menu_objects` runs for every menu on the site, which is why this is
 * one filter rather than one per location: Divi's mobile menu, the footer and
 * any future menu all get the same treatment for free.
 *
 * Logged-out visitors match neither test, so both items disappear for them.
 * If a public-facing entry is ever wanted in that slot, leave it untagged.
 *
 * Note for page caching: any cache layer must exclude logged-in users, or a
 * student can be served a teacher's cached menu.
 * ---------------------------------------------------------------------- */

add_filter( 'wp_nav_menu_objects', 'tbt_hub_filter_menu_by_role', 10, 2 );

/**
 * Remove role-tagged menu items that do not apply to the current viewer.
 *
 * @param array  $items Menu item objects.
 * @param object $args  wp_nav_menu() arguments.
 * @return array
 */
function tbt_hub_filter_menu_by_role( $items, $args ) {
	$is_teacher = tbt_hub_is_teacher();
	$is_student = is_user_logged_in() && ! $is_teacher;

	$remove = array();

	foreach ( $items as $item ) {
		$classes = (array) $item->classes;

		if ( in_array( 'tbt-role-teacher', $classes, true ) && ! $is_teacher ) {
			$remove[] = (int) $item->ID;
		}

		if ( in_array( 'tbt-role-student', $classes, true ) && ! $is_student ) {
			$remove[] = (int) $item->ID;
		}
	}

	// Nothing tagged on this menu, or everything tagged applies: leave the
	// array untouched rather than rebuilding it.
	if ( empty( $remove ) ) {
		return $items;
	}

	// A removed parent takes its whole subtree with it. The loop repeats until
	// a pass finds nothing new, so nesting deeper than one level still works
	// and the order items happen to appear in does not matter.
	do {
		$added = false;

		foreach ( $items as $item ) {
			if ( in_array( (int) $item->menu_item_parent, $remove, true )
				&& ! in_array( (int) $item->ID, $remove, true ) ) {
				$remove[] = (int) $item->ID;
				$added    = true;
			}
		}
	} while ( $added );

	// Reindexed because some menu walkers assume a sequential array.
	return array_values(
		array_filter(
			$items,
			function ( $item ) use ( $remove ) {
				return ! in_array( (int) $item->ID, $remove, true );
			}
		)
	);
}

/* -------------------------------------------------------------------------
 * Menu
 *
 * Priority 9 so the parent exists before other TBT plugins register their
 * submenus on the default priority of 10.
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'tbt_hub_register_menu', 9 );
function tbt_hub_register_menu() {
	// The parent menu deliberately uses `edit_posts`, not `manage_options`:
	// WordPress hides a parent menu from anyone who lacks the parent's own
	// capability, so a `manage_options` parent would hide the editor-level
	// tools (TBT Comprehension / TBT Tooltip) from any future editor account.
	// Each submenu still carries and enforces its own capability.
	add_menu_page(
		'TBT',
		'TBT',
		'edit_posts',
		TBT_HUB_SLUG,
		'tbt_hub_render_page',
		'dashicons-welcome-learn-more',
		3 // between Dashboard (2) and the first separator (4)
	);

	// Explicit submenu entry so the auto-generated duplicate is replaced
	// and we control its label.
	add_submenu_page(
		TBT_HUB_SLUG,
		'TBT Overview',
		'Overview',
		'edit_posts',
		TBT_HUB_SLUG,
		'tbt_hub_render_page'
	);
}

/* -------------------------------------------------------------------------
 * Submenu ordering
 *
 * WordPress orders submenus by registration order, which depends on plugin
 * load order. This pins Overview and Register to the top and sorts the rest
 * alphabetically, regardless of load order.
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'tbt_hub_order_submenu', 999 );
function tbt_hub_order_submenu() {
	global $submenu;

	if ( empty( $submenu[ TBT_HUB_SLUG ] ) ) {
		return;
	}

	// TBT Register keeps its own top-level menu, but if its slug ever appears
	// among the hub's submenus it should still pin to the top. Its real menu
	// slug is `mmr-calendar`.
	$pinned = array( TBT_HUB_SLUG, 'mmr-calendar' );

	usort(
		$submenu[ TBT_HUB_SLUG ],
		function ( $a, $b ) use ( $pinned ) {
			$a_pin = array_search( $a[2], $pinned, true );
			$b_pin = array_search( $b[2], $pinned, true );

			if ( false !== $a_pin && false !== $b_pin ) {
				return $a_pin <=> $b_pin;
			}
			if ( false !== $a_pin ) {
				return -1;
			}
			if ( false !== $b_pin ) {
				return 1;
			}

			return strcasecmp(
				wp_strip_all_tags( $a[0] ),
				wp_strip_all_tags( $b[0] )
			);
		}
	);

	$submenu[ TBT_HUB_SLUG ] = array_values( $submenu[ TBT_HUB_SLUG ] );
}

/* -------------------------------------------------------------------------
 * Registry
 *
 * Each TBT plugin adds itself to this list via the `tbt_hub_items` filter, so
 * the Overview page always reflects what is actually installed and active.
 *
 * Item shape:
 *   'slug'        => menu slug used in add_submenu_page()
 *   'title'       => display name
 *   'description' => one line: what it does
 *   'capability'  => cap required to see it (default 'manage_options')
 * ---------------------------------------------------------------------- */

function tbt_hub_get_items() {
	$items = apply_filters( 'tbt_hub_items', array() );

	$items = array_filter(
		$items,
		function ( $item ) {
			$cap = isset( $item['capability'] ) ? $item['capability'] : 'manage_options';
			return current_user_can( $cap );
		}
	);

	usort(
		$items,
		function ( $a, $b ) {
			// TBT Register (menu slug `mmr-calendar`) is always listed first.
			if ( 'mmr-calendar' === $a['slug'] ) {
				return -1;
			}
			if ( 'mmr-calendar' === $b['slug'] ) {
				return 1;
			}
			return strcasecmp( $a['title'], $b['title'] );
		}
	);

	return $items;
}

/* -------------------------------------------------------------------------
 * Overview page
 * ---------------------------------------------------------------------- */

function tbt_hub_render_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.' ) );
	}

	$items = tbt_hub_get_items();
	?>
	<div class="wrap">
		<h1>TBT</h1>
		<p class="description" style="font-size:14px;margin-bottom:24px;">
			Everything built for this site, in one place.
		</p>

		<?php if ( empty( $items ) ) : ?>
			<div class="notice notice-warning inline">
				<p>No TBT plugins have registered themselves yet.</p>
			</div>
		<?php else : ?>
			<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
				<?php
				foreach ( $items as $item ) :
					// A plugin may supply an explicit `url` (e.g. a custom post
					// type list at edit.php?post_type=…). Otherwise the card
					// links to the standard admin.php?page={slug} route.
					$item_url = ! empty( $item['url'] )
						? $item['url']
						: admin_url( 'admin.php?page=' . $item['slug'] );
					?>
					<div class="card" style="margin:0;padding:16px;max-width:none;">
						<h2 style="margin-top:0;font-size:16px;">
							<a href="<?php echo esc_url( $item_url ); ?>">
								<?php echo esc_html( $item['title'] ); ?>
							</a>
						</h2>
						<p style="margin-bottom:0;color:#50575e;">
							<?php echo esc_html( $item['description'] ); ?>
						</p>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
