<?php
/**
 * Author: Hoang Ngo
 */

namespace WP_Defender\Module\IP_Lockout\Component;

use Hammer\Helper\HTTP_Helper;
use Hammer\Helper\WP_Helper;
use WP_Defender\Behavior\Utils;
use WP_Defender\Module\IP_Lockout\Model\IP_Model;
use WP_Defender\Module\IP_Lockout\Model\Log_Model;

class Logs_Table extends \WP_List_Table {
	public function __construct( $args = array() ) {
		parent::__construct( array_merge( array(
			'plural'     => '',
			'autoescape' => false,
			'screen'     => 'lockout_logs'
		), $args ) );
	}

	/**
	 * @return array
	 */
	function get_table_classes() {
		return array(
			'list-table',
			//'hover-effect',
			'logs',
			'intro'
		);
	}

	/**
	 * @return array
	 */
	function get_columns() {
		$columns = array(
			'reason' => esc_html__( 'Details', wp_defender()->domain ),
			'date'   => esc_html__( 'Time', wp_defender()->domain ),
			'action' => ''
		);

		return $columns;
	}

	protected function get_sortable_columns() {
		return array(
			//'reason' => array( 'log', true ),
//			'date' => array( 'date', true ),
//			'ip'   => array( 'ip', true ),
		);
	}

	function prepare_items() {
		$paged    = $this->get_pagenum();
		$per_page = 20;
		$offset   = ( $paged - 1 ) * $per_page;

		$params = array(
			'date' => array(
				'compare' => '>=',
				'value'   => strtotime( '-' . HTTP_Helper::retrieve_get( 'interval', 30 ) . ' days' )
			)
		);

		if ( ( $filter = Http_Helper::retrieve_get( 'type', null ) ) != null ) {
			$params['type'] = $filter;
		}
		if ( ( $ip = Http_Helper::retrieve_get( 'ip_address', null ) ) != null ) {
			$params['ip'] = $ip;
		}

		$logs       = Log_Model::findAll( $params,
			HTTP_Helper::retrieve_get( 'orderby', 'id' ),
			HTTP_Helper::retrieve_get( 'order', 'desc' ),
			$offset . ',' . $per_page
		);
		$cache      = WP_Helper::getArrayCache();
		$totalItems = $cache->get( Login_Protection_Api::COUNT_TOTAL, false );
		if ( $totalItems == false ) {
			$totalItems = Log_Model::count( $params );
			$cache->set( Login_Protection_Api::COUNT_TOTAL, $totalItems, 3600 );
		}

		$this->set_pagination_args( array(
			'total_items' => $totalItems,
			'total_pages' => ceil( $totalItems / $per_page ),
			'per_page'    => $per_page
		) );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->items           = $logs;
	}

	/**
	 * @param Log_Model $log
	 *
	 * @return string
	 */
	public function column_action( Log_Model $log ) {
		return '<i class="dev-icon dev-icon-caret_down"></i>';
	}

	/**
	 * @param Log_Model $log
	 *
	 * @return string
	 */
	public function column_reason( Log_Model $log ) {
		$format = false;
		if ( $log->type == Log_Model::ERROR_404 ) {
			$format = true;
		}
		ob_start();
		?>
        <!--        <input type="checkbox" class="single-select" name="ids[]" value="--><?php //echo $log->id ?><!--"/>-->
        <span class="badge <?php echo $log->type == 'auth_lock' || $log->type == '404_lock' ? 'locked' : null ?>"><?php echo $log->type == 'auth_fail' || $log->type == 'auth_lock' ? 'login' : '404' ?></span>
		<?php
		echo $log->get_log_text( $format );

		return ob_get_clean();
	}

	/**
	 * @param Log_Model $log
	 *
	 * @return string
	 */
	public function column_date( Log_Model $log ) {
		return $log->get_date();
	}

	/**
	 * @param Log_Model $log
	 *
	 * @return string
	 */
	public function column_ip( Log_Model $log ) {
		$ip = Utils::instance()->getUserIp();
		if ( $ip == $log->get_ip() ) {
			return '<span tooltip="' . esc_attr( $ip ) . '" class="badge">' . __( "You", wp_defender()->domain ) . '</span>';
		} else {
			return $log->get_ip();
		}
	}

