<?php
/**
 * Admin Settings view. Rendered by SettingsController::render() via
 * `require`, same composition convention as dashboard.php.
 *
 * @package ArvanReseller
 *
 * @var string $activeSlug
 * @var string $activeTab
 * @var array{name:string, logo_url:string, website:string, email:string, phone:string, about:string} $businessProfile
 * @var array<int, array<string, mixed>> $apiKeyRows
 * @var float $markupPercent
 * @var int $unitPriceToman
 * @var array{notify_threshold_rial:int, resume_threshold_rial:int, terminate_grace_days:int} $lifecyclePolicy
 * @var string $layout
 * @var string $errorCode
 * @var bool $saved
 * @var array<string, string> $actions
 */

use ArvanReseller\Domain\Money;
use ArvanReseller\Wp\Admin\AdminMenu;
use ArvanReseller\Wp\Admin\ResellerSettings;

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/partials/admin-header.php';

$tab_labels = [
	'business'  => __( 'کسب‌وکار', 'arvan-reseller' ),
	'api-keys'  => __( 'کلیدهای API', 'arvan-reseller' ),
	'pricing'   => __( 'قیمت‌گذاری', 'arvan-reseller' ),
	'lifecycle' => __( 'چرخه‌ی عمر', 'arvan-reseller' ),
	'layout'    => __( 'چیدمان', 'arvan-reseller' ),
];

