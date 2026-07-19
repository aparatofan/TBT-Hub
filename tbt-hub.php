<?php
/**
 * Plugin Name: TBT Hub
 * Description: Central admin menu and index page for all TBT plugins.
 * Version:     1.0.0
 * Author:      Mariusz Mirecki
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */

define( 'TBT_HUB_VERSION', '1.0.0' );
define( 'TBT_HUB_SLUG', 'tbt-hub' );          // other TBT plugins check for this

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
