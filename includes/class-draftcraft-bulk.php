<?php
/**
 * DraftCraft — Bulk Keyword Strategy CSV Importer
 *
 * Imports a CSV of keywords into a persistent queue that the
 * WP-Cron pipeline consumes sequentially before falling back
 * to category rotation.
 *
 * CSV Columns: Keyword, Target Category, Specific Instructions, Scheduled Date
 *
 * @package DraftCraft
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DraftCraft_Bulk
 */
class DraftCraft_Bulk {


	const QUEUE_OPTION = 'draftcraft_keyword_queue';
	const MAX_ROWS     = 500;


	/**
	 * Boot admin handlers.
	 *
	 * @since 1.2.0
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'handle_csv_import' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_queue_actions' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_sample_csv_download' ) );
		add_action( 'wp_ajax_draftcraft_import_csv', array( __CLASS__, 'ajax_import_csv' ) );
		add_action( 'wp_ajax_draftcraft_queue_ajax_action', array( __CLASS__, 'ajax_queue_action' ) );
	}//end init()


	/**
	 * Serve a sample keyword CSV for download.
	 *
	 * @since 1.2.0
	 */
	public static function handle_sample_csv_download(): void {
     // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Download gate; capability + nonce checked below.
		$wants_sample = ! empty( $_GET['draftcraft_sample_csv'] );
     // phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! $wants_sample ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to download this file.', 'draftcraft' ) );
		}

		check_admin_referer( 'draftcraft_sample_csv' );

		$today = current_time( 'Y-m-d' );
		$soon  = wp_date( 'Y-m-d', strtotime( '+3 days', time() ) );
		$later = wp_date( 'Y-m-d', strtotime( '+7 days', time() ) );

		$rows = array(
			array(
				'Keyword',
				'Target Category',
				'Instructions',
				'Scheduled Date',
			),
			array(
				'best coffee makers 2026',
				'Reviews',
				'Compare drip vs espresso; keep it beginner-friendly.',
				$today,
			),
			array(
				'how to brew pour over coffee',
				'',
				'Step-by-step guide with gear list. Skip brand hype.',
				'',
			),
			array(
				'wordpress seo tips for beginners',
				'Tutorials',
				'Mention focus keyword and meta description basics.',
				$soon,
			),
			array(
				'indoor plant care for apartments',
				'Gardening',
				'Focus on low-light plants and watering mistakes.',
				$later,
			),
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="draftcraft-keyword-sample.csv"' );

     // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Could not generate the sample CSV.', 'draftcraft' ) );
		}

