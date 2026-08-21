<?php
/**
 * Setup Wizard view.
 *
 * Rendered by SetupWizard::renderTemplate() via `require`, which runs in
 * that method's own scope — every variable referenced below ($step, $error,
 * $furthestStep, $businessProfile, $lifecyclePolicy, $markupPercent,
 * $rateLimited, $nonceAction, $pageSlug, $lastStep) is set there, not in
 * this file. No business logic here — validation and persistence already
 * happened in the controller before this ever renders. render() is never
 * called once the wizard is complete (SetupWizard::handleRequest() redirects
 * away first), so there is no "complete" state to render here at all.
 *
 * Every dynamic value is escaped at the point it is printed (SECURITY.md
 * §9). The API key field is never pre-filled — see step 2 below.
 *
 * @package ArvanReseller
 *
 * @var int $step
 * @var string|null $error
 * @var int $furthestStep
 * @var array{name:string, logo_url:string, website:string, email:string, phone:string, about:string} $businessProfile
 * @var array{notify_threshold_rial:int, resume_threshold_rial:int, terminate_grace_days:int} $lifecyclePolicy
 * @var float $markupPercent
 * @var bool $rateLimited
 * @var string $nonceAction
 * @var string $pageSlug
 * @var int $lastStep
 */

defined( 'ABSPATH' ) || exit;

