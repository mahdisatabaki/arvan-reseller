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
 * RTL/Persian is a standing product requirement (CLAUDE.md, DESIGN.md §4)
 * for every plugin-owned screen, not just this one — `dir="rtl"`/`lang="fa"`
 * are set on the wrapper explicitly rather than relying on the site's own
 * admin locale, so this renders correctly even on an English-locale
 * WordPress install.
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
	1 => __( 'توکن دسترسی', 'arvan-reseller' ),
	2 => __( 'کلید API', 'arvan-reseller' ),
	3 => __( 'پروفایل کسب‌وکار', 'arvan-reseller' ),
	4 => __( 'نرخ سود و چرخه عمر', 'arvan-reseller' ),
	5 => __( 'پایان', 'arvan-reseller' ),
];
?>
<div class="wrap arvan-setup-wizard" dir="rtl" lang="fa">
	<style>
		.arvan-setup-wizard { direction: rtl; text-align: right; }
		.arvan-setup-wizard .arvan-wizard-progress { display: flex; gap: 1.5em; list-style: none; padding-right: 0; }
		.arvan-setup-wizard .form-table th { text-align: right; padding-right: 0; }
		.arvan-setup-wizard .form-table td { text-align: right; }
		.arvan-setup-wizard input[type="text"],
		.arvan-setup-wizard input[type="url"],
		.arvan-setup-wizard input[type="email"],
		.arvan-setup-wizard input[type="password"],
		.arvan-setup-wizard input[type="number"],
		.arvan-setup-wizard textarea,
		.arvan-setup-wizard select { direction: rtl; text-align: right; }
	</style>

	<h1><?php esc_html_e( 'افزونه ریسلر آروان — راه‌اندازی', 'arvan-reseller' ); ?></h1>

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

			<p><?php esc_html_e( 'توکن دسترسی دریافت‌شده از آروان را وارد کنید تا اتصال حساب ریسلر شما تأیید شود.', 'arvan-reseller' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="access_token"><?php esc_html_e( 'توکن دسترسی آروان', 'arvan-reseller' ); ?></label></th>
					<td>
						<input type="text" id="access_token" name="access_token" class="regular-text" autocomplete="off" required
							<?php disabled( $rateLimited ); ?> />
					</td>
				</tr>
			</table>
			<?php if ( $rateLimited ) : ?>
				<p class="description"><?php esc_html_e( 'تعداد تلاش‌های ناموفق بیش از حد مجاز است. لطفاً کمی صبر کنید و دوباره امتحان کنید.', 'arvan-reseller' ); ?></p>
			<?php endif; ?>

		<?php elseif ( 2 === $step ) : ?>

			<p><?php esc_html_e( 'کلید API آروان‌کلود خود را وارد کنید. پیش از ذخیره، تست و رمزنگاری می‌شود و دیگر هرگز نمایش داده نخواهد شد.', 'arvan-reseller' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="label"><?php esc_html_e( 'برچسب', 'arvan-reseller' ); ?></label></th>
					<td><input type="text" id="label" name="label" class="regular-text" required /></td>
				</tr>
				<tr>
					<th><label for="purpose"><?php esc_html_e( 'کاربرد', 'arvan-reseller' ); ?></label></th>
					<td>
						<select id="purpose" name="purpose">
							<option value="cdn"><?php esc_html_e( 'CDN', 'arvan-reseller' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="api_key"><?php esc_html_e( 'کلید API', 'arvan-reseller' ); ?></label></th>
					<td>
						<!-- Never pre-filled, even after a failed test (SECURITY.md §4). -->
						<input type="password" id="api_key" name="api_key" class="regular-text" autocomplete="off" value="" required />
					</td>
				</tr>
			</table>

		<?php elseif ( 3 === $step ) : ?>

			<table class="form-table" role="presentation">
				<tr>
					<th><label for="name"><?php esc_html_e( 'نام کسب‌وکار', 'arvan-reseller' ); ?></label></th>
					<td><input type="text" id="name" name="name" class="regular-text" required
						value="<?php echo esc_attr( $businessProfile['name'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="logo_url"><?php esc_html_e( 'آدرس لوگو', 'arvan-reseller' ); ?></label></th>
					<td><input type="url" id="logo_url" name="logo_url" class="regular-text"
						value="<?php echo esc_attr( $businessProfile['logo_url'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="website"><?php esc_html_e( 'وب‌سایت', 'arvan-reseller' ); ?></label></th>
					<td><input type="url" id="website" name="website" class="regular-text"
						value="<?php echo esc_attr( $businessProfile['website'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="email"><?php esc_html_e( 'ایمیل تماس', 'arvan-reseller' ); ?></label></th>
					<td><input type="email" id="email" name="email" class="regular-text"
						value="<?php echo esc_attr( $businessProfile['email'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="phone"><?php esc_html_e( 'شماره تماس', 'arvan-reseller' ); ?></label></th>
					<td><input type="text" id="phone" name="phone" class="regular-text"
						value="<?php echo esc_attr( $businessProfile['phone'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="about"><?php esc_html_e( 'درباره ما', 'arvan-reseller' ); ?></label></th>
					<td><textarea id="about" name="about" class="large-text" rows="4"><?php echo esc_textarea( $businessProfile['about'] ); ?></textarea></td>
				</tr>
			</table>

		<?php elseif ( 4 === $step ) : ?>

			<table class="form-table" role="presentation">
				<tr>
					<th><label for="markup_percent"><?php esc_html_e( 'نرخ سود (حداکثر ۲۰٪)', 'arvan-reseller' ); ?></label></th>
					<td>
						<input type="number" id="markup_percent" name="markup_percent" min="0" max="20" step="0.1"
							value="<?php echo esc_attr( (string) $markupPercent ); ?>" />
						<p class="description">
							<?php
							printf(
								/* translators: %1$d: example base price, %2$d: example customer price */
								esc_html__( 'مثال: قیمت پایه %1$d + ۲۰٪ سود = قیمت مشتری %2$d.', 'arvan-reseller' ),
								100,
								120
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="notify_threshold_toman"><?php esc_html_e( 'هشدار اعتبار کم (تومان)', 'arvan-reseller' ); ?></label></th>
					<td><input type="number" id="notify_threshold_toman" name="notify_threshold_toman" min="0"
						value="<?php echo esc_attr( (string) intdiv( $lifecyclePolicy['notify_threshold_rial'], 10 ) ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="resume_threshold_toman"><?php esc_html_e( 'آستانه‌ی از سرگیری (تومان)', 'arvan-reseller' ); ?></label></th>
					<td><input type="number" id="resume_threshold_toman" name="resume_threshold_toman" min="0"
						value="<?php echo esc_attr( (string) intdiv( $lifecyclePolicy['resume_threshold_rial'], 10 ) ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="terminate_grace_days"><?php esc_html_e( 'مهلت خاتمه‌ی سرویس (روز)', 'arvan-reseller' ); ?></label></th>
					<td><input type="number" id="terminate_grace_days" name="terminate_grace_days" min="0"
						value="<?php echo esc_attr( (string) $lifecyclePolicy['terminate_grace_days'] ); ?>" /></td>
				</tr>
			</table>

		<?php elseif ( 5 === $step ) : ?>

			<p><?php esc_html_e( 'خلاصه‌ی تنظیمات شما. مقادیر این صفحه قابل تغییر نیستند؛ بعداً از بخش تنظیمات می‌توانید آن‌ها را ویرایش کنید.', 'arvan-reseller' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'کسب‌وکار', 'arvan-reseller' ); ?></th>
					<td><?php echo esc_html( '' !== $businessProfile['name'] ? $businessProfile['name'] : __( '(تنظیم نشده)', 'arvan-reseller' ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'نرخ سود', 'arvan-reseller' ); ?></th>
					<td>
						<?php
						printf(
							/* translators: %s: markup percentage */
							esc_html__( '%s٪', 'arvan-reseller' ),
							esc_html( (string) $markupPercent )
						);
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'هشدار اعتبار کم', 'arvan-reseller' ); ?></th>
					<td>
						<?php
						printf(
							/* translators: %s: amount in Toman */
							esc_html__( '%s تومان', 'arvan-reseller' ),
							esc_html( (string) intdiv( $lifecyclePolicy['notify_threshold_rial'], 10 ) )
						);
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'آستانه‌ی از سرگیری', 'arvan-reseller' ); ?></th>
					<td>
						<?php
						printf(
							/* translators: %s: amount in Toman */
							esc_html__( '%s تومان', 'arvan-reseller' ),
							esc_html( (string) intdiv( $lifecyclePolicy['resume_threshold_rial'], 10 ) )
						);
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'مهلت خاتمه‌ی سرویس', 'arvan-reseller' ); ?></th>
					<td>
						<?php
						printf(
							/* translators: %s: number of days */
							esc_html__( '%s روز', 'arvan-reseller' ),
							esc_html( (string) $lifecyclePolicy['terminate_grace_days'] )
						);
						?>
					</td>
				</tr>
			</table>

		<?php endif; ?>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php echo esc_html( $step === $lastStep ? __( 'پایان', 'arvan-reseller' ) : __( 'ادامه', 'arvan-reseller' ) ); ?>
			</button>
		</p>
	</form>
</div>
