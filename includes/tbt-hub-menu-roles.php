<?php
add_filter( 'wp_nav_menu_objects', 'tbt_hub_filter_menu_by_role', 10, 2 );
function tbt_hub_filter_menu_by_role( $items, $args ) {
    $is_teacher = current_user_can( 'manage_tbt_notes' ); // your teacher cap
    $is_student = is_user_logged_in() && ! $is_teacher;

    $remove = array();
    foreach ( $items as $item ) {
        $c = (array) $item->classes;
        if ( in_array( 'tbt-role-teacher', $c, true ) && ! $is_teacher ) { $remove[] = (int) $item->ID; }
        if ( in_array( 'tbt-role-student', $c, true ) && ! $is_student ) { $remove[] = (int) $item->ID; }
    }
    if ( ! $remove ) { return $items; }

    do { // cascade to sub items
        $added = false;
        foreach ( $items as $item ) {
            if ( in_array( (int) $item->menu_item_parent, $remove, true )
                 && ! in_array( (int) $item->ID, $remove, true ) ) {
                $remove[] = (int) $item->ID;
                $added = true;
            }
        }
    } while ( $added );

    return array_filter( $items, function ( $i ) use ( $remove ) {
        return ! in_array( (int) $i->ID, $remove, true );
    } );
}
