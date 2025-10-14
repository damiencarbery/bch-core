<?php
/**
 * Widget API: Widget_Recent_Closures class
 *
 */

/**
 * Core class used to implement a Recent Closures widget.
 *
 * @see WP_Widget
 */
class BCH_Widget_Recent_Closures extends WP_Widget {

	/**
	 * Sets up a new Recent Closures widget instance.
	 */
	public function __construct() {
		$widget_ops = array(
			'classname'                   => 'widget_recent_closures',
			'description'                 => 'Most recent store closures.',
			'customize_selective_refresh' => true,
			'show_instance_in_rest'       => true,
		);
		parent::__construct( 'recent-closures', 'Recent Closures', $widget_ops );
		$this->alt_option_name = 'widget_recent_closures';
	}

	/**
	 * Outputs the content for the current Recent Closures widget instance.
	 *
	 * @param array $args     Display arguments including 'before_title', 'after_title',
	 *                        'before_widget', and 'after_widget'.
	 * @param array $instance Settings for the current Recent Closures widget instance.
	 */
	public function widget( $args, $instance ) {
		if ( ! isset( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		$default_title = 'Recent closures';
		$title         = ( ! empty( $instance['title'] ) ) ? $instance['title'] : $default_title;

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		$number = ( ! empty( $instance['number'] ) ) ? absint( $instance['number'] ) : 5;
		if ( ! $number ) {
			$number = 5;
		}
		$show_date = isset( $instance['show_date'] ) ? $instance['show_date'] : false;

		// Do a direct postmeta query to get the post IDs and closure dates (yyyy-mm-dd format).
		global $wpdb;
		$r = $wpdb->get_results( $wpdb->prepare("SELECT `post_id`, `meta_value` FROM {$wpdb->prefix}postmeta WHERE `meta_key` = 'closeddate' ORDER BY `meta_value` DESC LIMIT %d", $number ), OBJECT );

		if ( ! $r ) {
			return;
		}
		?>

		<?php echo $args['before_widget']; ?>

		<?php
		if ( $title ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}

		$format = current_theme_supports( 'html5', 'navigation-widgets' ) ? 'html5' : 'xhtml';

		/** This filter is documented in wp-includes/widgets/class-wp-nav-menu-widget.php */
		$format = apply_filters( 'navigation_widgets_format', $format );

		if ( 'html5' === $format ) {
			// The title may be filtered: Strip out HTML and make sure the aria-label is never empty.
			$title      = trim( strip_tags( $title ) );
			$aria_label = $title ? $title : $default_title;
			echo '<nav aria-label="' . esc_attr( $aria_label ) . '">';
		}
		?>

		<ul>

			<?php foreach ( $r as $recent_closure ) { ?>
				<?php
				$post_title   = get_the_title( $recent_closure->post_id );
				$title        = ( ! empty( $post_title ) ) ? $post_title : '(no title)';
				?>
				<li>
					<a href="<?php the_permalink( $recent_closure->post_id ); ?>"><?php echo $title; ?></a>
					<?php if ( $show_date ) { ?>(<span class="post-date"><?php echo date( 'j M Y', strtotime( $recent_closure->meta_value ) ); ?></span>)<?php } ?>
				</li>
			<?php } ?>
		</ul>

		<?php
		if ( 'html5' === $format ) {
			echo '</nav>';
		}

		echo $args['after_widget'];
	}

	/**
	 * Handles updating the settings for the current Recent Closures widget instance.
	 *
	 * @param array $new_instance New settings for this instance as input by the user via
	 *                            WP_Widget::form().
	 * @param array $old_instance Old settings for this instance.
	 * @return array Updated settings to save.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance              = $old_instance;
		$instance['title']     = sanitize_text_field( $new_instance['title'] );
		$instance['number']    = (int) $new_instance['number'];
		$instance['show_date'] = isset( $new_instance['show_date'] ) ? (bool) $new_instance['show_date'] : false;
		return $instance;
	}

	/**
	 * Outputs the settings form for the Recent Closures widget.
	 *
	 * @param array $instance Current settings.
	 */
	public function form( $instance ) {
		$title     = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$number    = isset( $instance['number'] ) ? absint( $instance['number'] ) : 5;
		$show_date = isset( $instance['show_date'] ) ? (bool) $instance['show_date'] : false;
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>">Title:</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'number' ); ?>">Number of stores to show:</label>
			<input class="tiny-text" id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="number" step="1" min="1" value="<?php echo $number; ?>" size="3" />
		</p>

		<p>
			<input class="checkbox" type="checkbox"<?php checked( $show_date ); ?> id="<?php echo $this->get_field_id( 'show_date' ); ?>" name="<?php echo $this->get_field_name( 'show_date' ); ?>" />
			<label for="<?php echo $this->get_field_id( 'show_date' ); ?>">Display closure date?</label>
		</p>
		<?php
	}
}



// Load this widget.
add_action( 'widgets_init', 'bch_load_widgets' );
function bch_load_widgets() {
    register_widget( 'BCH_Widget_Recent_Closures' );
}