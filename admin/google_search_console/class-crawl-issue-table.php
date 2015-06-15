<?php
/**
 * @package WPSEO\Admin|Google_Search_Console
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Class WPSEO_Crawl_Issue_Table
 */
class WPSEO_Crawl_Issue_Table extends WP_List_Table {

	/**
	 * @var string
	 */
	private $search_string;

	/**
	 * @var array
	 */
	protected $_column_headers;

	/**
	 * The category that is displayed
	 *
	 * @var mixed|string
	 */
	private $current_view;

	/**
	 * @var WPSEO_Crawl_Issue_Table_Data
	 */
	private $crawl_issue_source;

	/**
	 * @var integer
	 */
	private $per_page     = 50;

	/**
	 * @var integer
	 */
	private $current_page = 1;

	/**
	 * @var array
	 */
	private $modal_heights = array(
		'create'         => 350,
		'no_premium'     => 100,
		'already_exists' => 150,
	);

	/**
	 * The constructor
	 *
	 * @param WPSEO_GWT_Platform_Tabs $platform_tabs
	 * @param WPSEO_GWT_Service       $service
	 */
	public function __construct( WPSEO_GWT_Platform_Tabs $platform_tabs, WPSEO_GWT_Service $service ) {
		parent::__construct();

		// Adding the thickbox.
		add_thickbox();

		// Set search string.
		if ( ( $search_string = filter_input( INPUT_GET, 's' ) ) != '' ) {
			$this->search_string = $search_string;
		}

		// Set the crawl issue source.
		$this->crawl_issue_source = new WPSEO_Crawl_Issue_Table_Data( $platform_tabs->current_tab(), $this->screen->id, $service );
		$this->crawl_issue_source->show_fields();
	}

	/**
	 * Setup the table variables, fetch the items from the database, search, sort and format the items.
	 * Set the items as the WPSEO_Redirect_Table items variable.
	 *
	 */
	public function prepare_items() {
		// Setting the current view.
		$this->current_view       = $this->crawl_issue_source->get_category();

		// Get variables needed for pagination.
		$this->per_page     = $this->get_items_per_page( 'errors_per_page', $this->per_page );
		$this->current_page = intval( ( $paged = filter_input( INPUT_GET, 'paged' ) ) ? $paged : 1 );

		// Setup the columns.
		$this->setup_columns();

		// Views.
		$this->views();

		// Setting the items.
		$this->set_items();
	}

	/**
	 * Running the setup of the columns
	 */
	public function setup_columns() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	/**
	 * Set the table columns
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'             => '<input type="checkbox" />',
			'url'            => __( 'URL', 'wordpress-seo' ),
			'last_crawled'   => __( 'Last crawled', 'wordpress-seo' ),
			'first_detected' => __( 'First detected', 'wordpress-seo' ),
			'response_code'  => __( 'Response code', 'wordpress-seo' ),
		);

		return $columns;
	}

	/**
	 * Return the columns that are sortable
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'url'            => array( 'url', false ),
			'last_crawled'   => array( 'last_crawled', false ),
			'first_detected' => array( 'first_detected', false ),
			'response_code'  => array( 'response_code', false ),
		);

		return $sortable_columns;
	}

	/**
	 * Default method to display a column
	 *
	 * @param array  $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	protected function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}

	/**
	 * Checkbox column
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="wpseo_crawl_issues_mark_as_fixed[]" value="%s" />', $item['url']
		);
	}

	/**
	 * Return available bulk actions
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'mark_as_fixed' => __( 'Mark as fixed', 'wordpress-seo' ),
		);
	}

	/**
	 * URL column
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	protected function column_url( $item ) {
		$actions = array();

		if (  $this->can_create_redirect( ) ) {
			/**
			 * Modal box
			 */
			$modal_height = $this->modal_box( $item['url'] );

