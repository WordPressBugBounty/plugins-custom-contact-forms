<?php
/**
 * Forms > Integrations: one card per connected service, free and Pro.
 *
 * Each card shows what the service does, whether it is free or needs Pro,
 * and its status, and opens to show its settings. Free services are set up
 * here. Custom Contact Forms Pro replaces its placeholder cards with live
 * ones through the ccf_integration_cards filter.
 *
 * @package Custom_Contact_Forms
 * @since   7.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCF_Integrations_Page {

	const SLUG = 'ccf-integrations';

	/**
	 * @var string
	 */
	private $hook = '';

	public function setup() {
		add_action( 'admin_menu', array( $this, 'menu' ), 15 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function menu() {
		$this->hook = add_submenu_page(
			'edit.php?post_type=ccf_form',
			esc_html__( 'Integrations', 'custom-contact-forms' ),
			esc_html__( 'Integrations', 'custom-contact-forms' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			3
		);
	}

	/**
	 * @param string $hook Current admin page.
	 */
	public function enqueue( $hook ) {
		if ( $hook !== $this->hook ) {
			return;
		}

		wp_enqueue_style( 'ccf-integrations', plugins_url( '/build/css/ccf-integrations.css', dirname( __FILE__ ) ), array(), CCF_VERSION );
		wp_enqueue_script( 'ccf-integrations', plugins_url( '/assets/js/ccf-integrations-page.js', dirname( __FILE__ ) ), array(), CCF_VERSION, true );
		wp_localize_script(
			'ccf-integrations',
			'ccfIntegrationsPage',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'on'    => __( 'On', 'custom-contact-forms' ),
					'off'   => __( 'Off', 'custom-contact-forms' ),
					'error' => __( 'That did not save. Reload the page and try again.', 'custom-contact-forms' ),
				),
			)
		);

		/**
		 * Fires when the Integrations page loads its scripts, so add-ons can
		 * load what their card settings need.
		 *
		 * @since 7.18.0
		 */
		do_action( 'ccf_integrations_enqueue' );
	}

	/**
	 * Card groups, in order.
	 *
	 * @return array
	 */
	private function categories() {
		return array(
			'email'      => __( 'Email marketing', 'custom-contact-forms' ),
			'automation' => __( 'Automation', 'custom-contact-forms' ),
			'spam'       => __( 'Spam protection', 'custom-contact-forms' ),
			'payments'   => __( 'Payments', 'custom-contact-forms' ),
			'more'       => __( 'Messages and addresses', 'custom-contact-forms' ),
		);
	}

	/**
	 * All cards. Pro cards are placeholders until Pro replaces them.
	 *
	 * @return array
	 */
	public function cards() {
		$cards = array();
		$em    = CCF_Email_Marketing::factory();

		// Email marketing. Mailchimp is free; the rest come with Pro.
		$email_cards = array(
			'mailchimp'      => array( 'Mailchimp', __( 'Add people who fill in a form to a Mailchimp audience, with double opt-in.', 'custom-contact-forms' ), '#e8a400', 'free' ),
			'mailerlite'     => array( 'MailerLite', __( 'Add subscribers to a MailerLite group.', 'custom-contact-forms' ), '#09a35b', 'pro' ),
			'brevo'          => array( 'Brevo', __( 'Add contacts to a Brevo list.', 'custom-contact-forms' ), '#0b8f68', 'pro' ),
			'kit'            => array( 'Kit', __( 'Add subscribers to a Kit form (formerly ConvertKit).', 'custom-contact-forms' ), '#e8585a', 'pro' ),
			'activecampaign' => array( 'ActiveCampaign', __( 'Add contacts to an ActiveCampaign list.', 'custom-contact-forms' ), '#356ae6', 'pro' ),
			'mailpoet'       => array( 'MailPoet', __( 'Add subscribers to a MailPoet list on this site. No API key needed.', 'custom-contact-forms' ), '#e8590c', 'pro' ),
		);

		foreach ( $email_cards as $id => $info ) {
			$provider = $em->provider( $id );
			$card     = array(
				'id'          => $id,
				'name'        => $info[0],
				'description' => $info[1],
				'color'       => $info[2],
				'icon'        => 'email-alt',
				'tier'        => $info[3],
				'category'    => 'email',
			);

			if ( $provider ) {
				$card['status'] = $em->status( $provider );
				$card['group']  = 'ccf-int-' . $id;
				$card['render'] = function () use ( $em, $provider ) {
					$em->render_settings( $provider );
				};
			}

			$cards[ $id ] = $card;
		}

		// Webhooks.
		$hooked = $this->forms_with_webhooks();
		$cards['webhooks'] = array(
			'id'          => 'webhooks',
			'name'        => __( 'Webhooks', 'custom-contact-forms' ),
			'description' => __( 'Send each new entry to Zapier, Make, n8n or any URL, and on to thousands of apps.', 'custom-contact-forms' ),
			'color'       => '#3858e9',
			'icon'        => 'randomize',
			'tier'        => 'free',
			'category'    => 'automation',
			'status'      => $hooked
				? array(
					'state' => 'ok',
					/* translators: %d: number of forms */
					'label' => sprintf( _n( 'On %d form', 'On %d forms', $hooked, 'custom-contact-forms' ), $hooked ),
				)
				: array(
					'state' => 'off',
					'label' => __( 'Not used yet', 'custom-contact-forms' ),
				),
			'render'      => function () {
				echo '<p>' . esc_html__( 'Webhooks are set per form. Open a form, go to Form Settings, and paste one or more URLs under Webhooks. Use "Send a test" so Zapier and similar tools can see your fields.', 'custom-contact-forms' ) . '</p>';
				echo '<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=ccf_form' ) ) . '">' . esc_html__( 'Go to your forms', 'custom-contact-forms' ) . '</a></p>';
			},
		);

		// Spam protection.
		$spam = CCF_Spam::factory();
		$cards['akismet'] = array(
			'id'          => 'akismet',
			'name'        => 'Akismet',
			'description' => __( 'Check entries with Akismet. Flagged ones go to a spam folder you can review.', 'custom-contact-forms' ),
			'color'       => '#2e7d32',
			'icon'        => 'shield',
			'tier'        => 'free',
			'category'    => 'spam',
			'status'      => $spam->akismet_enabled()
				? array( 'state' => 'ok', 'label' => __( 'On', 'custom-contact-forms' ) )
				: ( $spam->akismet_available()
					? array( 'state' => 'off', 'label' => __( 'Off', 'custom-contact-forms' ) )
					: array( 'state' => 'off', 'label' => __( 'Needs the Akismet plugin', 'custom-contact-forms' ) ) ),
			'group'       => 'ccf-int-akismet',
			'render'      => array( $spam, 'render_settings' ),
		);

		$hcaptcha = CCF_Hcaptcha::factory();
		$cards['hcaptcha'] = array(
			'id'          => 'hcaptcha',
			'name'        => 'hCaptcha',
			'description' => __( 'Ask visitors to prove they are human before a form sends.', 'custom-contact-forms' ),
			'color'       => '#0074bf',
			'icon'        => 'lock',
			'tier'        => 'free',
			'category'    => 'spam',
			'status'      => $hcaptcha->is_active() ? array( 'state' => 'ok', 'label' => __( 'On', 'custom-contact-forms' ) ) : array( 'state' => 'off', 'label' => __( 'Off', 'custom-contact-forms' ) ),
			'group'       => 'ccf-int-hcaptcha',
			'render'      => array( $hcaptcha, 'render_settings' ),
		);

		$turnstile = CCF_Turnstile::factory();
		$cards['turnstile'] = array(
			'id'          => 'turnstile',
			'name'        => 'Cloudflare Turnstile',
			'description' => __( 'Block bots without making visitors solve puzzles.', 'custom-contact-forms' ),
			'color'       => '#f38020',
			'icon'        => 'lock',
			'tier'        => 'free',
			'category'    => 'spam',
			'status'      => $turnstile->is_active() ? array( 'state' => 'ok', 'label' => __( 'On', 'custom-contact-forms' ) ) : array( 'state' => 'off', 'label' => __( 'Off', 'custom-contact-forms' ) ),
			'group'       => 'ccf-int-turnstile',
			'render'      => array( $turnstile, 'render_settings' ),
		);

		$cards['recaptcha'] = array(
			'id'          => 'recaptcha',
			'name'        => 'Google reCAPTCHA',
			'description' => __( 'Add a reCAPTCHA field to any form in the form builder.', 'custom-contact-forms' ),
			'color'       => '#4285f4',
			'icon'        => 'lock',
			'tier'        => 'free',
			'category'    => 'spam',
			'status'      => array( 'state' => 'info', 'label' => __( 'Set up per form', 'custom-contact-forms' ) ),
			'render'      => function () {
				echo '<p>' . esc_html__( 'In the form builder, drag the reCAPTCHA field from Special Fields into your form and add your site key and secret key there.', 'custom-contact-forms' ) . '</p>';
			},
		);

		// Pro modules: placeholders until Pro supplies live cards.
		$pro_cards = array(
			'stripe' => array( 'Stripe', __( 'Take card, Apple Pay and Google Pay payments right on your form.', 'custom-contact-forms' ), '#635bff', 'cart', 'payments' ),
			'paypal' => array( 'PayPal', __( 'Let people pay with PayPal without leaving the page.', 'custom-contact-forms' ), '#003087', 'money-alt', 'payments' ),
			'twilio' => array( 'Twilio SMS', __( 'Text a confirmation the moment someone books or pays.', 'custom-contact-forms' ), '#f22f46', 'smartphone', 'more' ),
			'places' => array( 'Address Autocomplete', __( 'People find their address as they type, and the rest fills itself in.', 'custom-contact-forms' ), '#1a73e8', 'location', 'more' ),
		);

		foreach ( $pro_cards as $id => $info ) {
			$cards[ $id ] = array(
				'id'          => $id,
				'name'        => $info[0],
				'description' => $info[1],
				'color'       => $info[2],
				'icon'        => $info[3],
				'tier'        => 'pro',
				'category'    => $info[4],
			);
		}

		/**
		 * Filter the Integrations cards. Custom Contact Forms Pro replaces the
		 * Pro placeholders with live cards using the same IDs.
		 *
		 * Card keys: id, name, description, color, icon (Dashicon name) or
		 * logo (inline SVG), tier (free|pro), category, status (state and
		 * label), group (settings group saved through options.php), render
		 * (callable that prints the settings), toggle (enabled, action,
		 * nonce, module) for cards with an on/off switch.
		 *
		 * @since 7.18.0
		 * @param array $cards Cards keyed by ID.
		 */
		return apply_filters( 'ccf_integration_cards', $cards );
	}

	/**
	 * Number of forms that send webhooks.
	 *
	 * @return int
	 */
	private function forms_with_webhooks() {
		$count = 0;
		$forms = get_posts(
			array(
				'post_type'      => 'ccf_form',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'meta_key'       => CCF_Webhooks::FORM_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);

		foreach ( $forms as $form_id ) {
			if ( CCF_Webhooks::factory()->urls( $form_id ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Logo for a card: an official logo file the site owner added, if any.
	 *
	 * Drop a file named after the card ID, such as mailchimp.svg or
	 * stripe.png, into assets/images/integrations/ to show it on the card.
	 *
	 * @param string $id Card ID.
	 * @return string URL or empty.
	 */
	private function logo_url( $id ) {
		$url = '';

		foreach ( array( 'svg', 'png', 'webp' ) as $ext ) {
			$file = 'assets/images/integrations/' . $id . '.' . $ext;

			if ( file_exists( CCF_PLUGIN_DIR . $file ) ) {
				$url = plugins_url( $file, CCF_PLUGIN_DIR . 'custom-contact-forms.php' );
				break;
			}
		}

		/**
		 * Filter the logo shown on an Integrations card.
		 *
		 * @since 7.18.0
		 * @param string $url Logo URL, or empty to show the colored badge.
		 * @param string $id  Card ID.
		 */
		return (string) apply_filters( 'ccf_integration_logo_url', $url, $id );
	}

	/**
	 * Short letters for the colored badge when there is no logo file.
	 *
	 * @param string $name Integration name.
	 * @return string
	 */
	private function monogram( $name ) {
		$words = preg_split( '/[\s]+/', trim( preg_replace( '/[^A-Za-z0-9 ]/', ' ', $name ) ) );

		if ( count( $words ) > 1 ) {
			return strtoupper( substr( $words[0], 0, 1 ) . substr( $words[1], 0, 1 ) );
		}

		// Split CamelCase names such as MailPoet or ActiveCampaign.
		if ( preg_match_all( '/[A-Z]/', $name, $caps ) && count( $caps[0] ) > 1 ) {
			return $caps[0][0] . $caps[0][1];
		}

		return strtoupper( substr( $name, 0, 1 ) );
	}

	public function render() {
		$cards      = $this->cards();
		$categories = $this->categories();
		$open       = isset( $_GET['open'] ) ? sanitize_key( wp_unslash( $_GET['open'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$has_pro    = defined( 'CCFP_VERSION' ) && version_compare( CCFP_VERSION, '1.2.0', '>=' );
		$old_pro    = defined( 'CCFP_VERSION' ) && ! $has_pro;

		// One grid: by category, free before Pro within each.
		$order = array_flip( array_keys( $categories ) );
		uasort(
			$cards,
			function ( $a, $b ) use ( $order ) {
				$ca = isset( $order[ $a['category'] ] ) ? $order[ $a['category'] ] : 99;
				$cb = isset( $order[ $b['category'] ] ) ? $order[ $b['category'] ] : 99;

				if ( $ca !== $cb ) {
					return $ca - $cb;
				}

				return ( 'pro' === $a['tier'] ? 1 : 0 ) - ( 'pro' === $b['tier'] ? 1 : 0 );
			}
		);
		?>
		<div class="wrap ccf-int-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Integrations', 'custom-contact-forms' ); ?></h1>
			<hr class="wp-header-end">

			<?php settings_errors(); ?>

			<p class="ccf-int-intro">
				<?php esc_html_e( 'Connect your forms to the services you already use. Free integrations are included. Pro ones come with Custom Contact Forms Pro.', 'custom-contact-forms' ); ?>
			</p>

			<div class="ccf-int-toolbar">
				<div class="ccf-int-filters" role="group" aria-label="<?php esc_attr_e( 'Category', 'custom-contact-forms' ); ?>">
					<button type="button" class="ccf-int-filter is-active" data-group="category" data-filter="all"><?php esc_html_e( 'All', 'custom-contact-forms' ); ?></button>
					<?php foreach ( $categories as $category => $label ) : ?>
						<button type="button" class="ccf-int-filter" data-group="category" data-filter="<?php echo esc_attr( $category ); ?>"><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
				</div>
				<div class="ccf-int-filters ccf-int-segmented" role="group" aria-label="<?php esc_attr_e( 'Show', 'custom-contact-forms' ); ?>">
					<button type="button" class="ccf-int-filter is-active" data-group="tier" data-filter="all"><?php esc_html_e( 'Any', 'custom-contact-forms' ); ?></button>
					<button type="button" class="ccf-int-filter" data-group="tier" data-filter="free"><?php esc_html_e( 'Free', 'custom-contact-forms' ); ?></button>
					<button type="button" class="ccf-int-filter" data-group="tier" data-filter="pro"><?php esc_html_e( 'Pro', 'custom-contact-forms' ); ?></button>
					<button type="button" class="ccf-int-filter" data-group="tier" data-filter="active"><?php esc_html_e( 'Connected', 'custom-contact-forms' ); ?></button>
				</div>
			</div>

			<div class="ccf-int-grid">
				<?php foreach ( $cards as $card ) : ?>
					<?php $this->render_card( $card, $categories, $open, $has_pro, $old_pro ); ?>
				<?php endforeach; ?>
			</div>
			<p class="ccf-int-none" hidden><?php esc_html_e( 'Nothing matches these filters.', 'custom-contact-forms' ); ?></p>

			<section class="ccf-int-problems" id="ccf-problems">
				<h2><?php esc_html_e( 'Recent problems', 'custom-contact-forms' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Why a subscriber, webhook or payment did not go through, newest first.', 'custom-contact-forms' ); ?></p>
				<?php CCF_Integration_Log::render(); ?>
			</section>

			<div class="ccf-int-overlay" hidden></div>
		</div>
		<?php
	}

	/**
	 * Logo or colored badge for a card.
	 *
	 * @param array $card Card.
	 */
	private function render_mark( $card ) {
		$logo = $this->logo_url( $card['id'] );

		if ( $logo ) {
			echo '<span class="ccf-int-mark ccf-int-mark-logo"><img src="' . esc_url( $logo ) . '" alt="" loading="lazy"></span>';
			return;
		}

		echo '<span class="ccf-int-mark" style="--ccf-brand:' . esc_attr( $card['color'] ) . ';">';
		echo '<span class="ccf-int-mono" aria-hidden="true">' . esc_html( $this->monogram( $card['name'] ) ) . '</span>';
		echo '</span>';
	}

	/**
	 * One card.
	 *
	 * @param array  $card       Card.
	 * @param array  $categories Category labels.
	 * @param string $open       Card to open.
	 * @param bool   $has_pro    Pro 1.2+ active.
	 * @param bool   $old_pro    An older Pro is active.
	 */
	private function render_card( $card, $categories, $open, $has_pro, $old_pro ) {
		$id      = sanitize_key( $card['id'] );
		$is_pro  = 'pro' === $card['tier'];
		$live    = ! empty( $card['render'] ) || ! empty( $card['toggle'] );
		$status  = isset( $card['status'] ) ? $card['status'] : null;
		$toggle  = isset( $card['toggle'] ) ? $card['toggle'] : null;
		$enabled = $toggle ? ! empty( $toggle['enabled'] ) : true;
		$is_open = $open === $id && ! empty( $card['render'] ) && $enabled;
		$active  = ( $status && 'ok' === $status['state'] ) || ( $toggle && $enabled );
		$cat     = isset( $categories[ $card['category'] ] ) ? $categories[ $card['category'] ] : '';
		?>
		<div class="ccf-int-card<?php echo $is_open ? ' is-open' : ''; ?>" id="ccf-int-<?php echo esc_attr( $id ); ?>" data-tier="<?php echo esc_attr( $is_pro ? 'pro' : 'free' ); ?>" data-category="<?php echo esc_attr( $card['category'] ); ?>" data-active="<?php echo $active ? '1' : '0'; ?>">
			<div class="ccf-int-head">
				<?php $this->render_mark( $card ); ?>
				<div class="ccf-int-titles">
					<h3 class="ccf-int-name"><?php echo esc_html( $card['name'] ); ?></h3>
					<span class="ccf-int-cat"><?php echo esc_html( $cat ); ?></span>
				</div>
				<span class="ccf-int-badge ccf-int-badge-<?php echo $is_pro ? 'pro' : 'free'; ?>"><?php echo $is_pro ? esc_html__( 'Pro', 'custom-contact-forms' ) : esc_html__( 'Free', 'custom-contact-forms' ); ?></span>
			</div>

			<p class="ccf-int-desc"><?php echo esc_html( $card['description'] ); ?></p>

			<div class="ccf-int-foot">
				<?php if ( $toggle ) : ?>
					<span class="ccf-int-switch">
						<button type="button" class="ccf-int-toggle" role="switch" aria-checked="<?php echo $enabled ? 'true' : 'false'; ?>"
							data-action="<?php echo esc_attr( $toggle['action'] ); ?>"
							data-nonce="<?php echo esc_attr( $toggle['nonce'] ); ?>"
							data-module="<?php echo esc_attr( $toggle['module'] ); ?>"
							aria-label="<?php echo esc_attr( sprintf( /* translators: %s: integration name */ __( 'Turn %s on or off', 'custom-contact-forms' ), $card['name'] ) ); ?>">
							<span class="ccf-int-knob"></span>
						</button>
						<span class="ccf-int-status ccf-int-status-<?php echo $enabled ? 'ok' : 'off'; ?>"><?php echo $enabled ? esc_html__( 'On', 'custom-contact-forms' ) : esc_html__( 'Off', 'custom-contact-forms' ); ?></span>
					</span>
				<?php elseif ( $status ) : ?>
					<span class="ccf-int-status ccf-int-status-<?php echo esc_attr( $status['state'] ); ?>"><?php echo esc_html( $status['label'] ); ?></span>
				<?php elseif ( $is_pro && ! $live ) : ?>
					<?php if ( $old_pro ) : ?>
						<a class="ccf-int-upgrade" href="<?php echo esc_url( admin_url( 'edit.php?post_type=ccf_form&page=ccfp' ) ); ?>"><?php esc_html_e( 'Update Pro to use this', 'custom-contact-forms' ); ?></a>
					<?php else : ?>
						<a class="ccf-int-upgrade" href="<?php echo esc_url( ccf_pro_url( 'integrations-' . $id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade to Pro', 'custom-contact-forms' ); ?></a>
					<?php endif; ?>
				<?php else : ?>
					<span></span>
				<?php endif; ?>

				<?php if ( ! empty( $card['render'] ) ) : ?>
					<button type="button" class="button ccf-int-open" aria-haspopup="dialog" aria-controls="ccf-int-drawer-<?php echo esc_attr( $id ); ?>" <?php echo ( $toggle && ! $enabled ) ? 'hidden' : ''; ?>>
						<?php echo ( $active || $toggle ) ? esc_html__( 'Settings', 'custom-contact-forms' ) : esc_html__( 'Set up', 'custom-contact-forms' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $card['render'] ) ) : ?>
				<div class="ccf-int-drawer" id="ccf-int-drawer-<?php echo esc_attr( $id ); ?>" role="dialog" aria-modal="true" aria-labelledby="ccf-int-drawer-title-<?php echo esc_attr( $id ); ?>" <?php echo $is_open ? '' : 'hidden'; ?>>
					<div class="ccf-int-drawer-head">
						<?php $this->render_mark( $card ); ?>
						<h2 id="ccf-int-drawer-title-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $card['name'] ); ?></h2>
						<button type="button" class="ccf-int-close" aria-label="<?php esc_attr_e( 'Close', 'custom-contact-forms' ); ?>">&times;</button>
					</div>
					<div class="ccf-int-drawer-body">
						<p class="ccf-int-drawer-desc"><?php echo esc_html( $card['description'] ); ?></p>
						<?php if ( ! empty( $card['group'] ) ) : ?>
							<form method="post" action="options.php">
								<?php settings_fields( $card['group'] ); ?>
								<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( wp_unslash( add_query_arg( 'open', $id, remove_query_arg( array( 'open', 'settings-updated' ) ) ) ) ); ?>">
								<?php call_user_func( $card['render'] ); ?>
								<?php submit_button( __( 'Save', 'custom-contact-forms' ), 'primary', 'submit', true, array( 'id' => 'ccf-int-save-' . $id ) ); ?>
							</form>
						<?php else : ?>
							<?php call_user_func( $card['render'] ); ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @return CCF_Integrations_Page
	 */
	public static function factory() {
		static $instance;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}
