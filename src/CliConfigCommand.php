<?php

/**
 * Manage the CLI configs.
 */

namespace Camaleaun;

use WP_CLI;
use WP_CLI\Utils;
use Mustangostang\Spyc;

/**
 * Manage the CLI configs.
 */
class CliConfigCommand {

	/**
	 * Sets a CLI config.
	 *
	 * Update if exists or add.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Name of the CLI config.
	 *
	 * <value>
	 * : Value to set CLI config.
	 *
	 * [--config=<config>]
	 * : Config file to be considered for operations.
	 * ---
	 * default: global
	 * options:
	 *   - global
	 *   - project
	 *   - local
	 * ---
	 *
	 * [--local-merge[=<inherit>]]
	 * : Define 'wp-cli.local.yml' merging.
	 *
	 * ## EXAMPLES
	 *
	 *     # Set the path
	 *     $ wp cli set path ~/.wp-cli/sites/example
	 *     Success: Added 'path' path.
	 *
	 * @subcommand
	 */
	public function set( $args, $assoc_args ) {
		list( $name, $value ) = $args;

		$config = WP_CLI\Utils\get_flag_value( $assoc_args, 'config', 'global' );
		$merge  = WP_CLI\Utils\get_flag_value( $assoc_args, 'local-merge' );

		WP_CLI::debug( "Type: $config" );

		if ( 'local' === $config ) {
			if ( ! file_exists( getcwd() . '/wp-cli.local.yml' ) ) {
				WP_CLI::launch( 'touch wp-cli.local.yml 2>&1' );
			}
		}

		list( $path, $data ) = $this->get_cli_config_data( $config );

		if ( $merge ) {
			$data = array_merge(
				array(
					'_' => array(
						'merge'   => true,
						'inherit' => 'wp-cli.yml',
					),
				),
				$data
			);
		}

		$operation = 'Added';
		if ( isset( $data[ $name ] ) ) {
			$operation = 'Updated';
		}

		if ( 'path' === $name ) {
			$value = preg_replace( '#^~#', WP_CLI\Utils\get_home_dir(), $value );
			$this->touch_path( $value );
		}

		$data = array_merge( $data, array( $name => $value ) );

		$this->process_config( $data, $name, $path, $operation );
	}

	/**
	 * Get config path and config data based on config type.
	 *
	 * @param string $config Type of config to get data from.
	 *
	 * @return array Config Path and Config in it.
	 */
	private function get_cli_config_data( $config ) {

		if ( 'local' === $config ) {
			$path = $this->get_local_config_path();
		} elseif ( 'local' === $config ) {
			$path = $this->get_project_config_path();
		} elseif ( 'global' === $config ) {
			$path = $this->get_global_config_path();
		}

		if ( $path ) {
			$data = Spyc::YAMLLoad( $path );
		}

		return array( $path, $data );
	}

	/**
	 * Get the path to the local-specific configuration
	 * YAML file.
	 *
	 * @return string|false
	 */
	public function get_local_config_path() {
		$config_files = array( 'wp-cli.local.yml' );

		// Stop looking upward when we find we have emerged from a subdirectory
		// installation into a parent installation
		$local_config_path = WP_CLI\Utils\find_file_upward(
			$config_files,
			getcwd(),
			function ( $dir ) {
				static $wp_load_count = 0;
				$wp_load_path         = $dir . DIRECTORY_SEPARATOR . 'wp-load.php';
				if ( file_exists( $wp_load_path ) ) {
					++ $wp_load_count;
				}
				return $wp_load_count > 1;
			}
		);

		$this->local_config_path_debug = 'No local config found';

		if ( ! empty( $local_config_path ) ) {
			$this->local_config_path_debug = 'Using local config: ' . $local_config_path;
		}

		return $local_config_path;
	}

	/**
	 * Save config data to config file.
	 *
	 * @param array  $config      Current Config data.
	 * @param string $name        Name of config.
	 * @param string $config_path Path to config file.
	 * @param string $operation   Current operation string fro message.
	 */
	private function process_config( $config, $name, $config_path, $operation = '' ) {
		// Convert data to YAML string.
		$yaml_data = preg_replace( '/-{3}\n/', '', Spyc::YAMLDump( $config ) );

		// Add data in config file.
		if ( file_put_contents( $config_path, $yaml_data ) ) {
			WP_CLI::success( trim( "$operation '{$name}' config." ) );
		}
	}

	private function touch_path( $path ) {
		preg_match_all( '#/[^/]+#', $path, $dirs );
		$dirs = current( $dirs );
		$path = '';
		foreach ( $dirs as $dir ) {
			$path .= $dir;
			$this->touch_dir( $path );
		}
	}

	private function touch_dir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			if ( ! is_writable( dirname( $dir ) ) ) {
				WP_CLI::error( "Insufficient permission to create directory '{$dir}'." );
			}

			WP_CLI::debug( "Creating directory '{$dir}'." );
			if ( ! @mkdir( $dir, 0777, true /*recursive*/ ) ) {
				$error = error_get_last();
				WP_CLI::error( "Failed to create directory '{$dir}': {$error['message']}." );
			}
		}
	}
}
