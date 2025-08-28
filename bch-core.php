<?php
/*
Plugin Name: BlanchCentreHistory.com
Plugin URI: https://www.damiencarbery.com
Description: Theme independent code for BlanchCentreHistory.com.
Author: Damien Carbery
Version: 0.10.20250828
*/


add_action( 'wp', 'bch_generatepress_tweaks', 20 );
function bch_generatepress_tweaks() {
	// Remove post meta (date/time, cats/tags) and post nav.
	remove_action( 'generate_after_entry_title', 'generate_post_meta' );
	remove_action( 'generate_after_entry_content', 'generate_footer_meta' );
	remove_action( 'generate_after_loop', 'generate_do_post_navigation' );

	// Add store info after post content.
	//add_action( 'generate_after_entry_content', 'bch_add_store_custom_field_info', 20 );
	add_action( 'generate_after_content', 'bch_add_store_custom_field_info', 20 );

	// Archive for unit_num taxonomy.
	add_filter( 'get_the_archive_title', 'bch_archive_title', 10, 3 );
	add_action( 'generate_before_main_content', 'bch_set_up_archive_loop' );

	// Footer.
	add_filter( 'generate_copyright', 'bch_footer_copyright' );
}


// Load CSS from plugin.
add_action( 'wp_enqueue_scripts', 'bch_enqueue_local_styles' );
function bch_enqueue_local_styles() {
	$dependant_style = 'genesis-sample';
	$active_theme = wp_get_theme();
	if ( $active_theme->exists() && 'GeneratePress' == $active_theme->get('Name') ) {
		$dependant_style = 'generate-style';
	}

    wp_enqueue_style( 'bch', plugin_dir_url( __FILE__ ) . 'bch-core.css', array( $dependant_style ) );
}


// Add favicon links. Files are in the root directory.
add_action( 'wp_head', 'bch_favicon' );
function bch_favicon() {
?>
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="manifest" href="/site.webmanifest">
<?php
}


// Set <title> when on url for history of a unit e.g. https://www.blanchcentrehistory.com/unit/111/, otherwise it is blank.
add_filter( 'document_title_parts', 'bch_unit_taxonomy_title' );
function bch_unit_taxonomy_title( $title ) {
	$unit_num = get_query_var( 'unit_num' );
	if ( $unit_num ) {
		$term = get_term_by( 'name', $unit_num, 'unit_num' );
		$title['title'] = 'History of unit ' . (empty( $term->description ) ? $term->name : $term->description);;
	}
	return $title;
}


add_filter('wp_title', 'bch_taxonomy_title', 20, 3);
function bch_taxonomy_title($title, $sep, $seplocation) {
    if (is_tax()) {
		$prefix = '';
        return $prefix . 'History of unit '.$title;
    }
    return $title;
}


// Use term description if available (primarily for WEDT == Westend Drive Thru.
function bch_archive_title( $title, $original_title, $prefix ) {
	$unit_num = get_query_var( 'unit_num' );
	if ( $unit_num ) {
		$term = get_term_by( 'name', $unit_num, 'unit_num' );
		return 'History of unit ' . (empty( $term->description ) ? $term->name : $term->description);
	}

	return $title;
}


// Filter to change $ to % in query's WHERE clause.
function dates_for_unit_sql( $where ) {
	$where = str_replace("meta_key = 'dates_for_unit_\$_unit_num", "meta_key LIKE 'dates_for_unit_%_unit_num", $where);
	//error_log( 'dates_for_unit_sql: ' . $where );

	return $where;
}


