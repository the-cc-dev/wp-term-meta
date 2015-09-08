<?php

/**
 * Term Meta UI Class
 *
 * @since 0.1.3
 *
 * @package TermMeta/UI
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( function_exists( 'add_term_meta' ) && ! class_exists( 'WP_Term_Meta_UI' ) ) :
/**
 * Main WP Term Meta UI class
 *
 * @link https://make.wordpress.org/core/2013/07/28/potential-roadmap-for-taxonomy-meta-and-post-relationships/ Taxonomy Roadmap
 *
 * @since 0.1.0
 */
class WP_Term_Meta_UI {

	/**
	 * @var string Plugin version
	 */
	protected $version = '0.0.0';

	/**
	 * @var string Database version
	 */
	protected $db_version = 201501010001;

	/**
	 * @var string Database version
	 */
	protected $db_version_key = '';

	/**
	 * @var string Metadata key
	 */
	protected $meta_key = '';

	/**
	 * @var string No value
	 */
	protected $no_value = '&#8212;';

	/**
	 * @var array Array of labels
	 */
	protected $labels = array(
		'singular'   => '',
		'plural'     => '',
		'descrption' => ''
	);

	/**
	 * @var string File for plugin
	 */
	public $file = '';

	/**
	 * @var string URL to plugin
	 */
	public $url = '';

	/**
	 * @var string Path to plugin
	 */
	public $path = '';

	/**
	 * @var string Basename for plugin
	 */
	public $basename = '';

	/**
	 * @var boo Whether to use fancy UI
	 */
	public $fancy = false;

	/**
	 * Hook into queries, admin screens, and more!
	 *
	 * @since 0.1.0
	 */
	public function __construct( $file = '' ) {

		// Setup plugin
		$this->file     = $file;
		$this->url      = plugin_dir_url( $this->file );
		$this->path     = plugin_dir_path( $this->file );
		$this->basename = plugin_basename( $this->file );
		$this->fancy    = apply_filters( "wp_fancy_term_{$this->meta_key}", true );

		// Queries
		add_action( 'create_term', array( $this, 'save_meta' ), 10, 2 );
		add_action( 'edit_term',   array( $this, 'save_meta' ), 10, 2 );

		// Get visible taxonomies
		$taxonomies = $this->get_taxonomies();

		// Always hook these in, for ajax actions
		foreach ( $taxonomies as $value ) {

			// Unfancy gets the column
			add_filter( "manage_edit-{$value}_columns",          array( $this, 'add_column_header' ) );
			add_filter( "manage_{$value}_custom_column",         array( $this, 'add_column_value'  ), 10, 3 );
			add_filter( "manage_edit-{$value}_sortable_columns", array( $this, 'sortable_columns'  ) );

			add_action( "{$value}_add_form_fields",  array( $this, 'add_form_field'  ) );
			add_action( "{$value}_edit_form_fields", array( $this, '	edit_form_field' ) );
		}

		// ajax actions
		$ajax_key = "ajax_{$this->meta_key}_terms";
		add_action( "wp_{$ajax_key}", array( $this, 'ajax_update' ) );

		// Only blog admin screens
		if ( is_blog_admin() || doing_action( 'wp_ajax_inline_save_tax' ) ) {
			add_action( 'admin_init',         array( $this, 'admin_init' ) );
			add_action( 'load-edit-tags.php', array( $this, 'edit_tags'  ) );
		}
	}

	/**
	 * Administration area hooks
	 *
	 * @since 0.1.0
	 */
	public function admin_init() {

		// Check for DB update
		$this->maybe_upgrade_database();
	}

	/**
	 * Administration area hooks
	 *
	 * @since 0.1.0
	 */
	public function edit_tags() {

		// Enqueue javascript
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_head',            array( $this, 'help_tabs'       ) );
		add_action( 'admin_head',            array( $this, 'admin_head'      ) );

		// Quick edit
		add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_meta' ), 10, 3 );
	}

