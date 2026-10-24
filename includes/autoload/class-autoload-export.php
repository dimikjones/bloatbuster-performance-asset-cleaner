<?php
/**
 * Byte-budgeted persistent options backup writer.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stages complete JSON downloads without accumulating option values in memory.
 */
final class Autoload_Export {

	/**
	 * Maximum raw bytes transferred or encoded in one chunk (divisible by three).
	 *
	 * @var int
	 */
	const CHUNK_BYTES = 49152;

	/**
	 * Build a validated, rewound export stream for the caller to close.
	 *
	 * @param array|null $names - Selected persistent option names, or null for all.
	 * @param int        $limit - Maximum encoded download size in bytes.
	 *
	 * @return resource|\WP_Error - Complete JSON stream, or an export error.
	 *
	 * @throws \RuntimeException On an export validation or I/O failure (converted by create()).
	 *
	 * @since 1.0.0
	 */
	public static function create( $names, $limit ) {
		$stream   = null;
		$complete = false;

		try {
			$scope   = self::get_scope( $names );
			$header  = self::encode(
				array(
					'plugin'         => 'bloatbuster',
					'format_version' => 2,
					'version'        => BLOATBUSTER_VERSION,
					'site_url'       => \home_url(),
					'created'        => gmdate( 'Y-m-d-His' ),
				)
			);
			$prefix  = substr( $header, 0, -1 ) . ',"options":[';
			$summary = self::get_summary( $scope );

			if ( 0 === (int) $summary->total ) {
				throw new \RuntimeException( 'no_export_options' );
			}

			if ( strlen( $prefix ) + 2 + (int) $summary->bytes + (int) $summary->total - 1 > $limit ) {
				throw new \RuntimeException( 'export_size' );
			}

			$stream         = self::open_stream();
			$written        = 0;
			$count          = 0;
			$metadata_bytes = 0;
			$cursor         = 0;
			self::write( $stream, $prefix, $written, $limit );

			while ( true ) {
				$rows = self::get_metadata( $scope, $cursor, (int) $summary->last_id );

				if ( empty( $rows ) ) {
					break;
				}

				foreach ( $rows as $row ) {
					$minimum = (int) $row->bytes + strlen( $row->name ) + strlen( $row->autoload ) + strlen( '{"name":"","autoload":"","value":""}' );

					if ( $minimum + $written + 2 + ( 0 < $count ? 1 : 0 ) > $limit ) {
						throw new \RuntimeException( 'export_size' );
					}

					if ( 0 < $count ) {
						self::write( $stream, ',', $written, $limit );
					}

					self::write_row( $stream, $row, $written, $limit );
					$cursor          = (int) $row->id;
					$metadata_bytes += $minimum;
					++$count;
				}
			}

			if ( $count !== (int) $summary->total || $metadata_bytes !== (int) $summary->bytes ) {
				throw new \RuntimeException( 'export_changed' );
			}

			self::write( $stream, ']}', $written, $limit );

			if ( ! rewind( $stream ) ) {
				throw new \RuntimeException( 'export_stream' );
			}

			$complete = true;
			return $stream;
		} catch ( \Throwable $error ) {
			return self::get_error( $error->getMessage() );
		} finally {
			if ( ! $complete && is_resource( $stream ) ) {
				self::close_stream( $stream );
			}
		}
	}

	/**
	 * Build persistent-only SQL scope without reading all database option names.
	 *
	 * @param array|null $names - Selection, or null for all persistent options.
	 *
	 * @return array - Prepared-query SQL fragment and parameters.
	 *
	 * @throws \RuntimeException On an export validation or I/O failure (converted by create()).
	 *
	 * @since 1.0.0
	 */
	public static function get_scope( $names ) {
		global $wpdb;

		$sql  = 'BINARY option_name NOT LIKE %s AND BINARY option_name NOT LIKE %s';
		$args = array( $wpdb->esc_like( '_transient_' ) . '%', $wpdb->esc_like( '_site_transient_' ) . '%' );

		if ( null !== $names ) {
			$names = array_values( array_unique( array_filter( array_map( 'strval', $names ), array( Autoload_Options::class, 'is_persistent_option' ) ) ) );

			if ( empty( $names ) ) {
				throw new \RuntimeException( 'no_export_options' );
			}

			$sql .= ' AND option_name IN ( ' . Autoload_Options::get_placeholders( count( $names ) ) . ' )';
			$args = array_merge( $args, $names );
		}

		return array(
			'sql'  => $sql,
			'args' => $args,
		);
	}