// TODO: This may not be needed if 'unit_num' taxonomy is being used, or
// may need significant rework to sort by ACF repeater field values.
// Sort tag archive by unit open date.
// See: http://www.billerickson.net/customize-the-wordpress-query/
/*add_action('pre_get_posts', 'bch_tag_order_by_opendate');
function bch_tag_order_by_opendate($query) {
	if ( ! is_tax( 'unit_num' ) ) { return; }

	if ( is_user_logged_in() ) {
		if ($query->is_main_query()) {
			echo '<p>BCH_TAG: main query</p>';
		} else {
			//echo '<p>BCH_TAG: NOT main query</p>';
		}
	}

    //if (is_admin()) { echo '<p>BCH_TAG: is_admin</p>';} else { echo '<p>BCH_TAG: NOT is_admin</p>';}

    if( $query->is_main_query() && !$query->is_feed() && !is_admin() ) {
    //if( !$query->is_feed() && !is_admin() ) {
        $query->set( 'meta_query', array(
            array(
                'key'     => 'opendate',
                'compare' => '<=',
                'type' => 'DATE',
            ),
          )
        );
        //$query->set('meta_key', 'opendate');
		$query->set('meta_key', 'closedate');
        $query->set('orderby', 'meta_value');
        $query->set('order', 'DESC');

        $query->set( 'posts_per_page', '2' );
        
        //$query->set('orderby', 'meta_value' );

		if ( is_user_logged_in() ) {
			echo '<details class="admin-note">';
			echo '<summary>BCH_TAG query_vars:</summary>';
			echo '<pre>', var_export($query->query_vars, true), '</pre>';
			echo '</details>';
		}
    }
}*/


// Given open and close dates (latter may be empty) return a string with
// the open and close dates as '(OPEN - CLOSE)'  or '(since OPEN)'. OPEN
// and CLOSE will be in 'full_month_name full_year' format.
function bch_unit_date_range( $open_date, $close_date ) {
			$open_date = date( 'F Y', strtotime( $open_date ) );
			if ( ! empty( $close_date ) ) {
				$close_date = date( 'F Y', strtotime( $close_date ) );
				return sprintf( '(%s - %s)', $open_date, $close_date );
			}
			else {

				return sprintf( '(since %s)', $open_date );
			}

}