	public function display() {
		$singular = $this->_args['singular'];

		$this->screen->render_screen_reader_content( 'heading_list' );
		?>
        <div class="lockout-logs-container">
			<?php $this->display_tablenav( 'top' ); ?>
			<?php if ( $this->_pagination_args['total_items'] > 0 ): ?>
                <div class="lockout-logs-inner">
                    <div class="lockout-logs-filter mline wd-hide">
                        <form method="post">
                            <div class="well well-white">
                                <div class="columns">
                                    <div class="column is-4">
                                        <strong>
											<?php _e( "Lockout Type", wp_defender()->domain ) ?>
                                        </strong>
                                        <select name="type">
                                            <option value=""><?php esc_html_e( "All", wp_defender()->domain ) ?></option>
                                            <option <?php selected( \WP_Defender\Module\IP_Lockout\Model\Log_Model::AUTH_FAIL, \Hammer\Helper\HTTP_Helper::retrieve_get( 'filter' ) ) ?>
                                                    value="<?php echo \WP_Defender\Module\IP_Lockout\Model\Log_Model::AUTH_FAIL ?>">
												<?php esc_html_e( "Failed login attempts", wp_defender()->domain ) ?></option>
                                            <option <?php selected( \WP_Defender\Module\IP_Lockout\Model\Log_Model::AUTH_LOCK, \Hammer\Helper\HTTP_Helper::retrieve_get( 'filter' ) ) ?>
                                                    value="<?php echo \WP_Defender\Module\IP_Lockout\Model\Log_Model::AUTH_LOCK ?>"><?php esc_html_e( "Login lockout", wp_defender()->domain ) ?></option>
                                            <option <?php selected( \WP_Defender\Module\IP_Lockout\Model\Log_Model::ERROR_404, \Hammer\Helper\HTTP_Helper::retrieve_get( 'filter' ) ) ?>
                                                    value="<?php echo \WP_Defender\Module\IP_Lockout\Model\Log_Model::ERROR_404 ?>"><?php esc_html_e( "404 error", wp_defender()->domain ) ?></option>
                                            <option <?php selected( \WP_Defender\Module\IP_Lockout\Model\Log_Model::LOCKOUT_404, \Hammer\Helper\HTTP_Helper::retrieve_get( 'filter' ) ) ?>
                                                    value="<?php echo \WP_Defender\Module\IP_Lockout\Model\Log_Model::LOCKOUT_404 ?>"><?php esc_html_e( "404 lockout", wp_defender()->domain ) ?></option>
                                        </select>
                                    </div>
                                    <div class="column is-4">
                                        <strong>
											<?php _e( "IP Address", wp_defender()->domain ) ?>
                                        </strong>
                                        <input name="ip_address" type="text"
                                               placeholder="<?php esc_attr_e( "Enter an IP address", wp_defender()->domain ) ?>">
                                    </div>
                                </div>
                                <div class="well-footer tr">
                                    <button type="submit" class="button button-small">
										<?php _e( "Apply", wp_defender()->domain ) ?></button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>">
                        <thead>
                        <tr>
							<?php $this->print_column_headers(); ?>
                        </tr>
                        </thead>

                        <tbody id="the-list"<?php
						if ( $singular ) {
							echo " data-wp-lists='list:$singular'";
						} ?>>
						<?php $this->display_rows_or_placeholder(); ?>
                        </tbody>
                    </table>
                </div>
				<?php
				$this->display_tablenav( 'bottom' );
				?>
			<?php else: ?>
                <div class="well with-cap well-blue">
                    <i class="def-icon icon-info fill-blue"></i>
					<?php _e( "No lockout events have been logged within the selected time period.", wp_defender()->domain ) ?>
                </div>
                <table></table>
			<?php endif; ?>
        </div>
		<?php
	}

	/**
	 * @param object $item
	 */
	public function single_row( $item ) {
		$class = '';
		if ( in_array( $item->type, array(
			Log_Model::AUTH_LOCK,
			Log_Model::AUTH_FAIL
		) ) ) {
			$class = 'log-login';
		} elseif ( in_array( $item->type, array(
			Log_Model::ERROR_404,
			Log_Model::ERROR_404_IGNORE,
			Log_Model::LOCKOUT_404
		) ) ) {
			$class = 'log-404';
		}

		if ( in_array( $item->type, array(
			Log_Model::LOCKOUT_404,
			Log_Model::AUTH_LOCK
		) ) ) {
			$class .= ' lockout';
		}
		$class .= ' show-hide-log';
		echo '<tr class="' . $class . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
		echo '<tr class="table-info wd-hide">';
		echo $this->detailRow( $item );
		echo '<tr>';
	}