	/** Assets ****************************************************************/

	/**
	 * Enqueue quick-edit JS
	 *
	 * @since 0.1.0
	 */
	public function enqueue_scripts() { }

	/**
	 * Add help tabs for this metadata
	 *
	 * @since 0.1.0
	 */
	public function help_tabs() { }

	/**
	 * Quick edit ajax updating
	 *
	 * @since 0.1.1
	 */
	public function ajax_update() {}

	/**
	 * Return the taxonomies used by this plugin
	 *
	 * @since 0.1.0
	 *
	 * @param array $args
	 * @return array
	 */
	private static function get_taxonomies( $args = array() ) {

		// Parse arguments
		$r = wp_parse_args( $args, array(
			'show_ui' => true
		) );

		// Get & return the taxonomies
		return get_taxonomies( $r );
	}

	/** Columns ***************************************************************/

	/**
	 * Add the "meta_key" column to taxonomy terms list-tables
	 *
	 * @since 0.1.0
	 *
	 * @param array $columns
	 *
	 * @return array
	 */
	public function add_column_header( $columns = array() ) {
		$columns[ $this->meta_key ] = $this->labels['singular'];

		return $columns;
	}

	/**
	 * Output the value for the custom column
	 *
	 * @since 0.1.0
	 *
	 * @param string $empty
	 * @param string $custom_column
	 * @param int    $term_id
	 *
	 * @return mixed
	 */
	public function add_column_value( $empty = '', $custom_column = '', $term_id = 0 ) {

		// Bail if no taxonomy passed or not on the `meta_key` column
		if ( empty( $_REQUEST['taxonomy'] ) || ( $this->meta_key !== $custom_column ) || ! empty( $empty ) ) {
			return;
		}

		// Get the metadata
		$meta   = $this->get_meta( $term_id );
		$retval = $this->no_value;

		// Output HTML element if not empty
		if ( ! empty( $meta ) ) {
			$retval = $this->format_output( $meta );
		}

		echo $retval;
	}

	/**
	 * Allow sorting by this `meta_key`
	 *
	 * @since 0.1.0
	 *
	 * @param array $columns
	 *
	 * @return array
	 */
	public function sortable_columns( $columns = array() ) {
		$columns[ $this->meta_key ] = $this->meta_key;
		return $columns;
	}

	/**
	 * Add `meta_key` to term when updating
	 *
	 * @since 0.1.0
	 *
	 * @param  int     $term_id
	 * @param  string  $taxonomy
	 */
	public function save_meta( $term_id = 0, $taxonomy = '' ) {

		// Get the term being posted
		$term_key = 'term-' . $this->meta_key;

		// Bail if not updating meta_key
		$meta = ! empty( $_POST[ $term_key ] )
			? $_POST[ $term_key ]
			: '';

		$this->set_meta( $term_id, $taxonomy, $meta );
	}

	/**
	 * Set `meta_key` of a specific term
	 *
	 * @since 0.1.0
	 *
	 * @param  int     $term_id
	 * @param  string  $taxonomy
	 * @param  string  $meta
	 * @param  bool    $clean_cache
	 */
	public function set_meta( $term_id = 0, $taxonomy = '', $meta = '', $clean_cache = false ) {

		// No meta_key, so delete
		if ( empty( $meta ) ) {
			delete_term_meta( $term_id, $this->meta_key );

		// Update meta_key value
		} else {
			update_term_meta( $term_id, $this->meta_key, $meta );
		}

		// Maybe clean the term cache
		if ( true === $clean_cache ) {
			clean_term_cache( $term_id, $taxonomy );
		}
	}

	/**
	 * Return the `meta_key` of a term
	 *
	 * @since 0.1.0
	 *
	 * @param int $term_id
	 */
	public function get_meta( $term_id = 0 ) {
		return get_term_meta( $term_id, $this->meta_key, true );
	}