function bch_add_store_custom_field_info() {
	if (is_single() && ('post' == get_post_type())) {
	  // May not need this - use get_post_meta( 'url', get_the_ID() ) and use later.
    $custom = get_post_custom( get_the_ID() );
    //$opendate = isset($custom['opendate'][0]) ? $custom['opendate'][0] : '1996-01-01';
	// TODO: Obsolete this, replacing it with $closed_unit_dates below.
    $closeddate = isset($custom['closeddate'][0]) ? strftime('%B %Y', strtotime($custom['closeddate'][0])) : '';
	//error_log( 'Custom for post ' . get_the_ID() . ':' . var_export( $custom, true ) );
    ?>
    <div class="shop-info">
<?php
	// TODO: Create empty arrays to note open and closed units.
	// And if there are mutiple open units (e.g. Lifestyle has multiple; Eason is in two units [on different floors]).
	$open_unit_tags = array(); // List currently open units 
	//$open_unit_dates = array();  // Index by open date for sorting.
	//$closed_unit_dates = array();  // Index by open date for sorting.

	// Initialise first/last to impossible values so that they will be overwritten by
	// valid values in later loops.
	$first_open_date = date( 'Y-m-d' );
	$last_close_date = null;

	// TODO: Change this section to use 'dates_for_unit' repeater info.
	// Maybe get earliest opening date instead of 'opendate' and latest closing date similarly.
	// Unit(s) info to come from list of currently open units.
	if ( have_rows( 'dates_for_unit' ) ) {
		// Loop through rows.
		while ( have_rows( 'dates_for_unit' ) ) {
			the_row();
			// Load sub field value.
			$unit_num = get_sub_field( 'unit_num' );  // Receives the 'unit_num' taxonomy object.
			$open_date = get_sub_field( 'open_date' );
			$close_date = get_sub_field( 'close_date' );
			//error_log( sprintf( 'Unit: %d; Open: %s; Close: %s', $unit_num, $open_date, $close_date ) );

			// Store the open units and maybe the close date.
			// ToDo: No one is unit 239 so no 'History of 239' is shown: https://staging.blanchcentrehistory.com/2014/10/loccitane/
			// May need to change the use of $open_unit_tags array there. Maybe store all units this store was in and use that.
			if ( empty( $close_date ) ) {
				// Add check whether 'Unit Dates' data is using new Unit Number (unit_num) taxonomy.
				if ( false == $unit_num && is_user_logged_in() ) {
					printf( '<p class="admin-note">Warning: New Unit Number not set. Old unit data: %d</p>', get_sub_field( 'unit_number' ) );
				}
				else {
					$open_unit_tags[] = $unit_num->name;
				}
			}
			else {
				// If the store closed later when in this unit then store that date.
				$last_close_date = ( $close_date > $last_close_date ) ? $close_date : $last_close_date;
			}
			// If the store opened earlier when in this unit then store that date.
			$first_open_date = ( $open_date < $first_open_date ) ? $open_date : $first_open_date;

			// List unit/open/close info.
			/*if ( is_user_logged_in() ) {
				$term_link = get_term_link( $unit_num, 'unit_num' );
				if ( !is_wp_error( $term_link ) ) {
					printf( '<p class="admin-note">Unit: <a href="%s">%s</a>; Open: %s, Close: %s</p>', $term_link, $unit_num->name, $open_date, $close_date );
				}
			}*/
		}
	}

	if ( false && is_user_logged_in() ) {
		echo '<details class="admin-note">';
		echo '<summary>$open_unit_tags:</summary>';
		printf( '<p>$open_unit_tags: %s</p>', var_export( $open_unit_tags, true ) );
		echo '</details>';
	}

	// This section lists the units the store currently occupies.
	// If the store is currently open then show current unit(s).
	if ( $open_unit_tags ) {
		// Change Unit to Units when more than one tag/unit listed.
		$unit_text = 'Unit';
		if ( count( $open_unit_tags ) > 1 ) {
			$unit_text = 'Units';
		}
		$units = array();

		foreach ( $open_unit_tags as $unit_num ) {
			if ( empty( $unit_num ) ) {
				$units[] = 'empty';
			}
			else {
				$units[] = sprintf('<a href="%s">%s</a>', get_term_link( $unit_num, 'unit_num' ), $unit_num ); 
			}
		}
		printf( '<p>%s: %s</p>', $unit_text, implode( ', ', $units ) );
	}
	
	// Show the earliest open date of the store. Check that there is a valid date stored.
	if ( $first_open_date != date( 'Y-m-d' ) ) {
		printf( '<p>Opened: <strong>%s</strong>', date( 'F Y', strtotime( $first_open_date ) ) );
	}
	// If the store is no longer in Blanchardstown Centre then show last closure date.
	if ( $last_close_date && empty( $open_unit_tags ) ) {
		printf( '<br />Closed: <strong>%s</strong>', date( 'F Y', strtotime( $last_close_date ) ) );
	}
	echo '</p>';

	// Allow for multiple urls (as is case with Heatons/Sports World.
	if (array_key_exists('url', $custom)) {
		$urls = array();
		foreach ( $custom['url'] as $url ) {
			$urls[] = sprintf( '<strong><a href="%s">%s</a></strong>', $url, $url );
		}
		printf( '<p>Web site: %s</p>', join( ' &amp; ', $urls ) );
	}

/*
      // SELECT `bchsg_postmeta.post_id` FROM `bchsg_postmeta` INNER JOIN `bchsg_posts` WHERE `bchsg_postmeta.meta_key` REGEXP 'bchsg_postmeta.dates_for_unit_[[:digit:]]+_unit_number' AND `bchsg_postmeta.meta_value` = '214' AND `bchsg_posts.ID`=`bchsg_postmeta.post_id` AND `bchsg_posts.post_status`='publish'

	  // Need to INNER JOIN with bchsg_posts to ensure that `ID`='214' `post_status`=='publish' results
	  SELECT bchsg_postmeta.`post_id` FROM bchsg_postmeta INNER JOIN bchsg_posts WHERE bchsg_postmeta.`meta_key` REGEXP 'dates_for_unit_[[:digit:]]+_unit_number' AND bchsg_postmeta.`meta_value` = '214' AND bchsg_posts.`ID`=bchsg_postmeta.`post_id` AND bchsg_posts.`post_status`='publish'

	Returns: 579 (Zara) and 1407 (Lego).
*/

    // Add history of this unit.
	printf( '<h2>%s history in Blanchardstown Centre</h2>', get_the_title() );
	$this_unit_date_strs = array(); // Store some strings for later.
	$dates_for_unit = get_field( 'dates_for_unit' );

	if ( $dates_for_unit ) {
		echo '<ul>';

		$store_history = array();
		foreach ( $dates_for_unit as $dates ) {
			// Add check whether 'Unit Dates' data is using new Unit Number (unit_num) taxonomy.
			if ( false == $dates[ 'unit_num' ] && is_user_logged_in() ) {
				printf( '<p class="admin-note">Warning: New Unit Number not set. Old unit data: %d</p>', $dates[ 'unit_number' ] );
			}
			else {
				$this_unit_date_strs[ $dates[ 'unit_num' ]->name ] = bch_unit_date_range( $dates[ 'open_date' ], $dates[ 'close_date' ] );
				$store_history[] = sprintf('<li><a href="%s">%s</a> %s</li>', get_term_link( $dates[ 'unit_num' ], 'unit_num' ), $dates[ 'unit_num' ]->name, bch_unit_date_range( $dates[ 'open_date' ], $dates[ 'close_date' ] ));
			}
		}

		echo implode( '', array_reverse( $store_history ) );
		echo '</ul>';
	}
	else {
		echo '<p>Sorry, there is no history for this store - the old data has not yet been converted to the new format.</p>';
	}


	// List the history for the unit.
	// ToDo: No one is unit 239 so no 'History of 239' is shown: https://staging.blanchcentrehistory.com/2014/10/loccitane/
	if ( false && is_user_logged_in() ) {
		echo '<details class="admin-note">';
		echo '<summary>$dates_for_unit:</summary>';
		printf( '<pre>$dates_for_unit:%s%s</pre>', "\n", var_export( $dates_for_unit, true ) );
		echo '</details>';
	}
	if ( $dates_for_unit ) {
		foreach ( $open_unit_tags as $unit_num ) {
			// Based on code from: https://www.advancedcustomfields.com/resources/query-posts-custom-fields/
			// See example: 4. Sub custom field values
			$term = get_term_by( 'name', $unit_num, 'unit_num' );
			$args = array(
				'numberposts' => -1,
				'meta_key'    => 'dates_for_unit_$_unit_num',
				'meta_value'  => $term->term_id, //Was: $unit_num,
				//'orderby'     => 'modified',  // Close - but still need close date sorting.
			);

			add_filter( 'posts_where', 'dates_for_unit_sql' ); // Change $ in 'dates_for_unit_$_unit_num' to % for SQL.
			$the_query = new WP_Query( $args );

			$stores_by_date = array();
			if ( $the_query->have_posts() ) {
				while ( $the_query->have_posts() ) {
					$the_query->the_post();

					$dates_for_unit = get_field( 'dates_for_unit' );
					if ( $dates_for_unit ) {
						//$unit_history = array();
						foreach ( $dates_for_unit as $dates ) {
							if ( $dates[ 'unit_num' ]->term_id == $term->term_id ) {
								//$unit_history[] = bch_unit_date_range( $dates[ 'open_date' ], $dates[ 'close_date' ] );
								$stores_by_date[ date( 'Ymd', strtotime( $dates[ 'open_date' ] ) ) ] = sprintf( '<li><a href="%s">%s</a> %s</li>', get_the_permalink(), get_the_title(), bch_unit_date_range( $dates[ 'open_date' ], $dates[ 'close_date' ] ) );
							}
						}
					}
					else {
						//echo '(No open/close date info)';
					}
				}
			}
			else {
				echo "<p>No results for meta_key query for unit $unit_num.</p>";
			}

			wp_reset_query();
			remove_filter( 'posts_where', 'dates_for_unit_sql' );

			if ( ! empty( $stores_by_date ) ) {
				printf( '<h2>History of <a href="%s">unit %s</a></h2>', get_term_link( $unit_num, 'unit_num' ), $unit_num );

				ksort( $stores_by_date );
				echo '<ul>';
				foreach ( array_reverse( $stores_by_date ) as $store_info ) {
					printf( '%s', $store_info );
				}
				echo '</ul>';
			}
			else {
				printf( '<p>Sorry, there is no history for unit %d.</p>', $unit_num );
			}
		}
	}
?>
    </div><!-- /.shop-info -->
	<details>
	<summary>Found an error? Let me know...</summary>
    <?php
    // Add contact form to solicit updates.
    echo do_shortcode('[ninja_form id=4]');  // ID=4 on localhost, ID=2 on staging.
?>
	</details>

<?php
  } // End: if (is_single() && ('post' == get_post_type()))
}


