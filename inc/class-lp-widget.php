<?php
/**
 * Define base class of LearnPress widgets and helper functions
 */

if ( !class_exists( 'LP_Widget' ) ) {
	/**
	 * Class LP_Widget
	 *
	 * @extend WP_Widget
	 */
	class LP_Widget extends WP_Widget {

		/**
		 * @var array
		 */
		private static $_widgets = array();

		/**
		 * @var bool
		 */
		private static $_has_widget = false;

		/**
		 * @var bool
		 */
		private static $_has_registered = false;

		/**
		 * Widget prefix
		 *
		 * @var string
		 */
		private $_id_prefix = 'lp-widget-';

		/**
		 * Widget name prefix
		 *
		 * @var string
		 */
		private $_name_prefix = 'LearnPress - ';
		private $map_fields = array();

		/**
		 * Widget file name
		 *
		 * @var string
		 */
		public $file = '';

		/**
		 * Widget template path
		 *
		 * @var string
		 */
		public $template_path = '';

		/**
		 * Widget arguments
		 *
		 * @var array
		 */
		public $args = array();

		/**
		 * Widget options
		 *
		 * @var array
		 */
		public $instance = array();

		/**
		 * @var bool
		 */
		public $options = false;

		/**
		 * LP_Widget constructor.
		 *
		 * @param array
		 */
		public function __construct( $args = array() ) {
			$defaults = array( 'id_base' => '', 'name' => '', 'widget_options' => '', 'control_options' => '' );
			$args     = wp_parse_args( $args, $defaults );
			$args     = self::parse_widget_args(
				$args,
				strtolower( str_replace( array( 'LP_Widget_', '_' ), array( '', '-' ), get_class( $this ) ) )
			);
			list( $id_base, $name, $widget_options, $control_options ) = $args;
			parent::__construct( $this->_id_prefix . $id_base, $this->_name_prefix . $name, $widget_options, $control_options );
		}

		public function field_data( $data, $object_id, $meta_key, $single ) {
			global $post;
			if ( $post->post_type == 'lp-post-widget' ) {
				$key  = !empty( $this->map_fields[$meta_key] ) ? $this->map_fields[$meta_key] : $meta_key;
				$data = array_key_exists( $key, $this->instance ) ? $this->instance[$key] : '';
			}
			return $data;
		}

		public function update( $new_instance = array(), $old_instance = array() ) {
			print_r( $old_instance );
			print_r( $new_instance );
			return $new_instance;
		}

		/**
		 * Display widget content
		 *
		 * @param array $args
		 * @param array $instance
		 */
		public function widget( $args, $instance ) {
			$this->args     = $args;
			$this->instance = $instance;

			$this->before_widget();
			$this->show();
			$this->after_widget();
		}

		public function before_widget() {
			echo $this->args['before_widget'];
			if ( !empty( $this->instance['title'] ) ) {
				echo $this->args['before_title'];
				echo $this->instance['title'];
				echo $this->args['after_title'];
			}
		}

		public function after_widget() {
			echo $this->args['after_widget'];
		}

		public function show() {
			learn_press_debug( $this->args, $this->instance );
		}

		public function form( $instance ) {
			$this->instance = $instance;
			add_filter( 'get_post_metadata', array( $this, 'field_data' ), 10, 4 );
			//learn_press_get_widget_template( $this->get_slug(), 'form.php', array( 'widget' => $this ) );
			if ( !$this->options ) {
				return;
			}
			global $post;
			$post = (object) array( 'ID' => 1, 'post_type' => 'lp-post-widget' );
			setup_postdata( $post );
			require_once LP_PLUGIN_PATH . 'inc/libraries/meta-box/meta-box.php';
			$this->options = RW_Meta_Box::normalize_fields( $this->options );
			foreach ( $this->options as $field ) {
				$origin_id                      = $field['id'];
				$field['field_name']            = $this->get_field_name( $field['id'] );
				$field['id']                    = $this->get_field_id( $field['id'] );//sanitize_title( $field['field_name'] );
				$this->map_fields[$field['id']] = $origin_id;

				call_user_func( array( RW_Meta_Box::get_class_name( $field ), 'show' ), $field, false );
			}
			wp_reset_postdata();
			remove_filter( 'get_post_metadata', array( $this, 'field_data' ) );

		}

		/**
		 * Find template and display it
		 *
		 * @param $args
		 * @param $instance
		 */
		public function get_template( $args, $instance ) {
			learn_press_get_widget_template( $this->get_slug(), 'default.php', array( 'x' => 100 ) );
		}

		/**
		 * Get path to template files inside widget
		 *
		 * @return string
		 */
		public function get_template_path() {
			if ( file_exists( $this->file ) ) {
				$this->template_path = dirname( $this->file ) . '/tmpl';
			}
			return $this->template_path;
		}

		/**
		 * Get slug of this widget from file
		 *
		 * @return mixed
		 */
		public function get_slug() {
			$class = get_class( $this );
			return str_replace( '_', '-', strtolower( str_replace( 'LP_Widget_', '', $class ) ) );
		}

		/**
		 * @param string
		 * @param mixed
		 */
		public static function register( $type, $args = '' ) {
			self::$_widgets[$type] = $args;
			if ( !self::$_has_registered ) {
				add_action( 'widgets_init', array( __CLASS__, 'do_register' ) );
				self::$_has_registered = true;
			}
		}

		/**
		 * Tell WP register our widgets
		 */
		public static function do_register() {
			if ( !self::$_widgets ) {
				return;
			}
			foreach ( self::$_widgets as $type => $args ) {
				$widget_file = LP_PLUGIN_PATH . "inc/widgets/{$type}/{$type}.php";
				if ( !file_exists( $widget_file ) ) {
					continue;
				}
				include_once $widget_file;
				$widget_class = self::get_widget_class( $type );
				if ( class_exists( $widget_class ) ) {
					$widget       = new $widget_class();
					$widget->file = $widget_file;
					register_widget( $widget );
				}
			}
		}

		/**
		 * Get class name of widget without LP_Widget prefix
		 *
		 * @param $slug
		 *
		 * @return string
		 */
		private static function get_widget_class( $slug ) {
			return 'LP_Widget_' . preg_replace( '~\s+~', '_', ucwords( str_replace( '-', ' ', $slug ) ) );
		}

		/**
		 * Parse some default options
		 *
		 * @param $args
		 * @param $type
		 *
		 * @return array
		 */
		private static function parse_widget_args( $args, $type ) {
			$id_base         = !empty( $args['id_base'] ) ? $args['id_base'] : 'lp-widget-' . $type;
			$name            = !empty( $args['name'] ) ? $args['name'] : ucwords( str_replace( '-', ' ', $type ) );
			$widget_options  = !empty( $args['widget_options'] ) ? $args['widget_options'] : array();
			$control_options = !empty( $args['control_options'] ) ? $args['control_options'] : array();
			return array( $id_base, $name, $widget_options, $control_options );
		}
	}
}

/**
 * Get template path of a widget
 *
 * @param $slug
 *
 * @return string
 */
function learn_press_get_widget_template_path( $slug ) {
	return LP_WIDGET_PATH . "/{$slug}/tmpl/";
}

function learn_press_get_widget_theme_template_path( $slug ) {

}

/**
 * Display a template of a widget
 *
 * @param       $slug
 * @param       $template_name
 * @param array $args
 */
function learn_press_get_widget_template( $slug, $template_name, $args = array() ) {
	$template_path = learn_press_get_widget_template_path( $slug );
	learn_press_get_template( $template_name, $args, learn_press_template_path() . "/widgets/{$slug}", $template_path );
}