	/**
	 * Preflight a conservative minimum JSON size without transferring any values.
	 *
	 * @param array $scope - Export scope SQL and parameters.
	 *
	 * @return object - Row count, raw lower-bound bytes and highest option identifier.
	 *
	 * @throws \RuntimeException On an export validation or I/O failure (converted by create()).
	 *
	 * @since 1.0.0
	 */
	public static function get_summary( $scope ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Metadata only; scope contains prepared placeholders.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total, COALESCE(SUM(OCTET_LENGTH(option_value) + OCTET_LENGTH(option_name) + OCTET_LENGTH(autoload) + %d), 0) AS bytes, COALESCE(MAX(option_id), 0) AS last_id FROM {$wpdb->options} WHERE {$scope['sql']}", array_merge( array( strlen( '{"name":"","autoload":"","value":""}' ) ), $scope['args'] ) ) );
		// phpcs:enable

		if ( ! $row || '' !== $wpdb->last_error ) {
			throw new \RuntimeException( 'backup_database' );
		}

		return $row;
	}

	/**
	 * Read a bounded metadata page with stable identifiers and value fingerprints.
	 *
	 * @param array $scope  - Export scope.
	 * @param int   $cursor - Last processed option identifier.
	 * @param int   $last   - Highest identifier at preflight.
	 *
	 * @return array - At most 100 metadata rows, without option values.
	 *
	 * @throws \RuntimeException On an export validation or I/O failure (converted by create()).
	 *
	 * @since 1.0.0
	 */
	public static function get_metadata( $scope, $cursor, $last ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Scope values prepared; fixed metadata page size.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_id AS id, option_name AS name, autoload, OCTET_LENGTH(option_value) AS bytes, SHA2(CAST(option_value AS BINARY), 256) AS digest FROM {$wpdb->options} WHERE {$scope['sql']} AND option_id > %d AND option_id <= %d ORDER BY option_id ASC LIMIT 100", array_merge( $scope['args'], array( $cursor, $last ) ) ) );
		// phpcs:enable

		if ( '' !== $wpdb->last_error ) {
			throw new \RuntimeException( 'backup_database' );
		}

		return $rows;
	}

	/**
	 * Read one value in byte-bounded slices and verify it against its metadata.
	 *
	 * @param object $row - Expected option metadata.
	 *
	 * @return resource - Rewound private value stream for the caller to close.
	 *
	 * @throws \RuntimeException On an export validation or I/O failure (converted by create()).
	 *
	 * @since 1.0.0
	 */
	public static function read_value( $row ) {
		global $wpdb;

		$raw      = self::open_stream();
		$complete = false;

		try {
			$hash   = hash_init( 'sha256' );
			$copied = 0;

			while ( $copied < (int) $row->bytes ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Only a bounded binary slice is transferred, even if the value grew after preflight.
				$part = $wpdb->get_row( $wpdb->prepare( "SELECT SUBSTRING(CAST(option_value AS BINARY), %d, %d) AS chunk, OCTET_LENGTH(option_value) AS bytes, option_name AS name, autoload FROM {$wpdb->options} WHERE option_id = %d", $copied + 1, min( self::CHUNK_BYTES, (int) $row->bytes - $copied ), $row->id ) );
				// phpcs:enable

				if ( '' !== $wpdb->last_error ) {
					throw new \RuntimeException( 'backup_database' );
				}

				if ( ! $part || (int) $part->bytes !== (int) $row->bytes || $part->name !== $row->name || $part->autoload !== $row->autoload || '' === $part->chunk ) {
					throw new \RuntimeException( 'export_changed' );
				}

				self::write( $raw, $part->chunk, $copied, (int) $row->bytes );
				hash_update( $hash, $part->chunk );
			}

			if ( ! is_string( $row->digest ) || ! hash_equals( $row->digest, hash_final( $hash ) ) || ! rewind( $raw ) ) {
				throw new \RuntimeException( 'export_changed' );
			}

			$complete = true;
			return $raw;
		} finally {
			if ( ! $complete ) {
				self::close_stream( $raw );
			}
		}
	}