// Add 'Closed' ribbon to posts with the 'closed' category.
// Markup and CSS from: https://css-generators.com/ribbon-shapes/
// ToDo: Consider getting this from ACF repeater field instead of relying on category being set.
add_action( 'generate_before_content', 'bch_store_closed_ribbon', 1 );
function bch_store_closed_ribbon() {
    /*if (!is_single()) {
        return;
    }*/
	//global $post;

    if (!in_category('closed')) {
        return;
    }
?>	
<div class="ribbon">Closed</div>
<?php
}


// Not using breadcrumbs with GeneratePress theme - they require a third party plugin.
/*
add_filter( 'genesis_breadcrumb_args', 'bch_breadcrumb_args' );
function bch_breadcrumb_args( $args ) {
	$args['labels']['category'] = 'List of units at ';
	$args['labels']['tag'] = 'History of unit ';
   
    return $args;
}


// Tweak the breadcrum for Closed category.
add_filter( 'genesis_category_crumb', 'bch_category_crumb', 10, 2 );
function bch_category_crumb( $crumb, $args ) {
	if ( is_category( 'closed' ) ) {
		return 'List of Closed units';
	}
	return $crumb;
}
*/


add_filter('get_term_metadata', 'bch_change_category_title', 10, 4);
function bch_change_category_title( $null, $term_id, $key, $single ) {
    if ('headline' == $key) {
        $term = get_term( $term_id);
		if ( 'Closed' == $term->name ) {
			return 'Closed units';
		}
		else {
			if ( is_category() ) {
				return $term->name .' units';
			}
			else {
				return 'History of unit '.$term->name;
			}
		}
    }
    return null;
}


