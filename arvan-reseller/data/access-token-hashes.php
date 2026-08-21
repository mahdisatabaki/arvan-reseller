<?php
/**
 * Demo Access Token hash allowlist.
 *
 * DATA-MODEL.md §14: "Demo Access Token hashes are seed/config data, not
 * customer/business tables." This file returns only hashes — no raw token
 * value is stored here or anywhere else in the plugin. The demo tokens
 * these hashes correspond to are documented in SECURITY.md §3 and
 * docs/BACKLOG.md T-2.2, not here.
 *
 * Each entry is the `password_hash($token, PASSWORD_DEFAULT)` output for one
 * demo token. To add a token: generate its hash offline
 * (`php -r "echo password_hash('...', PASSWORD_DEFAULT);"`) and append the
 * result below. This file is static application data — there is no
 * runtime/admin UI to add entries for the MVP.
 *
 * @return string[]
 */

declare( strict_types = 1 );

return [
	'$2y$10$052m5UOF5zfI54JKZ.Gmg.rYUyQIUfUJBl23HTftbYO2qDlcN0mvW',
	'$2y$10$gjWlX5j.Sr8/pXk8ztBitu4ARA9iMC4/TfaD4zWQ9re28zFWYE/Lu',
];
