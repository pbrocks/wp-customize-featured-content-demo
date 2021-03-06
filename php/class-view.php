<?php
/**
 * Class View.
 *
 * @package Customize_Featured_Content_Demo
 */

namespace Customize_Featured_Content_Demo;

/**
 * Class View
 *
 * @package Customize_Featured_Content_Demo
 */
class View {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Number of times the items were rendered.
	 *
	 * This is used by the active callback for the featured_items panel to
	 * determine whether or not it is contextual to the current preview.
	 *
	 * @see Featured_Items_Customize_Panel::active_callback()
	 * @var int
	 */
	public $render_items_count = 0;

	/**
	 * Plugin constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Add hooks.
	 */
	public function add_hooks() {
		add_action( 'init', array( $this, 'register_shortcode' ) );
	}

	/**
	 * Register shortcode.
	 */
	public function register_shortcode() {
		$view = $this; // For PHP 5.3.
		add_shortcode( 'featured_items', function() use ( $view ) {
			ob_start();
			$view->render_items();
			return ob_get_clean();
		} );
	}

	/**
	 * Render items.
	 */
	public function render_items() {
		$this->render_items_count += 1;
		$item_ids = array_keys( $this->plugin->model->get_items() );

		/*
		 * Render nothing if there are no items and if not in the customizer preview.
		 * If in the customizer preview, it's key to render the container UL so that
		 * new items can be added to it when they are created without having to
		 * refresh the entire page.
		 */
		if ( empty( $item_ids ) && ! is_customize_preview() ) {
			return;
		}

		echo '<ul class="featured-content-items">';
		foreach ( $item_ids as $item_id ) {
			$this->render_item( $item_id );
		}
		echo '</ul>';
	}

	/**
	 * Get rendered title.
	 *
	 * @param string $title Raw title.
	 * @param int    $id    Item (post) ID.
	 * @return string Rendered title.
	 */
	public function get_rendered_title( $title, $id ) {

		/** This filter is documented in wp-includes/post-template.php*/
		$title = apply_filters( 'the_title', $title, $id );

		$title = convert_smilies( $title );

		return $title;
	}

	/**
	 * Render item.
	 *
	 * @param int $id Featured item ID.
	 */
	function render_item( $id ) {
		$item = $this->plugin->model->get_item( $id );
		if ( ! $item || 'trash' === $item['status'] ) {
			return;
		}

		$item_schema_properties = $this->plugin->model->get_item_schema_properties();

		$related_post = ! empty( $item['related'] ) ? get_post( $item['related'] ) : null;
		if ( $related_post ) {
			$GLOBALS['post'] = $related_post;
			setup_postdata( $related_post ); // Gives a chance for Customize Posts to preview.
			if ( ! $item['url'] ) {
				$item['url'] = get_permalink( $related_post->ID );
			}
			if ( ! $item['title'] ) {
				$item['title'] = $related_post->post_title;
			}
			if ( ! $item['featured_media'] ) {
				$item['featured_media'] = get_post_thumbnail_id( $related_post );
			}
			wp_reset_postdata();
		}

		$rendered_item = array();
		foreach ( $item_schema_properties as $field_id => $field_schema ) {
			if ( isset( $field_schema['arg_options']['rendering']['callback'] ) ) {
				$render_callback = $field_schema['arg_options']['rendering']['callback'];
				$rendered_item[ $field_id ] = call_user_func(
					$render_callback,
					$item[ $field_id ],
					$id
				);
			} else {
				$rendered_item[ $field_id ] = $item[ $field_id ];
			}
		}

		$title_style = '';
		if ( $rendered_item['title_color'] ) {
			$title_style .= sprintf( 'color: %s;', $rendered_item['title_color'] );
		}
		if ( $rendered_item['title_background'] ) {
			$title_style .= sprintf( 'background-color: %s;', $rendered_item['title_background'] );
		}
		if ( $rendered_item['title_left'] ) {
			$title_style .= sprintf( 'left: %dpx;', $rendered_item['title_left'] );
		}
		if ( $rendered_item['title_top'] ) {
			$title_style .= sprintf( 'top: %dpx;', $rendered_item['title_top'] );
		}

		?>
		<li
			class="featured-content-item"
			data-customize-partial-id="<?php echo esc_attr( "featured_item[$id]" ); ?>"
			data-customize-partial-type="featured_item"
		>
			<a
				<?php
				if ( $rendered_item['url'] ) {
					printf( ' href="%s"', esc_url( $rendered_item['url'] ) );
				}
				?>
			>
				<span
					class="title"
					<?php
					if ( $title_style ) {
						printf( ' style="%s"', esc_attr( $title_style ) );
					}
					?>
				>
					<?php echo $rendered_item['title']; // WPCS: XSS OK. ?>
				</span>
				<?php if ( $rendered_item['featured_media'] ) : ?>
					<?php echo wp_get_attachment_image( $rendered_item['featured_media'], 'thumbnail' ); ?>
				<?php endif; ?>
			</a>
		</li>
		<?php
	}
}