// Add links to unit on left and unit on right. This allows easy browsing of the site.
function bch_left_right_units() {
	if ( true || is_tax( 'unit_num' ) ) {
		//$unit_num = get_queried_object();
		$unit_num = get_term_by( 'name', get_query_var( 'unit_num' ), 'unit_num' );

		// Create left/right links as long as there is a different tag set (e.g. if unit on left == current unit then do not create a circular loop)
		$left_link = '';
		$unit_left = get_field( 'unit_on_left', 'unit_num_' . $unit_num->term_id );
		if ( ! empty( $unit_left ) && ( $unit_left->term_id != $unit_num->term_id ) ) {
			$left_link = sprintf( '<a href="%s">&larr; Unit on left: %s</a>', get_term_link( $unit_left ), $unit_left->name );
		}
		$right_link = '';
		$unit_right = get_field( 'unit_on_right', 'unit_num_' . $unit_num->term_id );
		if ( ! empty( $unit_right ) && ( $unit_right->term_id . ':' . $unit_num->term_id ) ) {
			$right_link = sprintf( '<a href="%s">Unit on right: %s &rarr;</a>', get_term_link( $unit_right ), $unit_right->name );
		}
		
		if ( ! empty( $unit_left ) || ! empty( $unit_right ) ) {
?>
<div class="archive-description single-unit-num inside-article">
<div><?php echo $left_link; ?></div>
<div><?php echo $right_link; ?></div>
</div>
<?php
		}

		if ( empty( $unit_left ) && empty( $unit_right ) ) {
?>
<div class="archive-description">
<p>Error: There is no left/right unit set for this unit.</p>
<?php
			if ( is_user_logged_in() ) {
				printf( '<p><a href="/wp-admin/term.php?taxonomy=unit_num&tag_ID=%s&post_type=post">Edit unit %s</a></p>', $unit_num->term_id, $unit_num->name );
			}
?>
</div>
<?php
		}
	}
}