	/**
	 * Split complete UTF-8 characters from a possible trailing partial character.
	 *
	 * @param string $buffer - Input bytes including preceding carry.
	 * @param bool   $eof  - Whether this is the end of the value.
	 *
	 * @return array|false - Complete characters and carry, or invalid UTF-8.
	 *
	 * @since 1.0.0
	 */
	public static function split_utf8( $buffer, $eof ) {
		$maximum = $eof ? 0 : min( 3, strlen( $buffer ) );

		for ( $cut = 0; $cut <= $maximum; ++$cut ) {
			$prefix = 0 === $cut ? $buffer : substr( $buffer, 0, -$cut );

			if ( 1 === preg_match( '//u', $prefix ) ) {
				return array( $prefix, 0 === $cut ? '' : substr( $buffer, -$cut ) );
			}
		}

		return false;
	}

	/**
	 * Validate UTF-8 without loading the entire raw value.
	 *
	 * @param resource $raw - Rewound raw value stream.
	 *
	 * @return bool - Whether the complete value is valid UTF-8.
	 *
	 * @since 1.0.0
	 */
	public static function is_utf8( $raw ) {
		$carry = '';

		while ( ! feof( $raw ) ) {
			$part = self::split_utf8( $carry . self::read( $raw ), feof( $raw ) );

			if ( false === $part ) {
				return false;
			}

			$carry = $part[1];
		}

		return '' === $carry;
	}

	/**
	 * Write one complete option, encoding raw values in bounded chunks.
	 *
	 * @param resource $stream  - Destination JSON stream.
	 * @param object   $row     - Metadata for this option.
	 * @param int      $written - Total output bytes so far.
	 * @param int      $limit   - Maximum output bytes.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException On an export validation or I/O failure (converted by create()).
	 *
	 * @since 1.0.0
	 */
	public static function write_row( $stream, $row, &$written, $limit ) {
		$raw = self::read_value( $row );

		try {
			$utf8 = self::is_utf8( $raw );

			if ( ! rewind( $raw ) ) {
				throw new \RuntimeException( 'export_stream' );
			}

			$metadata = self::encode(
				array(
					'name'     => $row->name,
					'autoload' => $row->autoload,
				)
			);
			self::write( $stream, substr( $metadata, 0, -1 ) . ( $utf8 ? ',"value":"' : ',"value_base64":"' ), $written, $limit );
			$carry = '';

			while ( ! feof( $raw ) ) {
				$chunk = $carry . self::read( $raw );

				if ( $utf8 ) {
					$part = self::split_utf8( $chunk, feof( $raw ) );

					if ( false === $part ) {
						throw new \RuntimeException( 'export_encoding' );
					}

					$carry   = $part[1];
					$encoded = substr( self::encode( $part[0] ), 1, -1 );
				} else {
					$remainder = feof( $raw ) ? 0 : strlen( $chunk ) % 3;
					$carry     = 0 < $remainder ? substr( $chunk, -$remainder ) : '';
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Preserves invalid UTF-8 bytes.
					$encoded = base64_encode( 0 < $remainder ? substr( $chunk, 0, -$remainder ) : $chunk );
				}

				self::write( $stream, $encoded, $written, $limit );
			}

			self::write( $stream, '"}', $written, $limit );
		} finally {
			self::close_stream( $raw );
		}
	}

	/**
	 * Encode bounded UTF-8 metadata or a validated string chunk.
	 *
	 * @param mixed $value - Small metadata object or bounded value chunk.
	 *
	 * @return string - JSON token.
	 *
	 * @throws \RuntimeException On an export validation or I/O failure (converted by create()).
	 *
	 * @since 1.0.0
	 */
	public static function encode( $value ) {
		$json = \wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( false === $json ) {
			throw new \RuntimeException( 'export_encoding' );
		}

		return $json;
	}