	public function detailRow( $item ) {

		?>
        <td colspan="4">
            <div class="dev-box">
                <div class="box-content">
                    <div class="columns">
                        <div class="column is-8">
                            <p><strong><?php _e( "Description", wp_defender()->domain ) ?></strong></p>
                            <p><?php
								if ( $item->type == '404_error' ) {
									printf( __( "%s tried to access file %s", wp_defender()->domain ), $item->ip, $item->log );
								} elseif ( $item->type == 'auth_fail' ) {
									printf( __( "%s tried to login with username %s", wp_defender()->domain ), $item->ip, $item->tried );
								}
								?></p>
                        </div>
                        <div class="column is-4">
                            <p><strong><?php _e( "Type", wp_defender()->domain ) ?></strong></p>
                            <p>
                                <a href=""><?php echo in_array( $item->type, array(
										Log_Model::ERROR_404,
										Log_Model::ERROR_404_IGNORE,
										Log_Model::LOCKOUT_404
									) ) ? __( "404 error", wp_defender()->domain ) : __( "Login failed", wp_defender()->domain ) ?></a>
                            </p>
                        </div>
                    </div>
                    <div class="columns">
                        <div class="column is-4">
                            <p><strong><?php _e( "IP", wp_defender()->domain ) ?></strong></p>
                            <p><a href=""><?php
									echo $item->ip
									?></a></p>
                        </div>
                        <div class="column is-4">
                            <p><strong><?php _e( "Date/Time", wp_defender()->domain ) ?></strong></p>
                            <p><?php
								echo Utils::instance()->formatDateTime( $item->date )
								?></p>
                        </div>
                        <div class="column is-4">
                            <p><strong><?php _e( "Ban Status", wp_defender()->domain ) ?></strong></p>
                            <p><?php
								echo Login_Protection_Api::getIPStatusText( $item->ip )
								?></p>
                        </div>
                    </div>
                    <div class="well well-white">
                        <div>
							<?php
							echo Login_Protection_Api::getLogsActionsText( $item );
							?>
                        </div>
                        <p>
							<?php _e( "Note: Make sure this IP is not a legitimate operation, banning the IP will result in being permanently locked out from accessing your website.", wp_defender()->domain ) ?>
                        </p>
                    </div>
                </div>
            </div>
        </td>
		<?php
	}

	protected function display_tablenav( $which ) {
		?>
        <div class="intro">
            <div class="columns">
                <div class="column is-3">
                    <!--                    <input type="checkbox" id="bulk-select">-->
                    <!--                    <a href="#bulk" rel="dialog" class="button button-small button-light button-disabled">-->
                    <!--						--><?php //_e( "Bulk Action", wp_defender()->domain ) ?>
                    <!--                    </a>-->
                </div>
                <div class="column is-7">
                    <div class="nav">
                        <span><?php echo sprintf( esc_html__( "%s results", wp_defender()->domain ), $this->get_pagination_arg( 'total_items' ) ) ?></span>
                        <div class="button-group">
							<?php $this->pagination( $which ); ?>
                        </div>
                    </div>
                </div>
                <div class="column is-2 tr">
                    <button type="button" rel="show-filter" data-target=".lockout-logs-filter"
                            class="button button-small button-secondary"><?php _e( "Filter", wp_defender()->domain ) ?></button>
                </div>
            </div>

            <div class="clear"></div>
        </div>
		<?php
	}