// Handle custom url /unit_num/?
add_action( 'init', 'bch_unit_num_rewrite_tag' );
function bch_unit_num_rewrite_tag() {
	add_rewrite_tag( '%unit_num%', '([^&]+)');
	add_rewrite_rule('^unit_num/([^/]*)/?','index.php?unit_num=$matches[1]', 'top' );
}


// ToDo: Add pre_get_posts() - if unit_num found then change query to special 'unit_num' one.
add_action('pre_get_posts', 'bch_pre_get_posts_unit_num');
function bch_pre_get_posts_unit_num( $query ) {
	if ( get_query_var( 'unit_num' ) ) {
		if ( ! is_admin() && $query->is_main_query() && $query->is_home() ) {
			//error_log( 'unit_num query var: ' . get_query_var( 'unit_num' ) );

			$query->set( 'posts_per_page', -1 );  // Retrieve all stores.
			$query->set( 'post_status', 'publish' );  // So that 'private' are not queried when logged in.

			$query->set( 'meta_key', 'dates_for_unit_$_unit_num' );
			add_filter( 'posts_where', 'dates_for_unit_sql' ); // Change $ in 'dates_for_unit_$_unit_num' to % for SQL.

			// Use $unit_num term ID in query.
			$unit_num_term = get_term_by( 'name', get_query_var( 'unit_num' ), 'unit_num' );
			$query->set( 'meta_value', $unit_num_term->term_id );
		}
	}
}


function bch_set_up_archive_loop() {
	$unit_num = get_query_var( 'unit_num' );
	if ( $unit_num ) {
		add_filter( 'generate_has_default_loop', '__return_false' );
	}

	bch_unit_num_archive_loop();
}


// Handle the loop for /unit_num/* urls.
function bch_unit_num_archive_loop() {
	$unit_num = get_query_var( 'unit_num' );
	if ( !$unit_num ) {
		return;
	}

	generate_archive_title();

	$stores_by_date = array();

	if ( have_posts() ) {
		$unit_num_term = get_term_by( 'name', $unit_num, 'unit_num' );

		while ( have_posts() ) {
			the_post();

			$dates_for_unit = get_field( 'dates_for_unit' );
			if ( $dates_for_unit ) {
				foreach ( $dates_for_unit as $dates ) {
					if ( $dates[ 'unit_num' ]->term_id == $unit_num_term->term_id ) {
						$stores_by_date[ date( 'Ymd', strtotime( $dates[ 'open_date' ] ) ) ] = sprintf( '<li><a href="%s">%s</a> %s</li>', get_the_permalink(), get_the_title(), bch_unit_date_range( $dates[ 'open_date' ], $dates[ 'close_date' ] ) );
					}
				}
			}
		}
	}
?>
<article id="unit_num-<?php echo $unit_num; ?>" <?php generate_do_microdata( 'article' ); ?>>
	<div class="inside-article">
<?php
	if ( ! empty( $stores_by_date ) ) {
		// Show the description if the unit_num has one.
		$unit_num_term = get_term_by( 'name', $unit_num, 'unit_num' );
		if ( ! empty( $unit_num_term->description ) ) {
			echo '<p>', $unit_num_term->description, '</p>';
		}

		ksort( $stores_by_date );  // Sort by keys (which are open date).
		echo '<ul  class="unit_history">';
		foreach ( array_reverse( $stores_by_date ) as $store_info ) {
			printf( '%s', $store_info );
		}
		echo '</ul>';

	}
	else {
		printf( '<p>Sorry, there is no history for unit %d.</p>', $unit_num );
	}
?>
</div><!-- /.inside-article -->
</article>
<?php

	// Add Left/Right links after unit history list.
	bch_left_right_units();

	remove_filter( 'posts_where', 'dates_for_unit_sql' );
}


// Change footer text to add my name.
function bch_footer_copyright( $copyright ) {
	return sprintf(
			'<span class="copyright">&copy; 2013 - %1$s %2$s</span> &bull; Maintained by <a href="https://www.damiencarery.com" %3$s>Damien Carbery</a>',
			date( 'Y' ), // phpcs:ignore
			get_bloginfo( 'name' ),
			'microdata' === generate_get_schema_type() ? ' itemprop="url"' : ''
		);

}


