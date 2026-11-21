<?php
use PHPUnit\Framework\TestCase;

/**
 * v1.1.0: a key bundled in the dashboard's per-site download (fop-config.php) is imported once into the option.
 * A key already saved always wins, and a key the site owner clears stays cleared.
 */
class BundledTest extends TestCase {
	const KEY   = 'fop_pk_BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB';
	const OTHER = 'fop_pk_CCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCC';

	private $dir;

	protected function setUp(): void {
		FOP_Test_State::reset();
		$this->dir = sys_get_temp_dir() . '/fop-bundled-' . uniqid();
		mkdir( $this->dir );
	}

	protected function tearDown(): void {
		foreach ( glob( $this->dir . '/*' ) as $f ) {
			unlink( $f );
		}
		rmdir( $this->dir );
	}

	private function config( $body ) {
		$path = $this->dir . '/fop-config.php';
		file_put_contents( $path, "<?php\nif ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n" . $body . "\n" );
		return $path;
	}

	private function option( $name ) {
		return isset( FOP_Test_State::$options[1][ $name ] ) ? FOP_Test_State::$options[1][ $name ] : null;
	}

	public function test_the_bundled_key_is_imported_into_an_empty_option() {
		FOP_Chat_Bundled::import( $this->config( "return array( 'site_key' => '" . self::KEY . "' );" ) );
		$this->assertSame( self::KEY, $this->option( FOP_CHAT_OPTION ) );
		$this->assertSame( 'imported', $this->option( FOP_CHAT_BUNDLED_OPTION ) );
	}

	public function test_a_saved_key_wins_and_is_never_overwritten() {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = self::OTHER;
		FOP_Chat_Bundled::import( $this->config( "return array( 'site_key' => '" . self::KEY . "' );" ) );
		$this->assertSame( self::OTHER, $this->option( FOP_CHAT_OPTION ) );
		$this->assertSame( 'kept', $this->option( FOP_CHAT_BUNDLED_OPTION ) );
	}

	public function test_a_key_the_owner_cleared_stays_cleared() {
		$path = $this->config( "return array( 'site_key' => '" . self::KEY . "' );" );
		FOP_Chat_Bundled::import( $path );
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = '';       // cleared on the settings page
		FOP_Test_State::$updates                       = array();
		FOP_Chat_Bundled::import( $path );
		$this->assertSame( '', $this->option( FOP_CHAT_OPTION ) );
		$this->assertSame( array(), FOP_Test_State::$updates );
	}

	public function test_no_bundled_file_writes_nothing_so_a_later_download_still_imports() {
		FOP_Chat_Bundled::import( $this->dir . '/fop-config.php' );
		$this->assertSame( array(), FOP_Test_State::$updates );
		FOP_Chat_Bundled::import( $this->config( "return array( 'site_key' => '" . self::KEY . "' );" ) );
		$this->assertSame( self::KEY, $this->option( FOP_CHAT_OPTION ) );
	}

	/**
	 * @dataProvider bad_configs
	 */
	public function test_a_malformed_bundled_file_writes_nothing( $body ) {
		FOP_Chat_Bundled::import( $this->config( $body ) );
		$this->assertSame( array(), FOP_Test_State::$updates );
	}

	public function bad_configs() {
		return array(
			'not an array'      => array( "return 'fop_pk_BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB';" ),
			'no site_key'       => array( "return array( 'key' => 'fop_pk_BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB' );" ),
			'wrong format'      => array( "return array( 'site_key' => 'fop_pk_short' );" ),
			'markup in the key' => array( "return array( 'site_key' => 'fop_pk_BBBB\"><script>' );" ),
			'not a string'      => array( "return array( 'site_key' => 42 );" ),
			'returns nothing'   => array( '' ),
			'syntax error'      => array( "return array( 'site_key' => 'fop_pk_BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB' " ),
			'prints output'     => array( "echo 'x';\nreturn array( 'site_key' => 'fop_pk_BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB' );" ),
			'throws'            => array( "throw new RuntimeException( 'boom' );" ),
		);
	}

	public function test_a_bom_before_the_php_tag_writes_nothing_and_prints_nothing() {
		$path = $this->dir . '/fop-config.php';
		file_put_contents( $path, "\xEF\xBB\xBF<?php\nreturn array( 'site_key' => '" . self::KEY . "' );\n" );
		ob_start();
		FOP_Chat_Bundled::import( $path );
		$this->assertSame( '', ob_get_clean() );
		$this->assertSame( array(), FOP_Test_State::$updates );
	}

	private function real_config() {
		$real = FOP_CHAT_BUNDLED_CONFIG;
		if ( file_exists( $real ) ) {
			$this->markTestSkipped( 'a developer fop-config.php is in the plugin root; not touching it' );
		}
		file_put_contents( $real, "<?php\nreturn array( 'site_key' => '" . self::KEY . "' );\n" );
		return $real;
	}

	public function test_multisite_never_imports_network_wide_or_per_site() {
		$real = $this->real_config();
		try {
			FOP_Test_State::$multisite = true;
			FOP_Chat_Bundled::on_hook( true );                                 // network activation
			FOP_Chat_Bundled::on_hook();                                       // per-site activation / admin_init
			$this->assertSame( array(), FOP_Test_State::$updates );
			FOP_Test_State::$multisite = false;
			FOP_Chat_Bundled::on_hook();                                       // a single site: imports
			$this->assertSame( self::KEY, $this->option( FOP_CHAT_OPTION ) );
		} finally {
			unlink( $real );
		}
	}

	public function test_a_file_that_closes_our_buffer_or_opens_its_own_is_no_key() {
		$level = ob_get_level();
		ob_start();
		echo 'PAGE';
		FOP_Chat_Bundled::import( $this->config( "ob_end_clean();\nreturn array( 'site_key' => '" . self::KEY . "' );" ) );
		FOP_Chat_Bundled::import( $this->config( "ob_start();\nreturn array( 'site_key' => '" . self::KEY . "' );" ) );
		$this->assertSame( $level + 1, ob_get_level() );                    // no buffer left open, none of ours taken
		$this->assertSame( 'PAGE', ob_get_clean() );                          // the caller's output survives
		$this->assertSame( array(), FOP_Test_State::$updates );
	}

	public function test_the_notice_goes_once_the_owner_pastes_another_key() {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ]         = self::OTHER;
		FOP_Test_State::$options[1][ FOP_CHAT_BUNDLED_OPTION ] = 'imported';
		$this->assertFalse( FOP_Chat_Bundled::key_was_imported() );          // the plugin's own fop-config.php is absent
	}

	public function test_the_settings_page_says_where_an_imported_key_came_from() {
		$real = $this->real_config();
		try {
			FOP_Chat_Bundled::import( $real );
			$this->assertTrue( FOP_Chat_Bundled::key_was_imported() );
			$this->assertStringContainsString( 'Added from your Front Office Pilot download.', $this->page() );
			FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = '';                      // cleared
			$this->assertStringNotContainsString( 'Added from your', $this->page() );
		} finally {
			unlink( $real );
		}
	}

	private function page() {
		ob_start();
		try {
			FOP_Chat_Settings::render_page();
		} finally {
			$html = ob_get_clean();
		}
		return $html;
	}
}