$error_messages = [
	'invalid_markup'     => __( 'نرخ سود باید بین ۰٪ تا ۲۰٪ باشد.', 'arvan-reseller' ),
	'invalid_unit_price' => __( 'قیمت هر گیگابایت ترافیک نمی‌تواند منفی باشد.', 'arvan-reseller' ),
	'missing_key_fields' => __( 'برچسب و کلید API را وارد کنید.', 'arvan-reseller' ),
	'key_test_failed'    => __( 'اتصال با این کلید برقرار نشد. کلید را بررسی کنید.', 'arvan-reseller' ),
	'key_not_found'      => __( 'کلید موردنظر یافت نشد.', 'arvan-reseller' ),
];
?>

	<h1><?php esc_html_e( 'تنظیمات', 'arvan-reseller' ); ?></h1>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'تغییرات ذخیره شد.', 'arvan-reseller' ); ?></p></div>
	<?php endif; ?>

	<?php if ( '' !== $errorCode && isset( $error_messages[ $errorCode ] ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $error_messages[ $errorCode ] ); ?></p></div>
	<?php endif; ?>

	<ul class="arvan-admin-tabs">
		<?php foreach ( $tab_labels as $tab_key => $tab_label ) : ?>
			<li>
				<a class="<?php echo esc_attr( $activeTab === $tab_key ? 'is-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( [ 'page' => AdminMenu::SLUG_SETTINGS, 'tab' => $tab_key ], admin_url( 'admin.php' ) ) ); ?>">
					<?php echo esc_html( $tab_label ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="arvan-admin-card">

	<?php if ( 'business' === $activeTab ) : ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $actions['business'] ); ?>" />
			<?php wp_nonce_field( $actions['business'] ); ?>
			<table class="form-table">
				<tr>
					<th><label for="arvan-name"><?php esc_html_e( 'نام کسب‌وکار', 'arvan-reseller' ); ?></label></th>
					<td><input class="regular-text" type="text" id="arvan-name" name="name" value="<?php echo esc_attr( $businessProfile['name'] ); ?>" required /></td>
				</tr>
				<tr>
					<th><label for="arvan-logo"><?php esc_html_e( 'آدرس لوگو', 'arvan-reseller' ); ?></label></th>
					<td><input class="regular-text" type="url" id="arvan-logo" name="logo_url" value="<?php echo esc_attr( $businessProfile['logo_url'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="arvan-website"><?php esc_html_e( 'وب‌سایت', 'arvan-reseller' ); ?></label></th>
					<td><input class="regular-text" type="url" id="arvan-website" name="website" value="<?php echo esc_attr( $businessProfile['website'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="arvan-email"><?php esc_html_e( 'ایمیل', 'arvan-reseller' ); ?></label></th>
					<td><input class="regular-text" type="email" id="arvan-email" name="email" value="<?php echo esc_attr( $businessProfile['email'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="arvan-phone"><?php esc_html_e( 'تلفن', 'arvan-reseller' ); ?></label></th>
					<td><input class="regular-text" type="text" id="arvan-phone" name="phone" value="<?php echo esc_attr( $businessProfile['phone'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="arvan-about"><?php esc_html_e( 'درباره', 'arvan-reseller' ); ?></label></th>
					<td><textarea class="large-text" id="arvan-about" name="about" rows="4"><?php echo esc_textarea( $businessProfile['about'] ); ?></textarea></td>
				</tr>
			</table>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'ذخیره', 'arvan-reseller' ); ?></button>
		</form>

	<?php elseif ( 'api-keys' === $activeTab ) : ?>

		<?php if ( [] === $apiKeyRows ) : ?>
			<div class="arvan-admin-empty"><?php esc_html_e( 'هنوز هیچ کلید API ثبت نشده است.', 'arvan-reseller' ); ?></div>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'برچسب', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'کاربرد', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'کلید', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'پیش‌فرض', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'آخرین بررسی', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'عملیات', 'arvan-reseller' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $apiKeyRows as $key_row ) : ?>
						<?php $key_id = (int) $key_row['id']; ?>
						<tr>
							<td><?php echo esc_html( $key_row['label'] ); ?></td>
							<td><?php echo esc_html( $key_row['purpose'] ); ?></td>
							<td>••••<?php echo esc_html( $key_row['last_four'] ); ?></td>
							<td>
								<span class="arvan-admin-badge <?php echo esc_attr( 'active' === $key_row['status'] ? 'arvan-admin-badge--ok' : 'arvan-admin-badge--muted' ); ?>">
									<?php echo esc_html( 'active' === $key_row['status'] ? __( 'فعال', 'arvan-reseller' ) : __( 'غیرفعال', 'arvan-reseller' ) ); ?>
								</span>
							</td>
							<td><?php echo (int) $key_row['is_default'] === 1 ? esc_html__( 'بله', 'arvan-reseller' ) : ''; ?></td>
							<td><?php echo empty( $key_row['last_checked_at'] ) ? '—' : esc_html( mysql2date( 'Y/m/d H:i', $key_row['last_checked_at'] ) ); ?></td>
							<td>
								<form style="display:inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="<?php echo esc_attr( $actions['key_test'] ); ?>" />
									<input type="hidden" name="id" value="<?php echo esc_attr( $key_id ); ?>" />
									<?php wp_nonce_field( $actions['key_test'] ); ?>
									<button type="submit" class="button button-small"><?php esc_html_e( 'آزمایش', 'arvan-reseller' ); ?></button>
								</form>
								<form style="display:inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="<?php echo esc_attr( $actions['key_default'] ); ?>" />
									<input type="hidden" name="id" value="<?php echo esc_attr( $key_id ); ?>" />
									<?php wp_nonce_field( $actions['key_default'] ); ?>
									<button type="submit" class="button button-small"><?php esc_html_e( 'پیش‌فرض کردن', 'arvan-reseller' ); ?></button>
								</form>
								<form style="display:inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="<?php echo esc_attr( $actions['key_status'] ); ?>" />
									<input type="hidden" name="id" value="<?php echo esc_attr( $key_id ); ?>" />
									<?php wp_nonce_field( $actions['key_status'] ); ?>
									<button type="submit" class="button button-small"><?php echo 'active' === $key_row['status'] ? esc_html__( 'غیرفعال کردن', 'arvan-reseller' ) : esc_html__( 'فعال کردن', 'arvan-reseller' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'افزودن کلید جدید', 'arvan-reseller' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $actions['key_add'] ); ?>" />
			<?php wp_nonce_field( $actions['key_add'] ); ?>
			<table class="form-table">
				<tr>
					<th><label for="arvan-key-label"><?php esc_html_e( 'برچسب', 'arvan-reseller' ); ?></label></th>
					<td><input class="regular-text" type="text" id="arvan-key-label" name="label" required /></td>
				</tr>
				<tr>
					<th><label for="arvan-key-purpose"><?php esc_html_e( 'کاربرد', 'arvan-reseller' ); ?></label></th>
					<td><input class="regular-text" type="text" id="arvan-key-purpose" name="purpose" value="cdn" required /></td>
				</tr>
				<tr>
					<th><label for="arvan-key-value"><?php esc_html_e( 'کلید API', 'arvan-reseller' ); ?></label></th>
					<td><input class="regular-text" type="password" id="arvan-key-value" name="api_key" autocomplete="off" required /></td>
				</tr>
			</table>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'افزودن و آزمایش اتصال', 'arvan-reseller' ); ?></button>
		</form>

	<?php elseif ( 'pricing' === $activeTab ) : ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $actions['pricing'] ); ?>" />
			<?php wp_nonce_field( $actions['pricing'] ); ?>
			<table class="form-table">
				<tr>
					<th><label for="arvan-unit-price"><?php esc_html_e( 'قیمت هر گیگابایت ترافیک (تومان)', 'arvan-reseller' ); ?></label></th>
					<td><input type="number" min="0" step="1" id="arvan-unit-price" name="unit_price_toman_per_gb" value="<?php echo esc_attr( (string) $unitPriceToman ); ?>" required /></td>
				</tr>
				<tr>
					<th><label for="arvan-markup"><?php esc_html_e( 'نرخ سود (٪)', 'arvan-reseller' ); ?></label></th>
					<td><input type="number" min="0" max="20" step="0.1" id="arvan-markup" name="markup_percent" value="<?php echo esc_attr( (string) $markupPercent ); ?>" required /></td>
				</tr>
			</table>
			<p class="description">
				<?php
				printf(
					/* translators: 1: base price, 2: markup percent, 3: customer price */
					esc_html__( 'مثال: پایه %1$s + سود %2$s٪ = مبلغ نهایی مشتری %3$s', 'arvan-reseller' ),
					esc_html( number_format_i18n( 100 ) ),
					esc_html( number_format_i18n( $markupPercent ) ),
					esc_html( number_format_i18n( 100 + ( 100 * $markupPercent / 100 ) ) )
				);
				?>
			</p>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'ذخیره', 'arvan-reseller' ); ?></button>
		</form>

	<?php elseif ( 'lifecycle' === $activeTab ) : ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $actions['lifecycle'] ); ?>" />
			<?php wp_nonce_field( $actions['lifecycle'] ); ?>
			<table class="form-table">
				<tr>
					<th><label for="arvan-notify"><?php esc_html_e( 'آستانه‌ی اعلان کمبود اعتبار (تومان)', 'arvan-reseller' ); ?></label></th>
					<td><input type="number" min="0" step="1" id="arvan-notify" name="notify_threshold_toman" value="<?php echo esc_attr( (string) Money::fromRial( $lifecyclePolicy['notify_threshold_rial'] )->toToman() ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="arvan-resume"><?php esc_html_e( 'آستانه‌ی ازسرگیری سرویس (تومان)', 'arvan-reseller' ); ?></label></th>
					<td><input type="number" min="0" step="1" id="arvan-resume" name="resume_threshold_toman" value="<?php echo esc_attr( (string) Money::fromRial( $lifecyclePolicy['resume_threshold_rial'] )->toToman() ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="arvan-grace"><?php esc_html_e( 'مهلت خاتمه (روز)', 'arvan-reseller' ); ?></label></th>
					<td><input type="number" min="0" step="1" id="arvan-grace" name="terminate_grace_days" value="<?php echo esc_attr( (string) $lifecyclePolicy['terminate_grace_days'] ); ?>" /></td>
				</tr>
			</table>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'ذخیره', 'arvan-reseller' ); ?></button>
		</form>

	<?php else : ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $actions['layout'] ); ?>" />
			<?php wp_nonce_field( $actions['layout'] ); ?>
			<p>
				<label>
					<input type="radio" name="layout" value="<?php echo esc_attr( ResellerSettings::LAYOUT_CARDS ); ?>" <?php checked( ResellerSettings::LAYOUT_CARDS, $layout ); ?> />
					<?php esc_html_e( 'کارتی (دو ستون)', 'arvan-reseller' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="radio" name="layout" value="<?php echo esc_attr( ResellerSettings::LAYOUT_COMPACT ); ?>" <?php checked( ResellerSettings::LAYOUT_COMPACT, $layout ); ?> />
					<?php esc_html_e( 'فشرده (یک ستون)', 'arvan-reseller' ); ?>
				</label>
			</p>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'ذخیره', 'arvan-reseller' ); ?></button>
		</form>

	<?php endif; ?>

	</div>

</div>