	/**
	 * @param string $which
	 */
	protected function pagination( $which ) {
		if ( empty( $this->_pagination_args ) ) {
			return;
		}

		$total_items = $this->_pagination_args['total_items'];
		$total_pages = $this->_pagination_args['total_pages'];

		if ( $total_items == 0 ) {
			return;
		}

		if ( $total_pages < 2 ) {
			return;
		}

		$links        = array();
		$current_page = $this->get_pagenum();
		/**
		 * if pages less than 7, display all
		 * if larger than 7 we will get 3 previous page of current, current, and .., and, and previous, next, first, last links
		 */
		$current_url = set_url_scheme( 'http://' . parse_url( get_site_url(), PHP_URL_HOST ) . $_SERVER['REQUEST_URI'] );
		$current_url = remove_query_arg( array( 'hotkeys_highlight_last', 'hotkeys_highlight_first' ), $current_url );
		$current_url = esc_url( $current_url );
		$radius      = 1;
		if ( $current_page > 1 && $total_pages > $radius ) {
//			$links['first'] = sprintf( '<a class="button button-small lockout-nav button-light" data-paged="%s" href="%s">%s</a>',
//				1, add_query_arg( 'paged', 1, $current_url ), '&laquo;' );
			$links['prev'] = sprintf( '<a class="button button-small lockout-nav button-light" data-paged="%s" href="%s">%s</a>',
				$current_page - 1, add_query_arg( 'paged', $current_page - 1, $current_url ), '&lsaquo;' );
		}

		for ( $i = 1; $i <= $total_pages; $i ++ ) {
			if ( ( $i >= 1 && $i <= $radius ) || ( $i > $current_page - 2 && $i < $current_page + 2 ) || ( $i <= $total_pages && $i > $total_pages - $radius ) ) {
				if ( $i == $current_page ) {
					$links[ $i ] = sprintf( '<a href="#" class="button button-small lockout-nav button-light" data-paged="%s" disabled="">%s</a>', $i, $i );
				} else {
					$links[ $i ] = sprintf( '<a class="button button-small lockout-nav button-light" data-paged="%s" href="%s">%s</a>',
						$i, add_query_arg( 'paged', $i, $current_url ), $i );
				}
			} elseif ( $i == $current_page - $radius || $i == $current_page + $radius ) {
				$links[ $i ] = '<a href="#" class="button lockout-nav button-small button-light" disabled="">...</a>';
			}
		}

		if ( $current_page < $total_pages && $total_pages > $radius ) {
			$links['next'] = sprintf( '<a class="button lockout-nav button-small button-light" data-paged="%s" href="%s">%s</a>',
				$current_page + 1, add_query_arg( 'paged', $current_page + 1, $current_url ), '&rsaquo;' );
//			$links['last'] = sprintf( '<a class="button lockout-nav button-small button-light" data-paged="%s" href="%s">%s</a>',
//				$total_pages, add_query_arg( 'paged', $total_pages, $current_url ), '&raquo;' );
		}
		$output            = join( "\n", $links );
		$this->_pagination = $output;

		echo $this->_pagination;
	}

	public function print_column_headers( $with_id = true ) {
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		$current_url = network_admin_url( 'admin.php?page=wdf-ip-lockout&view=logs' );

		if ( isset( $_GET['orderby'] ) ) {
			$current_orderby = $_GET['orderby'];
		} else {
			$current_orderby = '';
		}

		if ( isset( $_GET['order'] ) && 'desc' === $_GET['order'] ) {
			$current_order = 'desc';
		} else {
			$current_order = 'asc';
		}

		if ( ! empty( $columns['cb'] ) ) {
			static $cb_counter = 1;
			$columns['cb'] = '<label class="screen-reader-text" for="cb-select-all-' . $cb_counter . '">' . __( 'Select All' ) . '</label>'
			                 . '<input id="cb-select-all-' . $cb_counter . '" type="checkbox" />';
			$cb_counter ++;
		}

		foreach ( $columns as $column_key => $column_display_name ) {
			$class = array( 'manage-column', "column-$column_key" );

			if ( in_array( $column_key, $hidden ) ) {
				$class[] = 'hidden';
			}

			if ( 'cb' === $column_key ) {
				$class[] = 'check-column';
			} elseif ( in_array( $column_key, array( 'posts', 'comments', 'links' ) ) ) {
				$class[] = 'num';
			}

			if ( $column_key === $primary ) {
				$class[] = 'column-primary';
			}

			if ( isset( $sortable[ $column_key ] ) ) {
				list( $orderby, $desc_first ) = $sortable[ $column_key ];

				if ( $current_orderby === $orderby ) {
					$order   = 'asc' === $current_order ? 'desc' : 'asc';
					$class[] = 'sorted';
					$class[] = $current_order;
				} else {
					$order   = $desc_first ? 'desc' : 'asc';
					$class[] = 'sortable';
					$class[] = $desc_first ? 'asc' : 'desc';
				}

				$column_display_name = '<a href="' . esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ) . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';
			}

			$tag   = ( 'cb' === $column_key ) ? 'td' : 'th';
			$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
			$id    = $with_id ? "id='$column_key'" : '';

			if ( ! empty( $class ) ) {
				$class = "class='" . join( ' ', $class ) . "'";
			}

			echo "<$tag $scope $id $class>$column_display_name</$tag>";
		}
	}
}