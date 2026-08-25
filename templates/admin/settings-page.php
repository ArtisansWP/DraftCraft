<?php
/**
 * DraftCraft — Main Admin Settings Page View
 *
 * @package DraftCraft
 * @since   1.2.1
 *
 * @var array         $settings        Plugin settings array.
 * @var array         $tabs            List of navigation tabs.
 * @var string        $active_tab      Currently active tab.
 * @var string        $masked_key      Masked API key string.
 * @var array         $categories      Category terms array.
 * @var string        $target_taxonomy Active taxonomy name.
 * @var int|bool      $next_cron       Next cron timestamp or false.
 * @var WP_Term|null  $rot_term        Next rotation term or null.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap draftcraft-wrap" id="draftcraft-app">
	<h1 class="draftcraft-screen-title"><?php esc_html_e( 'DraftCraft', 'draftcraft' ); ?></h1>

	<?php
	// Plugin Header.
	?>
	<div class="draftcraft-header">
		<div class="draftcraft-header-brand">
			<div class="draftcraft-header-text">
				<p class="draftcraft-header-title"><?php esc_html_e( 'DraftCraft', 'draftcraft' ); ?></p>
				<span class="draftcraft-header-by"><?php esc_html_e( 'by ArtisansWP', 'draftcraft' ); ?></span>
			</div>
		</div>
		<div class="draftcraft-header-meta">
			<div id="draftcraft-unsaved-badge" class="draftcraft-unsaved-pill" style="display:none;">
				<span class="draftcraft-status-pip draftcraft-pip-unsaved"></span>
				<?php esc_html_e( 'Unsaved Changes', 'draftcraft' ); ?>
			</div>
			<span class="draftcraft-version-badge">v<?php echo esc_html( DRAFTCRAFT_VERSION ); ?></span>
			<div class="draftcraft-status-pill <?php echo $next_cron ? 'is-active' : 'is-inactive'; ?>">
				<span class="draftcraft-status-pip"></span>
				<?php
				echo $next_cron ? esc_html__( 'Automation On', 'draftcraft' ) : esc_html__( 'Automation Off', 'draftcraft' );
				?>
			</div>
		</div>
	</div>

	<?php
	// Tab Navigation.
	?>
	<div class="draftcraft-nav">
		<?php foreach ( $tabs as $draftcraft_key => $draftcraft_tab ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $draftcraft_key, menu_page_url( 'draftcraft', false ) ) ); ?>"
				class="draftcraft-nav-item <?php echo $active_tab === $draftcraft_key ? 'is-active' : ''; ?>"
				data-tab="<?php echo esc_attr( $draftcraft_key ); ?>">
				<span class="dashicons <?php echo esc_attr( $draftcraft_tab['icon'] ); ?>"></span>
				<?php echo esc_html( $draftcraft_tab['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<?php
	// Main Body Section.
	?>
	<div class="draftcraft-body">
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin.php?page=draftcraft&tab=' . $active_tab ) ); ?>" id="draftcraft-form" class="draftcraft-content" novalidate>
			<?php wp_nonce_field( DRAFTCRAFT_NONCE_SETTINGS, 'draftcraft_nonce' ); ?>

			<?php
			// TAB 1: API & Model.
			draftcraft_render_template(
				'admin/tabs/tab-api',
				array(
					'settings'   => $settings,
					'masked_key' => $masked_key,
					'active_tab' => $active_tab,
				)
			);

			// TAB 2: Content.
			draftcraft_render_template(
				'admin/tabs/tab-content',
				array(
					'settings'        => $settings,
					'categories'      => $categories,
					'target_taxonomy' => $target_taxonomy,
					'active_tab'      => $active_tab,
				)
			);

			// TAB 3: SEO.
			draftcraft_render_template(
				'admin/tabs/tab-seo',
				array(
					'settings'   => $settings,
					'active_tab' => $active_tab,
				)
			);

			// TAB 4: Automation Schedule.
			draftcraft_render_template(
				'admin/tabs/tab-schedule',
				array(
					'settings'   => $settings,
					'next_cron'  => $next_cron,
					'active_tab' => $active_tab,
				)
			);

			// TAB 5: AI Drafts Queue.
			draftcraft_render_template(
				'admin/tabs/tab-drafts',
				array(
					'settings'   => $settings,
					'active_tab' => $active_tab,
				)
			);
			?>
		</form><!-- /#draftcraft-form -->

		<?php
		// Sidebar is outside the settings form so Quick Generate can never submit/save.
		draftcraft_render_template(
			'admin/sidebar',
			array(
				'settings'  => $settings,
				'next_cron' => $next_cron,
				'rot_term'  => $rot_term,
			)
		);
		?>

	</div><!-- /.draftcraft-body -->

	<!-- Custom Toast Notification -->
	<div id="draftcraft-toast" class="draftcraft-toast">
		<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
			<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
		</svg>
		<span class="draftcraft-toast-msg"><?php esc_html_e( 'Settings saved successfully.', 'draftcraft' ); ?></span>
	</div>

</div><!-- /#draftcraft-app -->