			$actions['create_redirect'] = '<a href="#TB_inline?width=600&height=' . $this->modal_heights[ $modal_height ] . '&inlineId=redirect-' . md5( $item['url'] )  . '" class="thickbox">' . __( 'Create redirect', 'wordpress-seo' ) . '</a>';
		}

		$actions['view']        = '<a href="' . $item['url'] . '" target="_blank">' . __( 'View', 'wordpress-seo' ) . '</a>';
		$actions['markasfixed'] = '<a href="javascript:wpseo_mark_as_fixed(\'' . urlencode( $item['url'] ) . '\');">' . __( 'Mark as fixed', 'wordpress-seo' ) . '</a>';

		return sprintf(
			'<span class="value">%1$s</span> %2$s',
			$item['url'],
			$this->row_actions( $actions )
		);
	}

	/**
	 * Check if the current category allow creating redirects
	 * @return bool
	 */
	private function can_create_redirect(  ) {
		return in_array( $this->crawl_issue_source->get_category(), array( 'soft404', 'notFound', 'accessDenied' ) );
	}

	/**
	 * Setting the table navigation
	 *
	 * @param int $total_items
	 * @param int $posts_per_page
	 */
	private function set_pagination( $total_items, $posts_per_page ) {
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'total_pages' => ceil( ( $total_items / $posts_per_page ) ),
			'per_page'    => $posts_per_page,
		) );
	}

	/**
	 * Setting the items
	 */
	private function set_items() {
		$this->items = $this->crawl_issue_source->get_issues();

		if ( is_array( $this->items ) && count( $this->items ) > 0 ) {
			if ( ! empty ( $this->search_string) ) {
				$this->do_search();
			}

			$this->set_pagination( count( $this->items ), $this->per_page );

			$this->sort_items();
			$this->paginate_items();
		}
	}

	/**
	 * Search through the items
	 */
	private function do_search( ) {
		$results = array();

		foreach ( $this->items as $item ) {
			foreach ( $item as $value ) {
				if ( stristr( $value, $this->search_string ) !== false ) {
					$results[] = $item;
					continue;
				}
			}
		}

		$this->items = $results;
	}

	/**
	 * Running the pagination
	 */
	private function paginate_items() {
		// Setting the starting point. If starting point is below 1, overwrite it with value 0, otherwise it will be sliced of at the back.
		$slice_start = ( $this->current_page - 1 );
		if ( $slice_start < 0 ) {
			$slice_start = 0;
		}

		// Apply 'pagination'.
		$this->items = array_slice( $this->items, ( $slice_start * $this->per_page ), $this->per_page );
	}

	/**
	 * Sort the items by callback
	 */
	private function sort_items() {
		// Sort the results.
		usort( $this->items, array( $this, 'do_reorder' ) );
	}
	/**
	 * Doing the sorting of the issues
	 *
	 * @param array $a
	 * @param array $b
	 *
	 * @return int
	 */
	private function do_reorder($a, $b) {
		// If no sort, default to title.
		$orderby = ( $orderby = filter_input( INPUT_GET, 'orderby' ) ) ? $orderby : 'url';

		// If no order, default to asc.
		$order = ( $order = filter_input( INPUT_GET, 'order' ) ) ? $order : 'asc';

		// Determine sort order.
		$result = strcmp( $a[ $orderby ], $b[ $orderby ] );

		// Send final sort direction to usort.
		return ( $order === 'asc' ) ? $result : ( -$result );
	}

	/**
	 * Modal box
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	private function modal_box( $url ) {
		$current_redirect = false;
		$view_type        = $this->modal_box_type( $url, $current_redirect );

		require 'views/gwt-create-redirect.php';

		return $view_type;
	}

	/**
	 * Determine which model box type should be rendered
	 *
	 * @param string $url
	 * @param string $current_redirect
	 *
	 * @return string
	 */
	private function modal_box_type( $url, &$current_redirect) {

		if ( defined( 'WPSEO_PREMIUM_FILE' ) && class_exists( 'WPSEO_URL_Redirect_Manager' ) ) {
			static $redirect_manager;

			if ( ! $redirect_manager ) {
				$redirect_manager = new WPSEO_URL_Redirect_Manager();
			}

			if ( $current_redirect = $redirect_manager->search_url( $url ) ) {
				return 'already_exists';
			}

			return 'create';
		}

		return 'no_premium';
	}

}
