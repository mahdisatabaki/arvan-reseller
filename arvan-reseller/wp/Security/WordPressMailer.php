<?php
/**
 * `Mailer` implementation on WordPress's own `wp_mail()`.
 *
 * `wp/Security/` rather than `wp/Notifications/` or similar: this file lives
 * alongside `WordPressSecretStore.php` as a small infra adapter with no admin
 * UI of its own, not a screen — matching where that sibling adapter already
 * sits.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Security;

use ArvanReseller\Ports\Mailer;

defined( 'ABSPATH' ) || exit;

final class WordPressMailer implements Mailer {

	public function send( string $to, string $subject, string $body ): bool {
		return wp_mail( $to, $subject, $body );
	}
}