// Do not remove the default WordPress custom fields metabox as it is used
// for some custom fields not covered by the Units repeater fields e.g. url.
add_filter( 'acf/settings/remove_wp_meta_box', '__return_false' );


// [stores_alphabetically list_closed=true list_all=true]
add_shortcode( 'stores_alphabetically', 'bch_list_stores_alphabetically' );
function bch_list_stores_alphabetically( $atts, $content, $code ) {
	ob_start();

	$atts = shortcode_atts( array(
			'list_closed' => false,
			'list_all' => false,
	  ), $atts );
	//echo '<pre>', var_export( $atts, true ), '</pre>';

	$args = array( 'order' => 'ASC', 'orderby' => 'title', 'posts_per_page'=> -1 );

	// Can choose to only list closed or to exclude closed stores.
	$closed_cat_id = 187;
	if ( $atts[ 'list_closed' ] ) {
		$args[ 'cat' ] = $closed_cat_id;  // Only list Closed.
	}
	else {
		$args[ 'cat' ] = '-' . $closed_cat_id;  // Exclude Closed.
	}
	// Or list *all* stores.
	if ( $atts[ 'list_all' ] ) {
		unset( $args[ 'cat' ] );
	}
	//echo '<pre>', var_export( $args, true ), '</pre>';

	$query = new WP_Query( $args );
	if ( $query->have_posts() ) {
		$i = 0;
		echo '<div class="stores-list">';
		while ( $query->have_posts() ) {
			$query->the_post();
			printf( '<div><a href="%s">%s</a></div>', get_permalink(), get_the_title() );
			$i++;
		}
		echo '</div>';
	} else {
		// No posts found, return nothing.
		ob_end_clean();
		return '';
	}
	return '<p>' . $i . ' stores.</p>' . ob_get_clean();
}


// List stores that do not have new format data.
add_shortcode( 'updates_needed', 'bch_updates_needed_shortcode' );
function bch_updates_needed_shortcode() {
	$output = '';
	//return '<p>[updates_needed] shortcode.</p>';
	//'meta_query' => array( 'key' =>'dates_for_unit', 'compare' => 'NOT EXISTS' );
    // WP_Query arguments
    $args = array (
		'order' => 'ASC',
		'posts_per_page' => -1,
		'meta_key' => 'dates_for_unit',
		'meta_compare' => 'NOT EXISTS',
    );
    // The Query
    $query = new WP_Query( $args );

    // The Loop
	$i = 0;
    $data = '';
    if ( $query->have_posts() ) {
		$data = '<ul>';
        while ( $query->have_posts() ) {
            $query->the_post();

            $data .= sprintf('<li><a href="%s">%s</a> (<a href="%s" target="_blank">Edit</a>)</li>%s', get_permalink(), get_the_title(), get_edit_post_link(), "\n" );
			$i++;
        }
		$data .= '</ul>';
    } else {
        // no posts found
    }

    // Restore original post data.
    wp_reset_postdata();

	$output = sprintf( '<h2>These %d stores do not have historical data.</h2>', $i ) . $data;

	// Another query for stores that have dates_for_unit but use unit_number instead of the unit_num taxonomy.
    $args = array (
		'order' => 'ASC',
		'posts_per_page' => -1,
		'meta_key' => 'dates_for_unit_0_unit_num',
		'meta_value' => '0',  // This meta_value/meta_compare does not make sense - I am looking for empty values.
		'meta_compare' => '<',
    );
    // The Query
    $query = new WP_Query( $args );

    // The Loop
	$i = 0;
    $data = '';
    if ( $query->have_posts() ) {
		$data = '<ul>';
        while ( $query->have_posts() ) {
            $query->the_post();

            $data .= sprintf('<li><a href="%s">%s</a> (<a href="%s" target="_blank">Edit</a>)</li>%s', get_permalink(), get_the_title(), get_edit_post_link(), "\n" );
			$i++;
        }
		$data .= '</ul>';
    } else {
        // no posts found
    }

    // Restore original post data.
    wp_reset_postdata();
	return $output . sprintf( '<h2>These %d stores do not use unit_num taxonomy.</h2>', $i ) . $data;
}


