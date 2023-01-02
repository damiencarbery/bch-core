<?php
/*
Plugin Name: BlanchCentreHistory.com
Plugin URI: https://www.damiencarbery.com
Description: Theme indenpendent code for BlanchCentreHistory.com.
Author: Damien Carbery
Version: 0.1
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


function bch_add_store_custom_field_info() {
  global $post;
 
  if (is_single() && ('post' == get_post_type())) {
    $custom = get_post_custom($post->ID);
    $opendate = isset($custom['opendate'][0]) ? $custom['opendate'][0] : '1996-01-01';
    $closeddate = isset($custom['closeddate'][0]) ? strftime('%B %Y', strtotime($custom['closeddate'][0])) : '';
    $url = '';
    if (array_key_exists('url', $custom)) {
        $url = $custom['url'][0];
    }
    ?>
    <div class="shop-info">

<?php
  // Change Unit to Units when more than one tag/unit listed.
  $unit_text = 'Unit';
  $posttags = get_the_tags();
  if ($posttags) {
	  if (count($posttags) > 1) {
		  $unit_text = 'Units';
	  }

      $units = array();
      foreach($posttags as $tag) {
          $units[] = sprintf('<a href="%s">%s</a>', get_tag_link($tag->term_id), $tag->name); 
      }
      echo '<p>', $unit_text, ': ', implode(', ', $units), '</p>';
  }
  else {
	  echo '<p style="color: red">NOTE: No unit specified.</p>';
  }
?>
    <p>Opened: <strong><?php echo strftime('%B %Y', strtotime($opendate)); ?></strong>
    <?php
    if (strlen($closeddate)) {
    ?>
      <br />Closed: <?php echo '<strong>', $closeddate, '</strong>'; ?></p>
    <?php
    }
    else {
        echo '</p>';
    }
    if (strlen($url)) {
    ?>
      <p>Web site: <?php echo '<strong><a href="', $url, '">', $url, '</a></strong>'; ?></p>
    <?php
    }

/*
      // SELECT `bchsg_postmeta.post_id` FROM `bchsg_postmeta` INNER JOIN `bchsg_posts` WHERE `bchsg_postmeta.meta_key` REGEXP 'bchsg_postmeta.dates_for_unit_[[:digit:]]+_unit_number' AND `bchsg_postmeta.meta_value` = '213' AND `bchsg_posts.ID`=`bchsg_postmeta.post_id` AND `bchsg_posts.post_status`='publish'
	  // Need to INNER JOIN with bchsg_posts to ensure that `ID`='213' `post_status`=='publish' results
/*	  SELECT bchsg_postmeta.`post_id` FROM bchsg_postmeta INNER JOIN bchsg_posts WHERE bchsg_postmeta.`meta_key` REGEXP 'dates_for_unit_[[:digit:]]+_unit_number' AND bchsg_postmeta.`meta_value` = '213' AND bchsg_posts.`ID`=bchsg_postmeta.`post_id` AND bchsg_posts.`post_status`='publish'
*/
	  
    // Add history of this unit.
      echo '<h2>Store History</h2>';
      $dates_for_unit = get_field( 'dates_for_unit' );
      if ( $dates_for_unit ) {
          echo '<ul>';
          
		  $store_history = array();
          foreach ( $dates_for_unit as $dates ) {
			  $open_date = date( 'F Y', strtotime( $dates[ 'open_date' ] ) );
			  if ( ! empty( $dates[ 'close_date' ] ) ) {
				$close_date = date( 'F Y', strtotime( $dates[ 'close_date' ] ) );
				$store_history[] = sprintf('<li><a href="/tag/%s/">%s</a> (%s - %s)</li>', $dates[ 'unit_number' ], $dates[ 'unit_number' ], $open_date, $close_date );
			  }
			  else {
				  $store_history[] = sprintf('<li><a href="/tag/%s/">%s</a> (since %s)</li>', $dates[ 'unit_number' ], $dates[ 'unit_number' ], $open_date );
			  }
          }
          
		  echo implode( '', array_reverse( $store_history ) );
          echo '</ul>';
      }

	if ( $posttags ) {
		foreach($posttags as $tag) {
			if (is_numeric($tag->slug)) {
				
				printf( '<h2>History of unit <a href="/tag/%s/">%s</a></h2>', $tag->slug, $tag->slug );
				if ( is_user_logged_in() ) {
					echo '<p><small>TODO: Sort by open or close date to avoid incorrect store order (which is based on when the store first opened).</small></p>';
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

				if( $the_query->have_posts() ) {
					ob_start(); // Buffer output and drop later so that echo calls can be left in code until source control sorted.
					echo '<ul>';
					$stores_by_date = array();
					while ( $the_query->have_posts() ) {
						$the_query->the_post();
						
						echo '<li><a href="', the_permalink(), '">', the_title(), '</a> ';
						
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

				ksort( $stores_by_date );
				echo '<ul>';
				foreach ( array_reverse( $stores_by_date ) as $date ) {
					echo '<li>', $date, '</li>';
				}
				echo '</ul>';
			}
		}
	}
?>

    </div><!-- /.shop-info -->
    <?php
    ?>
    </div><!-- /#shop -->
    <hr/>
    <?php
    // Add contact form to solicit updates.
    echo do_shortcode('[ninja_form id=2]');
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
?>
<style>
.single-unit-num { display: flex; justify-content: space-between; }
</style>
<div class="archive-description single-unit-num">
<div><?php echo $left_link; ?></div>
<div><?php echo $right_link; ?></div>
</div>
<?
	}
}


add_filter( 'acf/settings/remove_wp_meta_box', '__return_false' );


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