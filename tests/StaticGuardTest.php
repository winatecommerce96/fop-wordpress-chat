<?php
use PHPUnit\Framework\TestCase;

/**
 * Source-level guards: the plugin exposes no endpoints, stores no other options and makes no other external calls.
 */
class StaticGuardTest extends TestCase {
	private function plugin_sources() {
		$root  = dirname( __DIR__ );
		$files = array_merge( array( $root . '/front-office-pilot-chat.php', $root . '/uninstall.php' ), glob( $root . '/includes/*.php' ) );
		$this->assertGreaterThanOrEqual( 4, count( $files ) );
		$out = array();
		foreach ( $files as $f ) {
			$out[ $f ] = file_get_contents( $f );
		}
		return $out;
	}

	/**
	 * @dataProvider forbidden
	 */
	public function test_forbidden_strings_absent( $needle ) {
		foreach ( $this->plugin_sources() as $file => $src ) {
			$this->assertSame( 0, preg_match( '/' . $needle . '/i', $src ), "$needle found in $file" );
		}
	}

	public function forbidden() {
		return array(
			array( 'wp_ajax_' ),
			array( 'register_rest_route' ),
			array( '\\badd_(site_)?option\\s*\\(' ),
			array( 'set_(site_)?transient' ),
			array( 'wp_remote_post' ),
			array( 'wp_remote_request' ),
			array( 'wp_safe_remote' ),
			array( '\\bcurl_' ),
			array( 'fsockopen' ),
			array( 'script_loader_tag' ),
		);
	}

	/**
	 * v1.1.0: update_option only in the one-time bundled import, and only for the plugin's own two options.
	 * v1.1.1: plus exactly one write in the activation notice, of its own flag only.
	 */
	public function test_options_are_written_only_by_the_bundled_import() {
		foreach ( $this->plugin_sources() as $file => $src ) {
			preg_match_all( '/\\bupdate_(?:site_|network_|blog_)?option\\s*\\(\\s*([A-Z_]+)/i', $src, $m );
			$all = preg_match_all( '/\\bupdate_(?:site_|network_|blog_)?option\\s*\\(/i', $src );
			if ( 'class-fop-chat-notice.php' === basename( $file ) ) {
				$this->assertSame( 1, $all );
				$this->assertSame( array( 'FOP_CHAT_NOTICE_OPTION' ), $m[1] );
				continue;
			}
			if ( 'class-fop-chat-bundled.php' !== basename( $file ) ) {
				$this->assertSame( 0, $all, "update_option in $file" );
				continue;
			}
			$this->assertSame( 3, $all );
			$this->assertSame( count( $m[1] ), $all, 'every write names an option constant' );
			foreach ( $m[1] as $name ) {
				$this->assertContains( $name, array( 'FOP_CHAT_OPTION', 'FOP_CHAT_BUNDLED_OPTION' ) );
			}
		}
	}

	public function test_files_are_read_only_by_the_bundled_import_and_never_included_by_path() {
		foreach ( $this->plugin_sources() as $file => $src ) {
			$reads = preg_match_all( '/\\bfile_get_contents\\s*\\(/i', $src );
			if ( 'class-fop-chat-bundled.php' === basename( $file ) ) {
				$this->assertSame( 1, $reads );
				$this->assertSame( 1, preg_match_all( '/\\bfile_get_contents\\s*\\(\\s*\\$path\\s*,\\s*false\\s*,\\s*null\\s*,\\s*0\\s*,\\s*self::MAX_BYTES \\+ 1\\s*\\)/', $src ) );
			} else {
				$this->assertSame( 0, $reads, "file_get_contents in $file" );
			}
			// Tokens, not text (comments are skipped): the only include/require/eval anywhere are the main file's four
			// literal __DIR__ . '/includes/class-fop-chat-*.php' requires. The bundled config is read, never included.
			$tokens = array_values( array_filter( token_get_all( $src ), function ( $t ) {
				return ! ( is_array( $t ) && in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) );
			} ) );
			$found = 0;
			foreach ( $tokens as $i => $tok ) {
				if ( ! is_array( $tok ) || ! in_array( $tok[0], array( T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE, T_EVAL ), true ) ) {
					continue;
				}
				$found++;
				$this->assertSame( T_REQUIRE_ONCE, $tok[0], "include/require/eval in $file" );
				$this->assertSame( array( T_DIR, '.' ), array( $tokens[ $i + 1 ][0], $tokens[ $i + 2 ] ), "require_once target in $file" );
				$this->assertMatchesRegularExpression( "/^'\\/includes\\/class-fop-chat-[a-z]+\\.php'$/", $tokens[ $i + 3 ][1] );
				$this->assertSame( ';', $tokens[ $i + 4 ] );
			}
			$this->assertSame( 'front-office-pilot-chat.php' === basename( $file ) ? 4 : 0, $found, "includes in $file" );
		}
	}

	public function test_the_marker_option_name_matches_uninstall() {
		$this->assertSame( 'fop_chat_bundled_import', FOP_CHAT_BUNDLED_OPTION );
		$this->assertSame( 2, substr_count( file_get_contents( dirname( __DIR__ ) . '/uninstall.php' ), "delete_option( 'fop_chat_bundled_import' )" ) );
	}

	public function test_the_import_only_ever_reads_the_plugins_own_config_file() {
		foreach ( $this->plugin_sources() as $file => $src ) {
			preg_match_all( '/\\bimport\\s*\\(\\s*([^)]*)\\)/', $src, $m );
			foreach ( $m[1] as $arg ) {
				if ( 'class-fop-chat-bundled.php' === basename( $file ) && '$path' === trim( $arg ) ) {
					continue;                                               // the definition itself
				}
				$this->assertSame( 'FOP_CHAT_BUNDLED_CONFIG', trim( $arg ), "import( $arg ) in $file" );
			}
		}
	}

	public function test_exactly_one_remote_get() {
		$count = 0;
		foreach ( $this->plugin_sources() as $src ) {
			$count += substr_count( $src, 'wp_remote_get(' );
		}
		$this->assertSame( 1, $count );
	}

	public function test_files_guard_direct_access() {
		foreach ( $this->plugin_sources() as $file => $src ) {
			$guard = ( 'uninstall.php' === basename( $file ) ) ? 'WP_UNINSTALL_PLUGIN' : 'ABSPATH';
			$this->assertNotFalse( strpos( $src, $guard ), "$file has no $guard guard" );
		}
	}
}