/*
// Display stores with the specified tag.
add_shortcode('tag_query', 'bch_tag_query_shortcode');
function bch_tag_query_shortcode() {
    // WP_Query arguments
    $args = array (
    'tag'                    => '101',
	'order'                  => 'ASC',
    );
    // The Query
    $query = new WP_Query( $args );

    // The Loop
    $data = '';
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();

            $data .= sprintf('<p>Post ID: <a href="%s">%s</a></p>', get_permalink(), get_the_title());
            // do something
        }
    } else {
        // no posts found
    }

    // Restore original Post Data
    wp_reset_postdata();

    return $data;
}*/


// Register Custom Taxonomy for the Unit numbers.
add_action( 'init', 'create_unit_num_taxonomy', 0 );
function create_unit_num_taxonomy() {

	$labels = array(
		'name'                       => 'Units',
		'singular_name'              => 'Unit',
		'menu_name'                  => 'Unit numbers',
		'all_items'                  => 'All units',
		'parent_item'                => 'Parent Unit',
		'parent_item_colon'          => 'Parent Unit:',
		'new_item_name'              => 'New Unit',
		'add_new_item'               => 'Add Unit',
		'edit_item'                  => 'Edit Unit',
		'update_item'                => 'Update Unit',
		'view_item'                  => 'View Unit',
		'separate_items_with_commas' => 'Separate items with commas',
		'add_or_remove_items'        => 'Add or remove units',
		'choose_from_most_used'      => 'Choose from the most used',
		'popular_items'              => 'Popular Units',
		'search_items'               => 'Search Units',
		'not_found'                  => 'Not Found',
		'no_terms'                   => 'No units',
		'items_list'                 => 'Units list',
		'items_list_navigation'      => 'Units list navigation',
	);
	$rewrite = array(
		'slug'                       => 'unit',
		'with_front'                 => true,
		'hierarchical'               => false,
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => false,
		'public'                     => false,  // Disable archive. It will be handled with custom rewrite rules.
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => false,
		'show_tagcloud'              => false,
		'rewrite'                    => $rewrite,
		'show_in_rest'               => false,
	);
	register_taxonomy( 'unit_num', array( 'post' ), $args );

}


// Add 'Unit on left'/'Unit on right' columns to admin area.
add_filter( 'manage_edit-unit_num_columns', 'unit_num_add_columns', 20 );
function unit_num_add_columns( $columns ) {
	//global $taxonomy;
	//error_log( 'Taxonomy: ' . var_export( $taxonomy, true ) );

	$insert_after = 'name';

	$position = array_search( $insert_after, array_keys( $columns ) );
		if ( false !== $position ) {
			$before = $columns;
			$after = $columns;
			array_splice( $before, $position + 1 );
			array_splice( $after, 0, $position + 1 );

			// Add new columns.
			$before[ 'unit_on_left' ] = 'Unit on left';
			$before[ 'unit_on_right' ] = 'Unit on right';

			// Append the $after columns.
			$columns = array_merge( $before, $after );
		}

	
	unset( $columns[ 'slug' ] );
	unset( $columns[ 'description' ] );
	
	//error_log( 'Columns: ' . var_export( $columns, true ) );
	return $columns;
}


add_action( 'manage_unit_num_custom_column' , 'unit_num_add_column_data', 10, 3 );
function unit_num_add_column_data( $unused, $column, $term_id ) {
	// Use 'unit_num_' prefix to access taxonomy data: https://www.advancedcustomfields.com/resources/adding-fields-taxonomy-term/#notes
	switch ( $column ) {
		case 'unit_on_left':
		case 'unit_on_right':
			$data = get_field( $column, 'unit_num_' . $term_id );
			if ( $data ) {
				//error_log( 'Data left: ' . var_export( $data, true ) );
				// Make the data a link for easy editing (for no real reason).
				printf( '<a href="/wp-admin/term.php?taxonomy=unit_num&tag_ID=%s&post_type=post">%s</a>', $data->term_id, $data->name );
			}
			else {
				echo '-';
			}
			break;
	}
}
