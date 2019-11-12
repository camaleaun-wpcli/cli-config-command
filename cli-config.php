<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Manage the CLI configs.
 *
 * @when before_wp_load
 */
$wpcli_cli_config_command = dirname( __FILE__ ) . '/src/CliConfigCommand.php';
if ( file_exists( $wpcli_cli_config_command ) ) {
	require_once $wpcli_cli_config_command;
}
WP_CLI::add_command( 'cli config', 'Camaleaun\CliConfigCommand' );
