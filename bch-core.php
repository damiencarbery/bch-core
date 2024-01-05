<?php
/*
Plugin Name: BlanchCentreHistory.com
Plugin URI: https://www.damiencarbery.com
Description: Theme independent code for BlanchCentreHistory.com.
Author: Damien Carbery
Version: 0.2
*/


// Tweaks when using a Genesis theme.
add_action('genesis_setup','child_theme_setup', 15);
function child_theme_setup() {
  //* Add HTML5 markup structure
  //add_theme_support( 'html5' );

  //* Add viewport meta tag for mobile browsers
  add_theme_support( 'genesis-responsive-viewport' );

  //* Add support for custom background
  //add_theme_support( 'custom-background' );

  //* Add support for 3-column footer widgets
  //add_theme_support( 'genesis-footer-widgets', 3 );

  //* Enable accessibility (a11y) support. Introduced in Genesis 2.2.0.
  add_theme_support( 'genesis-accessibility', array( 'headings', 'drop-down-menu', 'search-form', 'skip-links', 'rems' ) );
  
  // Remove post info/author from under post title.
  remove_action( 'genesis_entry_header', 'genesis_post_info', 12 );
  
  // Remove the 'Filed Under' and 'Tags' info.
  remove_action( 'genesis_entry_footer', 'genesis_entry_footer_markup_open', 5 );
  remove_action( 'genesis_entry_footer', 'genesis_entry_footer_markup_close', 15 );
  remove_action( 'genesis_entry_footer', 'genesis_post_meta' );

  // Add store info after post.
  add_action('genesis_entry_content', 'bch_add_store_custom_field_info', 20);

  // Add Left/Right unit to tag archive.
  add_action( 'genesis_before_while', 'bch_left_right_units' );
}


// Load CSS from plugin.
add_action( 'wp_enqueue_scripts', 'bch_enqueue_local_styles' );
function bch_enqueue_local_styles() {
    wp_enqueue_style( 'bch', plugin_dir_url( __FILE__ ) . 'bch-core.css', array( 'genesis-sample' ) );
}


add_filter('wp_title', 'bch_taxonomy_title', 20, 3);
function bch_taxonomy_title($title, $sep, $seplocation) {
    if (is_tax()) {
		$prefix = '';
		if ( is_user_logged_in() ) {
			$prefix = '50';
		}
        return $prefix . 'History of unit '.$title;
    }
    return $title;
}


// Filter to change $ to % in query's WHERE clause.
add_filter('posts_where', 'my_posts_where');
function my_posts_where( $where ) {
	
	$where = str_replace("meta_key = 'dates_for_unit_\$_unit_number", "meta_key LIKE 'dates_for_unit_%_unit_number", $where);

	return $where;
}


// Sort tag archive by unit open date.
// See: http://www.billerickson.net/customize-the-wordpress-query/
add_action('pre_get_posts', 'bch_tag_order_by_opendate');
function bch_tag_order_by_opendate($query) {
	if ( ! is_tag() ) { return; }

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
        /*
        $query->set('orderby', 'meta_value' );*/
		
		if ( is_user_logged_in() ) {
			echo "<p>BCH_TAG query_vars:</p>";
			echo '<!-- ', var_export($query->query_vars, true), '-->';
		}
    }
}