$step_labels = [
	1 => __( 'Access Token', 'arvan-reseller' ),
	2 => __( 'API Key', 'arvan-reseller' ),
	3 => __( 'Business Profile', 'arvan-reseller' ),
	4 => __( 'Markup & Lifecycle', 'arvan-reseller' ),
	5 => __( 'Finish', 'arvan-reseller' ),
];
?>
<div class="wrap arvan-setup-wizard">
	<h1><?php esc_html_e( 'Arvan Reseller — Setup', 'arvan-reseller' ); ?></h1>

	<ol class="arvan-wizard-progress">
		<?php foreach ( $step_labels as $n => $label ) : ?>
			<?php
			$reachable = $n <= $furthestStep;
			$current   = $n === $step;
			?>
			<li<?php echo $current ? ' aria-current="step"' : ''; ?>>
				<?php if ( $reachable ) : ?>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => $pageSlug, 'step' => $n ], admin_url( 'admin.php' ) ) ); ?>">
						<?php echo esc_html( (string) $n . '. ' . $label ); ?>
					</a>
				<?php else : ?>
					<span><?php echo esc_html( (string) $n . '. ' . $label ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>

	<?php if ( null !== $error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( add_query_arg( [ 'page' => $pageSlug, 'step' => $step ], admin_url( 'admin.php' ) ) ); ?>">
		<?php wp_nonce_field( $nonceAction ); ?>

		<?php if ( 1 === $step ) : ?>

			<p><?php esc_html_e( 'Enter the demo Access Token provided by the hackathon team.', 'arvan-reseller' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="access_token"><?php esc_html_e( 'Access Token', 'arvan-reseller' ); ?></label></th>
					<td>
						<input type="text" id="access_token" name="access_token" class="regular-text" autocomplete="off" required
							<?php disabled( $rateLimited ); ?> />
					</td>
				</tr>
			</table>
			<?php if ( $rateLimited ) : ?>
				<p class="description"><?php esc_html_e( 'Too many failed attempts. Please wait before trying again.', 'arvan-reseller' ); ?></p>
			<?php endif; ?>

		<?php elseif ( 2 === $step ) : ?>

			<p><?php esc_html_e( 'Add your ArvanCloud API Key. It will be tested and encrypted before it is stored — it is never shown again.', 'arvan-reseller' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="label"><?php esc_html_e( 'Label', 'arvan-reseller' ); ?></label></th>
					<td><input type="text" id="label" name="label" class="regular-text" required /></td>
				</tr>
				<tr>
					<th><label for="purpose"><?php esc_html_e( 'Purpose', 'arvan-reseller' ); ?></label></th>
					<td>
						<select id="purpose" name="purpose">
							<option value="cdn"><?php esc_html_e( 'CDN', 'arvan-reseller' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="api_key"><?php esc_html_e( 'API Key', 'arvan-reseller' ); ?></label></th>
					<td>
						<!-- Never pre-filled, even after a failed test (SECURITY.md §4). -->
						<input type="password" id="api_key" name="api_key" class="regular-text" autocomplete="off" value="" required />
					</td>
				</tr>
			</table>

		<?php elseif ( 3 === $step ) : ?>

			<table class="form-table" role="presentation">
				<tr>
					<th><label for="name"><?php esc_html_e( 'Business Name', 'arvan-reseller' ); ?></label></th>
					<td><input type="text" id="name" name="name" class="regular-text" required
						value="<?php echo esc_attr( $businessProfile['name'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="logo_url"><?php esc_html_e( 'Logo URL', 'arvan-reseller' ); ?></label></th>
					<td><input type="url" id="logo_url" name="logo_url" class="regular-text"
						value="<?php echo esc_attr( $businessProfile['logo_url'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="website"><?php esc_html_e( 'Website', 'arvan-reseller' ); ?></label></th>
					<td><input type="url" id="website" name="website" class="regular-text"
						value="<?php echo esc_attr( $businessProfile['website'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="email"><?php esc_html_e( 'Contact Email', 'arvan-reseller' ); ?></label></th>
					<td><input type="email" id="email" name="email" class="regular-text"
						value="<?php echo esc_attr( $businessProfile['email'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="phone"><?php esc_html_e( 'Contact Phone', 'arvan-reseller' ); ?></label></th>
					<td><input type="text" id="phone" name="phone" class="regular-text"
						value="<?php echo esc_attr( $businessProfile['phone'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="about"><?php esc_html_e( 'About', 'arvan-reseller' ); ?></label></th>
					<td><textarea id="about" name="about" class="large-text" rows="4"><?php echo esc_textarea( $businessProfile['about'] ); ?></textarea></td>
				</tr>
			</table>

		<?php elseif ( 4 === $step ) : ?>

			<table class="form-table" role="presentation">
				<tr>
					<th><label for="markup_percent"><?php esc_html_e( 'Markup (max 20%)', 'arvan-reseller' ); ?></label></th>
					<td>
						<input type="number" id="markup_percent" name="markup_percent" min="0" max="20" step="0.1"
							value="<?php echo esc_attr( (string) $markupPercent ); ?>" />
						<p class="description">
							<?php
							printf(
								/* translators: %d: example base price */
								esc_html__( 'Example: base %1$d + 20%% markup = customer price %2$d.', 'arvan-reseller' ),
								100,
								120
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="notify_threshold_toman"><?php esc_html_e( 'Low Balance Notice (Toman)', 'arvan-reseller' ); ?></label></th>
					<td><input type="number" id="notify_threshold_toman" name="notify_threshold_toman" min="0"
						value="<?php echo esc_attr( (string) intdiv( $lifecyclePolicy['notify_threshold_rial'], 10 ) ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="resume_threshold_toman"><?php esc_html_e( 'Resume Threshold (Toman)', 'arvan-reseller' ); ?></label></th>
					<td><input type="number" id="resume_threshold_toman" name="resume_threshold_toman" min="0"
						value="<?php echo esc_attr( (string) intdiv( $lifecyclePolicy['resume_threshold_rial'], 10 ) ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="terminate_grace_days"><?php esc_html_e( 'Termination Grace Period (days)', 'arvan-reseller' ); ?></label></th>
					<td><input type="number" id="terminate_grace_days" name="terminate_grace_days" min="0"
						value="<?php echo esc_attr( (string) $lifecyclePolicy['terminate_grace_days'] ); ?>" /></td>
				</tr>
			</table>

		<?php elseif ( 5 === $step ) : ?>

			<p><?php esc_html_e( 'Review what you set up. Nothing here can be changed on this screen — visit Settings later to update it.', 'arvan-reseller' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Business', 'arvan-reseller' ); ?></th>
					<td><?php echo esc_html( '' !== $businessProfile['name'] ? $businessProfile['name'] : __( '(not set)', 'arvan-reseller' ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Markup', 'arvan-reseller' ); ?></th>
					<td>
						<?php
						printf(
							/* translators: %s: markup percentage */
							esc_html__( '%s%%', 'arvan-reseller' ),
							esc_html( (string) $markupPercent )
						);
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Low Balance Notice', 'arvan-reseller' ); ?></th>
					<td>
						<?php
						printf(
							/* translators: %s: amount in Toman */
							esc_html__( '%s Toman', 'arvan-reseller' ),
							esc_html( (string) intdiv( $lifecyclePolicy['notify_threshold_rial'], 10 ) )
						);
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Resume Threshold', 'arvan-reseller' ); ?></th>
					<td>
						<?php
						printf(
							/* translators: %s: amount in Toman */
							esc_html__( '%s Toman', 'arvan-reseller' ),
							esc_html( (string) intdiv( $lifecyclePolicy['resume_threshold_rial'], 10 ) )
						);
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Termination Grace Period', 'arvan-reseller' ); ?></th>
					<td>
						<?php
						printf(
							/* translators: %s: number of days */
							esc_html__( '%s days', 'arvan-reseller' ),
							esc_html( (string) $lifecyclePolicy['terminate_grace_days'] )
						);
						?>
					</td>
				</tr>
			</table>

		<?php endif; ?>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php echo esc_html( $step === $lastStep ? __( 'Finish', 'arvan-reseller' ) : __( 'Continue', 'arvan-reseller' ) ); ?>
			</button>
		</p>
	</form>
</div>
