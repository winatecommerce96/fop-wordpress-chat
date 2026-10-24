<?php
use PHPUnit\Framework\TestCase;

/**
 * Source-level guards: the plugin exposes no endpoints, stores no other options and makes no other external calls.
 */
class StaticGuardTest extends TestCase {
	private function plugin_sources() {
		$root  = dirname( __DIR__ );
		$files = array_merge( array( $root . '/fop-chat.php', $root . '/uninstall.php' ), glob( $root . '/includes/*.php' ) );
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
			$this->assertFalse( stripos( $src, $needle ), "$needle found in $file" );
		}
	}

	public function forbidden() {
		return array(
			array( 'wp_ajax_' ),
			array( 'register_rest_route' ),
			array( 'update_option' ),
			array( 'add_option' ),
			array( 'set_transient' ),
			array( 'wp_remote_post' ),
			array( 'wp_remote_request' ),
			array( 'wp_safe_remote' ),
			array( 'curl_' ),
			array( 'file_get_contents' ),
			array( 'fsockopen' ),
		);
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