function bch_add_store_custom_field_info() {
  if (is_single() && ('post' == get_post_type())) {
    $custom = get_post_custom( get_the_ID() );
    //$opendate = isset($custom['opendate'][0]) ? $custom['opendate'][0] : '1996-01-01';
    $closeddate = isset($custom['closeddate'][0]) ? strftime('%B %Y', strtotime($custom['closeddate'][0])) : '';
	//error_log( 'Custom for post ' . get_the_ID() . ':' . var_export( $custom, true ) );
    ?>
    <div class="shop-info">

<?php
	// TODO: Create empty arrays to note open and closed units.
	// And if there are mutiple open units (e.g. Lifestyle has multiple; Eason is in two units [on different floors]).
	$open_unit_tags = array(); // List currently open units 
	$open_unit_dates = array();  // Index by open date for sorting.
	$closed_unit_dates = array();  // Index by open date for sorting.

	$first_open_date = date( 'Y-m-d' );
	$last_close_date = '1996-01-01';

	// TODO: Change this section to use 'dates_for_unit' repeater info.
	// Maybe get earliest opening date instead of 'opendate' and latest closing date similarly.
	// Unit(s) info to come from list of currently open units.
	if ( have_rows( 'dates_for_unit' ) ) {
		// Loop through rows.
		while ( have_rows( 'dates_for_unit' ) ) {
			the_row();
			// Load sub field value.
			$unit_num = get_sub_field( 'unit_num' );  // Received term object.
			$open_date = get_sub_field( 'open_date' );
			$close_date = get_sub_field( 'close_date' );
			//error_log( sprintf( 'Unit: %d; Open: %s; Close: %s', $unit_num, $open_date, $close_date ) );

			// TODO: Store the open and close dates in the arrays above.
			if ( empty( $close_date ) ) {
				$open_unit_tags[] = $unit_num->name;
			}
			else {
				// If the store closed later when in this unit then store that date.
				$last_close_date = ( $close_date > $last_close_date ) ? $close_date : $last_close_date;
			}
			// If the store opened earlier when in this unit then store that date.
			$first_open_date = ( $open_date < $first_open_date ) ? $open_date : $first_open_date;

			// List unit/open/close info.
			if ( is_user_logged_in() ) {
				$term_link = get_term_link( $unit_num, 'unit_num' );
				if ( !is_wp_error( $term_link ) ) {
					printf( '<p class="admin-note">Unit: <a href="%s">%s</a>; Open: %s, Close: %s</p>', $term_link, $unit_num->name, $open_date, $close_date );
				}
			}
		}
	}

	// Change Unit to Units when more than one tag/unit listed.
	// This section lists the units the store currently occupies.
	$unit_text = 'Unit';
	if ( count( $open_unit_tags ) > 1 ) {
		$unit_text = 'Units';
	}
	foreach ( $open_unit_tags as $unit_num ) {
		$units[] = sprintf('<a href="%s">%s</a>', get_term_link( $unit_num, 'unit_num' ), $unit_num ); 
	}
	echo '<p>', $unit_text, ': ', implode(', ', $units), '</p>';
	
	// Show the earliest open date of the store.
?>
	<p>Opened: <strong><?php echo date( 'F Y', strtotime( $first_open_date ) ); ?></strong>
    <?php
    if (strlen($closeddate)) {
    ?>
      <br />Closed: <?php echo '<strong>', $closeddate, '</strong>'; ?></p>
    <?php
    }
    else {
        echo '</p>';
    }

	// Allow for multiple urls (as is case with Heatons/Sports World.
	if (array_key_exists('url', $custom)) {
		$urls = array();
		foreach ( $custom['url'] as $url ) {
			$urls[] = sprintf( '<strong><a href="%s">%s</a></strong>', $url, $url );
		}
		echo '<p>Web site: ', join( ' &amp; ', $urls ), '</p>';
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
			$open_date = date( 'F Y', strtotime( $dates[ 'open_date' ] ) );
			if ( ! empty( $dates[ 'close_date' ] ) ) {
				$close_date = date( 'F Y', strtotime( $dates[ 'close_date' ] ) );
				$store_history[] = sprintf('<li><a href="%s">%s</a> (%s - %s)</li>', get_term_link( $dates[ 'unit_number' ], 'unit_num' ), $dates[ 'unit_number' ], $open_date, $close_date );
				$this_unit_date_strs[ $dates[ 'unit_number' ] ] = sprintf('(%s - %s)</li>', $open_date, $close_date );
			}
			else {
				$store_history[] = sprintf('<li><a href="%s">%s</a> (since %s)</li>', get_term_link( $dates[ 'unit_number' ], 'unit_num' ), $dates[ 'unit_number' ], $open_date );
				$this_unit_date_strs[ $dates[ 'unit_number' ] ] = sprintf('(since %s)</li>', $open_date );
			}
		}

		echo implode( '', array_reverse( $store_history ) );
		echo '</ul>';
	}
	else {
		echo '<p>Sorry, there is no history for this store - the old data has not yet been converted to the new format.</p>';
	}

echo '<pre>', var_export( $this_unit_date_strs, true ), '</pre>';
	// List the history for the unit.
	if ( $dates_for_unit ) {
		global $wpdb;
		
		foreach ( $open_unit_tags as $unit_num ) {
			// ToDo: Explain the SQL.
			$sql = $wpdb->prepare( "SELECT {$wpdb->prefix}postmeta.`post_id` FROM {$wpdb->prefix}postmeta INNER JOIN {$wpdb->prefix}posts WHERE {$wpdb->prefix}postmeta.`meta_key` REGEXP 'dates_for_unit_[[:digit:]]+_unit_number' AND {$wpdb->prefix}postmeta.`meta_value` = '%d' AND {$wpdb->prefix}posts.`ID`={$wpdb->prefix}postmeta.`post_id` AND {$wpdb->prefix}posts.`post_status`='publish'", $unit_num );
			
			$stores_in_unit = $wpdb->get_col( $sql );
			if ( $stores_in_unit ) {
				printf( '<h2>History of <a href="%s">unit %d</a></h2>', get_term_link( $unit_num, 'unit_num' ), $unit_num );
				echo '<ul>';
				foreach ( $stores_in_unit as $unit_id ) {
					$date_range = '(TBD)';
// ToDo: This check is not working as expected.
					if ( array_key_exists( $unit_id, $this_unit_date_strs ) ) {
						$date_range = $this_unit_date_strs[ $unit_id ];
					}
					printf( '<li><a href="%s">%s</a> (post id: %d) %s</li>', get_permalink( $unit_id ), get_the_title( $unit_id ), $unit_id, $date_range );
				}
				echo '</ul>';
			}
			//echo '<pre>Unit num: ', $unit_num, "\n", var_export( $stores_in_unit, true ), '</pre>';
		}

	}

// TODO: Rewrite this section to use 'unit_num' taxonomy instead of post tags.
	$posttags = get_the_tags();
	if ( $posttags ) {
		foreach($posttags as $tag) {
			if (is_numeric($tag->slug)) {
				
				printf( '<h2>History of unit <a href="/tag/%s/">%s</a> (with tags)</h2>', $tag->slug, $tag->slug );
				if ( is_user_logged_in() ) {
					echo '<p class="admin-note"><small>TODO: Sort by open or close date to avoid incorrect store order (which is based on when the store first opened).<br/>This section should only have 1 open store - rest should be closed.</small></p>';
				// See: https://staging.blanchcentrehistory.com/1996/01/sky/ - History of unit should have Sky first, not last.
				}

				// Based on code from: https://www.advancedcustomfields.com/resources/query-posts-custom-fields/
				// See example: 4. Sub custom field values
				$args = array(
					'numberposts' => -1,
					'meta_key'    => 'dates_for_unit_$_unit_number',
					'meta_value'  => $tag->slug,
					//'orderby'     => 'modified',  // Close - but still need close date sorting.
				);

				$the_query = new WP_Query( $args );

				$stores_by_date = array();
				if( $the_query->have_posts() ) {
					ob_start(); // Buffer output and drop later so that echo calls can be left in code until source control sorted.
					echo '<ul>';
					while ( $the_query->have_posts() ) {
						$the_query->the_post();
						
						echo '<li><a href="', get_permalink(), '">', get_the_title(), '</a> ';
						
						$dates_for_unit = get_field( 'dates_for_unit' );
						if ( $dates_for_unit ) {
							$unit_history = array();
							foreach ( $dates_for_unit as $dates ) {
								$open_date = date( 'F Y', strtotime( $dates[ 'open_date' ] ) );
								if ( $dates[ 'unit_number' ] == $tag->slug ) {
									if ( ! empty( $dates[ 'close_date' ] ) ) {
										$close_date = date( 'F Y', strtotime( $dates[ 'close_date' ] ) );
										//printf( '(%s - %s)', $dates[ 'open_date' ], $dates[ 'close_date' ] );
										$unit_history[] = sprintf( '(%s - %s)', $open_date, $close_date );
										$stores_by_date[ date( 'Ymd', strtotime( $dates[ 'open_date' ] ) ) ] = sprintf( '<a href="%s">%s</a> (%s - %s)', get_the_permalink(), get_the_title(), $open_date, $close_date );
									}
									else {
										//printf( '(since %s)', $dates[ 'open_date' ] );
										$unit_history[] = sprintf( '(since %s)', $open_date );
										$stores_by_date[ date( 'Ymd', strtotime( $dates[ 'open_date' ] ) ) ] = sprintf( '<a href="%s">%s</a> (since %s)', get_the_permalink(), get_the_title(), $open_date );
									}
								}
							}
							echo implode( ' &amp; ', array_reverse( $unit_history ) );
						}
						else {
							echo '(No open/close date info)';
							
						}
						
						echo '</li>';
					}
					echo '</ul>';
					ob_end_clean();  // Empty buffer (this allows echo calls to be left in the code until source control sorted).
				}

				wp_reset_query();

				if ( ! empty( $stores_by_date ) ) {
					ksort( $stores_by_date );
					echo '<ul>';
					foreach ( array_reverse( $stores_by_date ) as $date ) {
						echo '<li>', $date, '</li>';
					}
					echo '</ul>';
				}
				else {
					echo '<p>Sorry, there is no history for unit ', $tag->slug, '.</p>';
				}
			}
		}
	}
?>

    </div><!-- /.shop-info -->
    <?php
    ?>
    </div><!-- /#shop -->
    <!--<hr/>-->
	<details>
	<summary>Found an error? Let me know...</summary>
    <?php
    // Add contact form to solicit updates.
    echo do_shortcode('[ninja_form id=2]');
?>
	</details>
<?php
  } // End: if (is_single() && ('post' == get_post_type()))
}