		// UTF-8 BOM so Excel opens columns cleanly.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		foreach ( $rows as $row ) {
			fputcsv( $out, $row );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}//end handle_sample_csv_download()


	/**
	 * Get the full keyword queue.
	 *
	 * @since  1.2.0
	 * @return array<int, array>
	 */
	public static function get_queue(): array {
		$queue = get_option( self::QUEUE_OPTION, array() );
		return is_array( $queue ) ? $queue : array();
	}//end get_queue()


	/**
	 * Persist the queue.
	 *
	 * @since 1.2.0
	 * @param array $queue Queue rows.
	 */
	public static function save_queue( array $queue ): void {
		update_option( self::QUEUE_OPTION, array_values( $queue ), false );
	}//end save_queue()


	/**
	 * Whether the queue has pending items due now (or overdue / no date).
	 *
	 * @since  1.2.0
	 * @return boolean
	 */
	public static function has_pending(): bool {
		return null !== self::peek_next();
	}//end has_pending()


	/**
	 * Peek at the next due queue item without removing it.
	 *
	 * @since  1.2.0
	 * @return array|null
	 */
	public static function peek_next(): ?array {
		$queue = self::get_queue();
		$now   = current_time( 'Y-m-d' );

		foreach ( $queue as $index => $row ) {
			if ( ! empty( $row['status'] ) && 'pending' !== $row['status'] ) {
				continue;
			}

			$scheduled = ( $row['scheduled_date'] ?? '' );
			if ( '' === $scheduled || $scheduled <= $now ) {
				$row['_queue_index'] = $index;
				return $row;
			}
		}

		return null;
	}//end peek_next()


	/**
	 * Mark a queue item as completed and remove it (or mark done).
	 *
	 * @since 1.2.0
	 * @param integer $index   Queue index.
	 * @param integer $post_id Created post ID.
	 */
	public static function complete_item( int $index, int $post_id = 0 ): void {
		$queue = self::get_queue();
		if ( ! isset( $queue[ $index ] ) ) {
			return;
		}

		/*
		 * Fires when a bulk keyword queue item is completed.
		 *
		 * @since 1.2.0
		 * @param array $row     Queue row.
		 * @param int   $post_id Created post ID.
		 */
		do_action( 'draftcraft_bulk_item_completed', $queue[ $index ], $post_id );

		unset( $queue[ $index ] );
		self::save_queue( $queue );
	}//end complete_item()


	/**
	 * Mark a queue item as failed (keep for retry, or remove based on settings).
	 *
	 * @since 1.2.0
	 * @param integer $index  Queue index.
	 * @param string  $reason Failure reason.
	 */
	public static function fail_item( int $index, string $reason = '' ): void {
		$queue = self::get_queue();
		if ( ! isset( $queue[ $index ] ) ) {
			return;
		}

		$queue[ $index ]['status']      = 'failed';
		$queue[ $index ]['fail_reason'] = sanitize_text_field( $reason );
		$queue[ $index ]['failed_at']   = current_time( 'mysql' );
		self::save_queue( $queue );
	}//end fail_item()


	/**
	 * Handle CSV upload from admin (non-AJAX fallback).
	 *
	 * @since 1.2.0
	 */
	public static function handle_csv_import(): void {
		if ( ! isset( $_POST['draftcraft_import_csv'] ) ) {
			return;
		}

		check_admin_referer( 'draftcraft_import_csv', 'draftcraft_csv_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to import CSV files.', 'draftcraft' ) );
		}

		$result = self::process_csv_upload(
			( $_FILES['draftcraft_csv_file'] ?? array() ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			sanitize_key( wp_unslash( ( $_POST['draftcraft_csv_mode'] ?? 'append' ) ) )
		);

		if ( is_wp_error( $result ) ) {
			self::redirect_with_notice( 'error', $result->get_error_message() );
		}

		self::redirect_with_notice( 'success', $result['message'] );
	}//end handle_csv_import()


	/**
	 * AJAX CSV import — no page reload.
	 *
	 * @since 1.2.0
	 */
	public static function ajax_import_csv(): void {
		check_ajax_referer( 'draftcraft_import_csv', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to import CSV files.', 'draftcraft' ) ) );
		}

		$result = self::process_csv_upload(
			( $_FILES['draftcraft_csv_file'] ?? array() ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			sanitize_key( wp_unslash( ( $_POST['draftcraft_csv_mode'] ?? 'append' ) ) )
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}//end ajax_import_csv()


	/**
	 * AJAX handler for keyword queue actions (retry single, retry all failed, delete, clear).
	 *
	 * @since 1.2.3
	 */
	public static function ajax_queue_action(): void {
		check_ajax_referer( 'draftcraft_queue_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'draftcraft' ) ) );
		}

		$action = sanitize_key( wp_unslash( ( $_POST['queue_action'] ?? '' ) ) );
		$row_id = sanitize_text_field( wp_unslash( ( $_POST['row_id'] ?? '' ) ) );
		$queue  = self::get_queue();
		$msg    = '';

		if ( 'clear' === $action ) {
			$queue = array();
			self::save_queue( $queue );
			$msg = __( 'Keyword queue cleared.', 'draftcraft' );
		} elseif ( 'delete' === $action && '' !== $row_id ) {
			$queue = array_values( array_filter( $queue, static fn( $r ) => ( $r['id'] ?? '' ) !== $row_id ) );
			self::save_queue( $queue );
			$msg = __( 'Queue item removed.', 'draftcraft' );
		} elseif ( 'retry_single' === $action && '' !== $row_id ) {
			foreach ( $queue as &$row ) {
				if ( ( $row['id'] ?? '' ) === $row_id ) {
					$row['status']      = 'pending';
					$row['fail_reason'] = '';
					break;
				}
			}

			unset( $row );
			self::save_queue( $queue );
			$msg = __( 'Item reset to pending.', 'draftcraft' );
		} elseif ( 'retry_failed' === $action ) {
			foreach ( $queue as &$row ) {
				if ( 'failed' === ( $row['status'] ?? '' ) ) {
					$row['status']      = 'pending';
					$row['fail_reason'] = '';
				}
			}

			unset( $row );
			self::save_queue( $queue );
			$msg = __( 'Failed items reset to pending.', 'draftcraft' );
		} else {
			wp_send_json_error( array( 'message' => __( 'Invalid action.', 'draftcraft' ) ) );
		}

		$pending = count( array_filter( $queue, static fn( $r ) => 'pending' === ( $r['status'] ?? 'pending' ) ) );
		$total   = count( $queue );

		wp_send_json_success(
			array(
				'queue_html' => self::get_queue_table_html( $queue ),
				'pending'    => $pending,
				'total'      => $total,
				'message'    => $msg,
			)
		);
	}//end ajax_queue_action()


	/**
	 * Parse uploaded CSV and merge into the keyword queue.
	 *
	 * @since  1.2.0
	 * @param  array  $file $_FILES entry.
	 * @param  string $mode append|replace.
	 * @return array|WP_Error
	 */
	private static function process_csv_upload( array $file, string $mode = 'append' ) {
		if ( empty( $file['tmp_name'] ) ) {
			return new WP_Error( 'no_file', __( 'No CSV file uploaded.', 'draftcraft' ) );
		}

		$tmp = $file['tmp_name'];
		if ( ! is_uploaded_file( $tmp ) ) {
			return new WP_Error( 'invalid_upload', __( 'Invalid upload.', 'draftcraft' ) );
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $size < 1 || $size > ( 2 * MB_IN_BYTES ) ) {
			return new WP_Error( 'file_size', __( 'CSV file must be under 2 MB.', 'draftcraft' ) );
		}

		$filename = sanitize_file_name( ( $file['name'] ?? '' ) );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'csv', 'txt' ), true ) ) {
			return new WP_Error( 'file_type', __( 'Invalid file type. Please upload a .csv file.', 'draftcraft' ) );
		}

     // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $tmp, 'r' );
		if ( false === $handle ) {
			return new WP_Error( 'read_fail', __( 'Could not read the uploaded file.', 'draftcraft' ) );
		}

		$header = fgetcsv( $handle );
		if ( ! is_array( $header ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'empty', __( 'CSV appears empty.', 'draftcraft' ) );
		}

		if ( isset( $header[0] ) ) {
			$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header[0] );
		}

		$header_map = self::map_headers( $header );
		if ( ! isset( $header_map['keyword'] ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'no_keyword', __( 'CSV must include a "Keyword" column.', 'draftcraft' ) );
		}

		$new_rows = array();
		$data     = fgetcsv( $handle );

		while ( false !== $data ) {
			if ( count( $new_rows ) >= self::MAX_ROWS ) {
				break;
			}

			$keyword = sanitize_text_field( ( $data[ $header_map['keyword'] ] ?? '' ) );
			if ( '' === $keyword ) {
				continue;
			}

			$category     = sanitize_text_field( ( $data[ $header_map['category'] ] ?? '' ) );
			$instructions = isset( $header_map['instructions'] ) ? sanitize_textarea_field( ( $data[ $header_map['instructions'] ] ?? '' ) ) : '';
			$scheduled    = isset( $header_map['scheduled'] ) ? sanitize_text_field( ( $data[ $header_map['scheduled'] ] ?? '' ) ) : '';

			if ( $scheduled ) {
				$ts        = strtotime( $scheduled );
				$scheduled = $ts ? wp_date( 'Y-m-d', $ts ) : '';
			}

			$new_rows[] = array(
				'id'             => uniqid( 'dc_', true ),
				'keyword'        => $keyword,
				'category'       => $category,
				'category_id'    => self::resolve_category_id( $category ),
				'instructions'   => $instructions,
				'scheduled_date' => $scheduled,
				'status'         => 'pending',
				'imported_at'    => current_time( 'mysql' ),
			);

			$data = fgetcsv( $handle );
		}//end while

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( empty( $new_rows ) ) {
			return new WP_Error( 'no_rows', __( 'No valid keywords found in the CSV.', 'draftcraft' ) );
		}

		$mode  = ( 'replace' === $mode ) ? 'replace' : 'append';
		$queue = ( 'replace' === $mode ) ? array() : self::get_queue();
		$queue = array_merge( $queue, $new_rows );

		if ( count( $queue ) > self::MAX_ROWS ) {
			$queue = array_slice( $queue, 0, self::MAX_ROWS );
		}

		self::save_queue( $queue );

		/*
		 * Fires after a CSV keyword import.
		 *
		 * @since 1.2.0
		 * @param array $new_rows Imported rows.
		 * @param array $queue    Full queue after import.
		 */
		do_action( 'draftcraft_csv_imported', $new_rows, $queue );

		$pending = count( array_filter( $queue, static fn( $r ) => ( $r['status'] ?? 'pending' ) === 'pending' ) );
		$message = sprintf(
			/* translators: %d: number of rows imported. */
			__( 'Imported %d keyword(s) into the queue.', 'draftcraft' ),
			count( $new_rows )
		);

		return array(
			'message'    => $message,
			'imported'   => count( $new_rows ),
			'pending'    => $pending,
			'total'      => count( $queue ),
			'queue_html' => self::get_queue_table_html( $queue ),
		);
	}//end process_csv_upload()


	/**
	 * Build queue table inner HTML for AJAX refresh.
	 *
	 * @since  1.2.0
	 * @param  array|null $queue Queue rows (defaults to stored queue).
	 * @return string
	 */
	public static function get_queue_table_html( ?array $queue = null ): string {
		$queue = null === $queue ? self::get_queue() : $queue;
		ob_start();

		if ( empty( $queue ) ) {
			?>
			<div class="draftcraft-empty-state" style="text-align:center;padding:32px;background:#fafafa;border:1px dashed #ccc;border-radius:6px;">
				<p><?php esc_html_e( 'No keywords yet. Import a CSV to get started.', 'draftcraft' ); ?></p>
			</div>
			<?php
		} else {
			?>
			<table class="wp-list-table widefat fixed striped" style="border:none;box-shadow:none;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Keyword', 'draftcraft' ); ?></th>
						<th><?php esc_html_e( 'Category', 'draftcraft' ); ?></th>
						<th><?php esc_html_e( 'Scheduled', 'draftcraft' ); ?></th>
						<th><?php esc_html_e( 'Status', 'draftcraft' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'draftcraft' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( array_slice( $queue, 0, 50 ) as $row ) :
						$del_url       = wp_nonce_url(
							add_query_arg(
								array(
									'page'   => 'draftcraft',
									'tab'    => 'seo',
									'draftcraft_queue_action' => 'delete',
									'row_id' => ( $row['id'] ?? '' ),
								),
								admin_url( 'admin.php' )
							),
							'draftcraft_queue_action'
						);
						$retry_row_url = wp_nonce_url(
							add_query_arg(
								array(
									'page'   => 'draftcraft',
									'tab'    => 'seo',
									'draftcraft_queue_action' => 'retry_single',
									'row_id' => ( $row['id'] ?? '' ),
								),
								admin_url( 'admin.php' )
							),
							'draftcraft_queue_action'
						);
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( ( $row['keyword'] ?? '' ) ); ?></strong>
								<?php if ( ! empty( $row['instructions'] ) ) : ?>
									<br><span style="color:#666;font-size:12px;"><?php echo esc_html( wp_trim_words( $row['instructions'], 12 ) ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( ! empty( $row['category'] ) ? $row['category'] : '—' ); ?></td>
							<td><?php echo esc_html( ! empty( $row['scheduled_date'] ) ? $row['scheduled_date'] : __( 'ASAP', 'draftcraft' ) ); ?></td>
							<td>
								<?php
								$draftcraft_row_st   = ( $row['status'] ?? 'pending' );
								$draftcraft_row_pill = 'done' === $draftcraft_row_st ? 'ok' : ( 'failed' === $draftcraft_row_st ? 'warn' : 'info' );
								$draftcraft_fail_msg = ( ! empty( $row['fail_reason'] ) ? $row['fail_reason'] : '' );
								?>
								<span class="draftcraft-pill draftcraft-pill--<?php echo esc_attr( $draftcraft_row_pill ); ?>"<?php echo '' !== $draftcraft_fail_msg ? ' title="' . esc_attr( $draftcraft_fail_msg ) . '" style="cursor:help;"' : ''; ?>>
									<?php echo esc_html( ucfirst( $draftcraft_row_st ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( 'failed' === $draftcraft_row_st ) : ?>
									<a href="<?php echo esc_url( $retry_row_url ); ?>" class="draftcraft-queue-action-link" data-action="retry_single" data-row-id="<?php echo esc_attr( (string) ( $row['id'] ?? '' ) ); ?>" style="color:var(--dc-primary); font-size:12px; text-decoration:none; margin-right:8px;" title="<?php esc_attr_e( 'Retry generating this keyword', 'draftcraft' ); ?>">
										<span class="dashicons dashicons-update" style="font-size:14px; width:14px; height:14px; vertical-align:text-bottom;"></span>
										<?php esc_html_e( 'Retry', 'draftcraft' ); ?>
									</a>
								<?php endif; ?>
								<a href="<?php echo esc_url( $del_url ); ?>" class="draftcraft-queue-action-link draftcraft-queue-action-link--danger" data-action="delete" data-row-id="<?php echo esc_attr( (string) ( $row['id'] ?? '' ) ); ?>" style="color:var(--dc-red); font-size:12px; text-decoration:none;" title="<?php esc_attr_e( 'Remove from queue', 'draftcraft' ); ?>">
									<span class="dashicons dashicons-trash" style="font-size:14px; width:14px; height:14px; vertical-align:text-bottom;"></span>
									<?php esc_html_e( 'Remove', 'draftcraft' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( count( $queue ) > 50 ) : ?>
				<p class="draftcraft-description">
					<?php
					printf(
						/* translators: %d: total number of queue items. */
						esc_html__( 'Showing 50 of %d items.', 'draftcraft' ),
						count( $queue )
					);
					?>
				</p>
			<?php endif; ?>
			<?php
		}//end if

		return (string) ob_get_clean();
	}//end get_queue_table_html()


	/**
	 * Handle clear / delete-row queue actions.
	 *
	 * @since 1.2.0
	 */
	public static function handle_queue_actions(): void {
     // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Presence check only; nonce verified immediately below.
		$has_queue_action = isset( $_GET['draftcraft_queue_action'] );
     // phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! $has_queue_action ) {
			return;
		}

		check_admin_referer( 'draftcraft_queue_action' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'draftcraft' ) );
		}

		$action = sanitize_key( wp_unslash( $_GET['draftcraft_queue_action'] ) );

		if ( 'clear' === $action ) {
			self::save_queue( array() );
			self::redirect_with_notice( 'success', __( 'Keyword queue cleared.', 'draftcraft' ) );
		}

		if ( 'delete' === $action && isset( $_GET['row_id'] ) ) {
			$row_id = sanitize_text_field( wp_unslash( $_GET['row_id'] ) );
			$queue  = self::get_queue();
			$queue  = array_values( array_filter( $queue, static fn( $r ) => ( $r['id'] ?? '' ) !== $row_id ) );
			self::save_queue( $queue );
			self::redirect_with_notice( 'success', __( 'Queue item removed.', 'draftcraft' ) );
		}

		if ( 'retry_single' === $action && isset( $_GET['row_id'] ) ) {
			$row_id = sanitize_text_field( wp_unslash( $_GET['row_id'] ) );
			$queue  = self::get_queue();
			foreach ( $queue as &$row ) {
				if ( ( $row['id'] ?? '' ) === $row_id ) {
					$row['status']      = 'pending';
					$row['fail_reason'] = '';
					break;
				}
			}

			unset( $row );
			self::save_queue( $queue );
			self::redirect_with_notice( 'success', __( 'Item reset to pending.', 'draftcraft' ) );
		}

		if ( 'retry_failed' === $action ) {
			$queue = self::get_queue();
			foreach ( $queue as &$row ) {
				if ( 'failed' === ( $row['status'] ?? '' ) ) {
					$row['status']      = 'pending';
					$row['fail_reason'] = '';
				}
			}

			unset( $row );
			self::save_queue( $queue );
			self::redirect_with_notice( 'success', __( 'Failed items reset to pending.', 'draftcraft' ) );
		}
	}//end handle_queue_actions()


	/**
	 * Map flexible CSV headers to internal keys.
	 *
	 * @since  1.2.0
	 * @param  array $header Header row.
	 * @return array<string, int>
	 */
	private static function map_headers( array $header ): array {
		$map = array();
		foreach ( $header as $i => $col ) {
			$key = strtolower( trim( (string) $col ) );
			$key = str_replace( array( ' ', '-', '.' ), '_', $key );

			if ( in_array( $key, array( 'keyword', 'keywords', 'focus_keyword', 'kw' ), true ) ) {
				$map['keyword'] = $i;
			} elseif ( in_array( $key, array( 'target_category', 'category', 'cat', 'categories' ), true ) ) {
				$map['category'] = $i;
			} elseif ( in_array( $key, array( 'specific_instructions', 'instructions', 'notes', 'prompt' ), true ) ) {
				$map['instructions'] = $i;
			} elseif ( in_array( $key, array( 'scheduled_date', 'schedule', 'date', 'publish_date' ), true ) ) {
				$map['scheduled'] = $i;
			}
		}

		return $map;
	}//end map_headers()


	/**
	 * Resolve a category name, slug, or ID to a term ID.
	 * Names are matched case-insensitively.
	 *
	 * @since  1.2.0
	 * @param  string $category Category name, slug, or numeric ID.
	 * @return integer
	 */
	private static function resolve_category_id( string $category ): int {
		$category = trim( $category );
		if ( '' === $category ) {
			return 0;
		}

		$taxonomy = function_exists( 'draftcraft_get_target_taxonomy' ) ? draftcraft_get_target_taxonomy() : 'category';

		// Numeric ID (e.g. "12").
		if ( is_numeric( $category ) ) {
			$term = get_term( absint( $category ), $taxonomy );
			return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
		}

		// Exact name, then slug.
		$term = get_term_by( 'name', $category, $taxonomy );
		if ( ! $term ) {
			$term = get_term_by( 'slug', sanitize_title( $category ), $taxonomy );
		}

		// Case-insensitive name match (e.g. "reviews" → "Reviews").
		if ( ! $term ) {
			$candidates = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'name__like' => $category,
					'number'     => 10,
					'hide_empty' => false,
				)
			);
			if ( ! is_wp_error( $candidates ) && is_array( $candidates ) ) {
				$needle = strtolower( $category );
				foreach ( $candidates as $candidate ) {
					if ( strtolower( (string) $candidate->name ) === $needle ) {
						$term = $candidate;
						break;
					}
				}
			}
		}

		return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
	}//end resolve_category_id()


	/**
	 * Redirect back to the Bulk tab with a notice.
	 *
	 * @since 1.2.0
	 * @param string $type    success|error.
	 * @param string $message Notice message.
	 */
	private static function redirect_with_notice( string $type, string $message ): void {
		set_transient(
			'draftcraft_bulk_notice',
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'draftcraft',
					'tab'  => 'seo',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}//end redirect_with_notice()


	/**
	 * Display admin notice from transient (DraftCraft screen only).
	 *
	 * @since 1.2.0
	 */
	public static function maybe_show_notice(): void {
     // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin screen gate.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
     // phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( 'draftcraft' !== $page ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || false === strpos( (string) $screen->id, 'draftcraft' ) ) {
				return;
			}
		}

		$notice = get_transient( 'draftcraft_bulk_notice' );
		if ( ! $notice || ! is_array( $notice ) ) {
			return;
		}

		delete_transient( 'draftcraft_bulk_notice' );
		$class = ( 'error' === ( $notice['type'] ?? '' ) ) ? 'notice-error' : 'notice-success';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible draftcraft-notice"><p>' . esc_html( ( $notice['message'] ?? '' ) ) . '</p></div>';
	}//end maybe_show_notice()


	/**
	 * Build prompt additions from a queue row.
	 *
	 * @since  1.2.0
	 * @param  array $row Queue row.
	 * @return array{system_extra:string,user_extra:string,category_ids:int[]}
	 */
	public static function build_prompt_from_row( array $row ): array {
		$keyword      = ( $row['keyword'] ?? '' );
		$instructions = ( $row['instructions'] ?? '' );
		$cat_id       = absint( ( $row['category_id'] ?? 0 ) );

		$system_extra = '';
		$user_extra   = '';

		if ( $keyword ) {
			$system_extra .= sprintf(
				"\n\nPrimary target keyword: \"%s\". The post MUST be optimized around this keyword naturally.",
				$keyword
			);
			$user_extra   .= sprintf(
				' Write a unique, high-quality blog post focused on the keyword: "%s".',
				$keyword
			);
		}

		if ( $instructions ) {
			$system_extra .= "\n\nAdditional editorial instructions: " . $instructions;
			$user_extra   .= ' Additional instructions: ' . $instructions;
		}

		$category_ids = $cat_id > 0 ? array( $cat_id ) : array();

		return array(
			'system_extra'  => $system_extra,
			'user_extra'    => $user_extra,
			'category_ids'  => $category_ids,
			'focus_keyword' => $keyword,
		);
	}//end build_prompt_from_row()


	/**
	 * Render the Bulk CSV admin panel HTML.
	 *
	 * @since 1.2.0
	 */
	public static function render_admin_panel(): void {
		self::render_seo_keywords_section();
		self::render_queue_table();
	}//end render_admin_panel()


	/**
	 * SEO sync + CSV import settings.
	 *
	 * @since 1.2.0
	 */
	public static function render_seo_keywords_section(): void {
		$queue      = self::get_queue();
		$settings   = function_exists( 'draftcraft_get_settings' ) ? draftcraft_get_settings() : array();
		$pending    = count( array_filter( $queue, static fn( $r ) => ( $r['status'] ?? 'pending' ) === 'pending' ) );
		$total      = count( $queue );
		$clear_url  = wp_nonce_url(
			add_query_arg(
				array(
					'page'                    => 'draftcraft',
					'tab'                     => 'seo',
					'draftcraft_queue_action' => 'clear',
				),
				admin_url( 'admin.php' )
			),
			'draftcraft_queue_action'
		);
		$retry_url  = wp_nonce_url(
			add_query_arg(
				array(
					'page'                    => 'draftcraft',
					'tab'                     => 'seo',
					'draftcraft_queue_action' => 'retry_failed',
				),
				admin_url( 'admin.php' )
			),
			'draftcraft_queue_action'
		);
		$sample_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'                  => 'draftcraft',
					'tab'                   => 'seo',
					'draftcraft_sample_csv' => '1',
				),
				admin_url( 'admin.php' )
			),
			'draftcraft_sample_csv'
		);
		?>

		<?php
		// ── Section 1: SEO Plugin Sync ──
		?>
		<div class="draftcraft-section">
			<div class="draftcraft-section-header">
				<h2><?php esc_html_e( 'SEO Plugin Sync', 'draftcraft' ); ?></h2>
				<p><?php esc_html_e( 'DraftCraft can automatically write AI-generated SEO metadata directly into your active SEO plugin after each article is created.', 'draftcraft' ); ?></p>
			</div>
			<div class="draftcraft-section-body">

				<div class="draftcraft-cron-info" style="margin-bottom:16px;">
					<span class="dashicons dashicons-plugins-checked"></span>
					<?php
					printf(
						/* translators: %s: detected SEO plugin name. */
						esc_html__( 'Active SEO Plugin Detected: %s', 'draftcraft' ),
						'<strong>' . esc_html( class_exists( 'DraftCraft_SEO' ) ? DraftCraft_SEO::get_active_plugin_label() : __( 'None', 'draftcraft' ) ) . '</strong>'
					);
					?>
				</div>

				<div class="draftcraft-feature-card">
					<div class="draftcraft-feature-card-header">
						<div class="draftcraft-feature-card-info">
							<strong>
								<span class="dashicons dashicons-tag" style="color:var(--dc-primary);"></span>
								<?php esc_html_e( 'Write SEO Metadata After Generation', 'draftcraft' ); ?>
								<span class="draftcraft-tooltip-wrap">
									<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
									<span class="draftcraft-tooltip-bubble">
										<?php esc_html_e( 'Automatically fills in Focus Keyword, Meta Title, and Meta Description in Rank Math, Yoast SEO, or AIOSEO immediately after each post is created.', 'draftcraft' ); ?>
									</span>
								</span>
							</strong>
							<p class="draftcraft-description"><?php esc_html_e( 'Populates focus keyword, meta title, and meta description in your SEO plugin after every AI-generated article. Supports Rank Math, Yoast SEO, and AIOSEO.', 'draftcraft' ); ?></p>
						</div>
						<label class="draftcraft-switch" for="draftcraft_seo_sync_enabled">
							<input type="checkbox" id="draftcraft_seo_sync_enabled" name="draftcraft_seo_sync_enabled" value="1" <?php checked( '1', ( $settings['seo_sync_enabled'] ?? '0' ) ); ?>>
							<span class="draftcraft-switch-track"><span class="draftcraft-switch-thumb"></span></span>
						</label>
					</div>
				</div>

			</div>
		</div>

		<?php
		// ── Section 2: Keyword-Driven Content Queue ──
		?>
		<div class="draftcraft-section">
			<div class="draftcraft-section-header">
				<h2><?php esc_html_e( 'Keyword-Driven Content Queue', 'draftcraft' ); ?></h2>
				<p><?php esc_html_e( 'Upload a list of target keywords. DraftCraft will write a dedicated, SEO-optimized article for each keyword — before falling back to category rotation.', 'draftcraft' ); ?></p>
			</div>
			<div class="draftcraft-section-body">

				<?php
				// ── How it works callout ──
				?>
				<div class="draftcraft-how-it-works">
					<div class="draftcraft-how-it-works-title">
						<span class="dashicons dashicons-lightbulb"></span>
						<?php esc_html_e( 'How keyword priority works', 'draftcraft' ); ?>
					</div>
					<div class="draftcraft-how-it-works-flow">
						<div class="draftcraft-flow-step draftcraft-flow-step--active">
							<span class="dashicons dashicons-list-view"></span>
							<span><?php esc_html_e( 'Keywords in Queue', 'draftcraft' ); ?></span>
						</div>
						<div class="draftcraft-flow-arrow">→</div>
						<div class="draftcraft-flow-step draftcraft-flow-step--active">
							<span class="dashicons dashicons-yes-alt"></span>
							<span><?php esc_html_e( 'Written First', 'draftcraft' ); ?></span>
						</div>
						<div class="draftcraft-flow-divider">|</div>
						<div class="draftcraft-flow-step">
							<span class="dashicons dashicons-arrow-right-alt2"></span>
							<span><?php esc_html_e( 'Queue Empty', 'draftcraft' ); ?></span>
						</div>
						<div class="draftcraft-flow-arrow">→</div>
						<div class="draftcraft-flow-step">
							<span class="dashicons dashicons-controls-repeat"></span>
							<span><?php esc_html_e( 'Category Rotation', 'draftcraft' ); ?></span>
						</div>
					</div>
					<p class="draftcraft-description draftcraft-how-it-works-desc" style="margin-top:14px;">
						<?php esc_html_e( 'Each row in your CSV becomes one article. The keyword becomes the article\'s primary focus, overriding the category rotation until all keywords are written.', 'draftcraft' ); ?>
					</p>
				</div>

				<?php
				// ── Priority Toggle ──
				?>
				<div class="draftcraft-feature-card" style="margin-top:0;">
					<div class="draftcraft-feature-card-header">
						<div class="draftcraft-feature-card-info">
							<strong>
								<span class="dashicons dashicons-sort" style="color:var(--dc-primary);"></span>
								<?php esc_html_e( 'Prioritize Keyword Queue Over Category Rotation', 'draftcraft' ); ?>
								<span class="draftcraft-tooltip-wrap">
									<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
									<span class="draftcraft-tooltip-bubble">
										<?php esc_html_e( 'When ON: DraftCraft works through all queued keywords before resuming category rotation. When OFF: keywords and category rotation run interchangeably.', 'draftcraft' ); ?>
									</span>
								</span>
							</strong>
							<p class="draftcraft-description"><?php esc_html_e( 'Process all pending keywords from your queue before resuming automatic category rotation.', 'draftcraft' ); ?></p>
						</div>
						<label class="draftcraft-switch" for="draftcraft_bulk_queue_priority">
							<input type="checkbox" id="draftcraft_bulk_queue_priority" name="draftcraft_bulk_queue_priority" value="1" <?php checked( '1', ( $settings['bulk_queue_priority'] ?? '1' ) ); ?>>
							<span class="draftcraft-switch-track"><span class="draftcraft-switch-thumb"></span></span>
						</label>
					</div>
				</div>

				<?php
				// ── CSV Import Box ──
				?>
				<div class="draftcraft-csv-box">
					<div class="draftcraft-csv-box-header">
						<span class="dashicons dashicons-upload"></span>
						<strong><?php esc_html_e( 'Import Keywords via CSV', 'draftcraft' ); ?></strong>
					</div>
					<div class="draftcraft-csv-grid">
						<div class="draftcraft-field">
							<label class="draftcraft-label" for="draftcraft_csv_file">
								<?php esc_html_e( 'CSV File', 'draftcraft' ); ?>
								<span class="draftcraft-tooltip-wrap">
									<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
									<span class="draftcraft-tooltip-bubble">
										<?php esc_html_e( 'CSV columns: Keyword, Target Category (name or ID), Instructions (optional), Scheduled Date YYYY-MM-DD (optional). Max 500 rows.', 'draftcraft' ); ?>
									</span>
								</span>
							</label>
							<input type="file"
									id="draftcraft_csv_file"
									name="draftcraft_csv_file"
									accept=".csv,text/csv"
									class="draftcraft-input draftcraft-file-input">
						</div>
						<div class="draftcraft-field">
							<label class="draftcraft-label" for="draftcraft_csv_mode">
								<?php esc_html_e( 'Import Mode', 'draftcraft' ); ?>
								<span class="draftcraft-tooltip-wrap">
									<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
									<span class="draftcraft-tooltip-bubble">
										<?php esc_html_e( 'Append: adds new keywords to the bottom of the existing queue. Replace: clears the queue and starts fresh with the new file.', 'draftcraft' ); ?>
									</span>
								</span>
							</label>
							<select id="draftcraft_csv_mode" name="draftcraft_csv_mode" class="draftcraft-select">
								<option value="append"><?php esc_html_e( 'Append — add to existing queue', 'draftcraft' ); ?></option>
								<option value="replace"><?php esc_html_e( 'Replace — clear queue and start fresh', 'draftcraft' ); ?></option>
							</select>
						</div>
					</div>
					<div class="draftcraft-csv-footer">
						<div class="draftcraft-csv-hints">
							<p class="draftcraft-description">
								<?php esc_html_e( 'Max 500 rows. Date format: YYYY-MM-DD. Category: name or ID.', 'draftcraft' ); ?>
							</p>
							<a href="<?php echo esc_url( $sample_url ); ?>" class="draftcraft-csv-sample-link">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Download Example CSV', 'draftcraft' ); ?>
							</a>
						</div>
						<button type="button" id="draftcraft-import-csv" class="draftcraft-btn draftcraft-btn--primary">
							<span class="dashicons dashicons-upload"></span>
							<?php esc_html_e( 'Import CSV', 'draftcraft' ); ?>
						</button>
					</div>
					<p id="draftcraft-csv-import-status" class="draftcraft-description" role="status" aria-live="polite"></p>
				</div>

				<?php
				// ── Queue Status Bar ──
				?>
				<div class="draftcraft-queue-status-bar" id="draftcraft-queue-stats">
					<div class="draftcraft-queue-status-counts">
						<span class="dashicons dashicons-media-spreadsheet" style="color:var(--dc-primary);"></span>
						<span id="draftcraft-queue-stats-text">
						<?php
						printf(
							/* translators: 1: pending count, 2: total count. */
							esc_html__( '%1$d pending · %2$d total in queue', 'draftcraft' ),
							(int) $pending,
							(int) $total
						);
						?>
						</span>
					</div>
					<div class="draftcraft-queue-status-actions">
						<a href="<?php echo esc_url( $retry_url ); ?>" class="draftcraft-queue-action-link" data-action="retry_failed">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Retry Failed', 'draftcraft' ); ?>
						</a>
						<span class="draftcraft-cat-action-sep">•</span>
						<a href="<?php echo esc_url( $clear_url ); ?>" class="draftcraft-queue-action-link draftcraft-queue-action-link--danger" data-action="clear" onclick="return confirm('<?php echo esc_js( __( 'This will permanently clear the entire keyword queue. Continue?', 'draftcraft' ) ); ?>');">
							<span class="dashicons dashicons-trash"></span>
							<?php esc_html_e( 'Clear Queue', 'draftcraft' ); ?>
						</a>
					</div>
				</div>

			</div>
		</div>
		<?php
	}//end render_seo_keywords_section()


	/**
	 * Keyword queue list table.
	 *
	 * @since 1.2.0
	 */
	public static function render_queue_table(): void {
		?>
		<div class="draftcraft-section">
			<div class="draftcraft-section-header">
				<h2><?php esc_html_e( 'Keyword Queue', 'draftcraft' ); ?></h2>
				<p><?php esc_html_e( 'Next generation run picks the next due item.', 'draftcraft' ); ?></p>
			</div>
			<div class="draftcraft-section-body" id="draftcraft-queue-body">
				<?php
       // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built with escaping inside get_queue_table_html().
				echo self::get_queue_table_html();
       // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
		</div>
		<?php
	}//end render_queue_table()
}//end class