	/** Markup ****************************************************************/

	/**
	 * Output the form field for this metadata when adding a new term
	 *
	 * @since 0.1.0
	 */
	public function add_form_field() {
		?>

		<div class="form-field term-<?php echo esc_attr( $this->meta_key ); ?>-wrap">
			<label for="term-<?php echo esc_attr( $this->meta_key ); ?>">
				<?php echo esc_html( $this->labels['singular'] ); ?>
			</label>

			<?php $this->form_field(); ?>

			<?php if ( ! empty( $this->labels['description'] ) ) : ?>

				<p class="description">
					<?php echo esc_html( $this->labels['description'] ); ?>
				</p>

			<?php endif; ?>

		</div>

		<?php
	}

	/**
	 * Output the form field when editing an existing term
	 *
	 * @since 0.1.0
	 *
	 * @param object $term
	 */
	public function edit_form_field( $term = false ) {
		?>

		<tr class="form-field term-<?php echo esc_attr( $this->meta_key ); ?>-wrap">
			<th scope="row" valign="top">
				<label for="term-<?php echo esc_attr( $this->meta_key ); ?>">
					<?php echo esc_html( $this->labels['singular'] ); ?>
				</label>
			</th>
			<td>
				<?php $this->form_field( $term ); ?>

				<?php if ( ! empty( $this->labels['description'] ) ) : ?>

					<p class="description">
						<?php echo esc_html( $this->labels['description'] ); ?>
					</p>

				<?php endif; ?>

			</td>
		</tr>

		<?php
	}

	/**
	 * Output the quick-edit field
	 *
	 * @since 0.1.0
	 *
	 * @param  $term
	 */
	public function quick_edit_meta( $column_name = '', $screen = '', $name = '' ) {

		// Bail if not the meta_key column on the `edit-tags` screen for a visible taxonomy
		if ( ( $this->meta_key !== $column_name ) || ( 'edit-tags' !== $screen ) || ! in_array( $name, $this->get_taxonomies() ) ) {
			return false;
		} ?>

		<fieldset>
			<div class="inline-edit-col">
				<label>
					<span class="title"><?php echo esc_html( $this->labels['singular'] ); ?></span>
					<span class="input-text-wrap">

						<?php $this->quick_edit_form_field(); ?>

					</span>
				</label>
			</div>
		</fieldset>

		<?php
	}

	/**
	 * Output the form field
	 *
	 * @since 0.1.0
	 *
	 * @param  $term
	 */
	protected function form_field( $term = '' ) {

		// Get the meta value
		$value = isset( $term->term_id )
			?  $this->get_meta( $term->term_id )
			: ''; ?>

		<input type="text" name="term-<?php echo esc_attr( $this->meta_key ); ?>" id="term-<?php echo esc_attr( $this->meta_key ); ?>" value="<?php echo esc_attr( $value ); ?>">

		<?php
	}

	/**
	 * Output the form field
	 *
	 * @since 0.1.0
	 *
	 * @param  $term
	 */
	protected function quick_edit_form_field() {
		?>

		<input type="text" class="ptitle" name="term-<?php echo esc_attr( $this->meta_key ); ?>" value="">

		<?php
	}

	/** Database Alters *******************************************************/

	/**
	 * Should a database update occur
	 *
	 * Runs on `init`
	 *
	 * @since 0.1.0
	 */
	protected function maybe_upgrade_database() {

		// Check DB for version
		$db_version = get_option( $this->db_version_key );

		// Needs
		if ( $db_version < $this->db_version ) {
			$this->upgrade_database( $db_version );
		}
	}

	/**
	 * Upgrade the database as needed, based on version comparisons
	 *
	 * @since 0.1.0
	 *
	 * @param  int  $old_version
	 */
	private function upgrade_database( $old_version = 0 ) {
		update_option( $this->db_version_key, $this->db_version );
	}
}
endif;