// Add 'Closed' ribbon to posts with the 'closed' category.
// Based on: http://sridharkatakam.com/add-new-ribbon-posts-published-last-7-days-genesis/
//add_action( 'genesis_before_entry', 'sk_display_new_ribbon' );
add_action( 'genesis_entry_header', 'sk_display_new_ribbon', 1 );
function sk_display_new_ribbon() {
    /*if (!is_single()) {
        return;
    }*/
	//global $post;
    
    if (!in_category('closed')) {
        return;
    }
?>	
<div class="ribbon-wrapper-red"><div class="ribbon-red">Closed</div></div>
<?php
}


add_filter( 'genesis_breadcrumb_args', 'bch_breadcrumb_args' );
function bch_breadcrumb_args( $args ) {
	//$args['labels']['prefix'] = 'You are here: ';
	$args['labels']['category'] = 'List of units at ';
	$args['labels']['tag'] = 'History of unit ';
	//$args['labels']['date'] = 'Archives for ';
	//$args['labels']['search'] = 'Search for ';
	//$args['labels']['tax'] = 'Archives for ';
	//$args['labels']['post_type'] = 'Archives for ';
   
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
	if ( is_tax( 'unit_num' ) ) {
		$unit_num = get_queried_object();

		$left_link = '';
		$unit_left = get_field( 'unit_on_left', 'unit_num_' . $unit_num->term_id );
		if ( ! empty( $unit_left ) ) {
			$left_link = sprintf( '<a href="%s">&larr; Unit on left: %d</a>', get_term_link( $unit_left ), $unit_left->name );
		}
		$right_link = '';
		$unit_right = get_field( 'unit_on_right', 'unit_num_' . $unit_num->term_id );
		if ( ! empty( $unit_right ) ) {
			$right_link = sprintf( '<a href="%s">Unit on right: %d &rarr;</a>', get_term_link( $unit_right ), $unit_right->name );
		}
		
		if ( ! empty( $unit_left ) || ! empty( $unit_right ) ) {
?>
<div class="archive-description single-unit-num">
<div><?php echo $left_link; ?></div>
<div><?php echo $right_link; ?></div>
</div>
<?
		}
	}
}