	/**
	 * Open a private stream which spills to disk above 64 KB.
	 *
	 * @return resource - Temporary stream for the caller to close.
	 *
	 * @throws \RuntimeException On an export validation or I/O failure (converted by create()).
	 *
	 * @since 1.0.0
	 */
	public static function open_stream() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.PHP.NoSilencedErrors.Discouraged -- Private temporary PHP stream; failure becomes a controlled error before download headers.
		$stream = @fopen( 'php://temp/maxmemory:65536', 'w+b' );

		if ( false === $stream ) {
			throw new \RuntimeException( 'export_stream' );
		}

		return $stream;
	}

	/**
	 * Read at most one bounded chunk, rejecting failures and stalled streams.
	 *
	 * @param resource $stream - Temporary value stream.
	 *
	 * @return string - Raw bytes, possibly empty only at EOF.
	 *
	 * @throws \RuntimeException On an export validation or I/O failure (converted by create()).
	 *
	 * @since 1.0.0
	 */
	public static function read( $stream ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPress.PHP.NoSilencedErrors.Discouraged -- Private stream; errors handled explicitly.
		$bytes = @fread( $stream, self::CHUNK_BYTES );

		if ( false === $bytes || ( '' === $bytes && ! feof( $stream ) ) ) {
			throw new \RuntimeException( 'export_stream' );
		}

		return $bytes;
	}

	/**
	 * Write a complete bounded token within the exact output budget.
	 *
	 * @param resource $stream  - Destination stream.
	 * @param string   $bytes   - Bounded token to write.
	 * @param int      $written - Current bytes written to this stream.
	 * @param int      $limit   - Maximum permitted size.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException On an export validation or I/O failure (converted by create()).
	 *
	 * @since 1.0.0
	 */
	public static function write( $stream, $bytes, &$written, $limit ) {
		$length = strlen( $bytes );

		if ( $length > $limit - $written ) {
			throw new \RuntimeException( 'export_size' );
		}

		if ( 0 < $length ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.PHP.NoSilencedErrors.Discouraged -- Private stream; partial writes are rejected.
			$count = @fwrite( $stream, $bytes );

			if ( $count !== $length ) {
				throw new \RuntimeException( 'export_stream' );
			}
		}

		$written += $length;
	}

	/**
	 * Close a private temporary PHP stream without using filesystem credentials.
	 *
	 * @param resource $stream - Stream owned by the export pipeline.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function close_stream( $stream ) {
		if ( is_resource( $stream ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Local PHP stream, not a WordPress filesystem operation.
			fclose( $stream );
		}
	}

	/**
	 * Convert internal failures into safe user-facing export errors.
	 *
	 * @param string $code - Internal failure code.
	 *
	 * @return \WP_Error - Download failure.
	 *
	 * @since 1.0.0
	 */
	public static function get_error( $code ) {
		$messages = array(
			'export_size'       => \__( 'The backup exceeds the import size limit. Use Export selected to create smaller files.', 'bloatbuster-performance-asset-cleaner' ),
			'no_export_options' => \__( 'No persistent options are available to export. Transient values and timeout rows are excluded.', 'bloatbuster-performance-asset-cleaner' ),
			'backup_database'   => \__( 'The database could not be read safely. Try again.', 'bloatbuster-performance-asset-cleaner' ),
			'export_changed'    => \__( 'Options changed during export. No backup was downloaded. Try again.', 'bloatbuster-performance-asset-cleaner' ),
			'export_encoding'   => \__( 'The backup could not be encoded. No file was exported.', 'bloatbuster-performance-asset-cleaner' ),
			'export_stream'     => \__( 'The temporary backup could not be written or read. No file was exported.', 'bloatbuster-performance-asset-cleaner' ),
		);
		$code     = isset( $messages[ $code ] ) ? $code : 'export_stream';

		return new \WP_Error( $code, $messages[ $code ] );
	}
}