// Change the search form text fronm 'Search this website' to 'Search for stores'.
add_filter( 'genesis_search_text', 'bch_change_search_form_text' );
function bch_change_search_form_text( $placeholder ) {
	return 'Search for stores';
}


// Do not remove the default WordPress custom fields metabox as it is used
// for some custom fields not covered by the Units repeater fields e.g. url.
add_filter( 'acf/settings/remove_wp_meta_box', '__return_false' );


add_shortcode( 'stores_alphabetically', 'bch_list_stores_alphabetically' );
function bch_list_stores_alphabetically( $atts, $content, $code ) {
	$atts = shortcode_atts( array(
			'list_closed' => false,
	  ), $atts );
	  
	$args = array( 'order' => 'ASC', 'orderby' => 'title', 'posts_per_page'=> -1 );
	$closed_cat_id = 187;
	if ( $atts[ 'list_closed' ] ) {
		$args[ 'cat' ] = $closed_cat_id;  // Only list Closed.
	}
	else {
		$args[ 'cat' ] = '-' . $closed_cat_id;  // Exclude Closed.
	}

	$query = new WP_Query( $args );
	ob_start();
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
		'public'                     => true,
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


// Disable SEO settings globally (primarily so there isn't in the Unit Numbers taxonomy pages).
add_action( 'after_setup_theme', 'bch_disable_genesis_seo', 20 );
function bch_disable_genesis_seo() {
	if ( function_exists( 'genesis_disable_seo' ) ) {
		genesis_disable_seo();
	}

	//remove_action( 'unit_num_edit_form', 'genesis_taxonomy_layout_options', 10, 2 );
}


// No Edit link at bottom of post/page.
add_filter('genesis_edit_post_link', '__return_false' );
