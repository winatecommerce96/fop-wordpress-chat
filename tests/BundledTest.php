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
		);
	}

	public function test_the_import_runs_on_activation_and_on_admin_init() {
		$this->assertSame( dirname( __DIR__ ) . '/fop-config.php', FOP_CHAT_BUNDLED_CONFIG );
		FOP_Chat_Bundled::init( dirname( __DIR__ ) . '/front-office-pilot-chat.php' );
		$this->assertSame(
			array( array( dirname( __DIR__ ) . '/front-office-pilot-chat.php', array( 'FOP_Chat_Bundled', 'on_hook' ) ) ),
			FOP_Test_State::$activation
		);
		$this->assertContains( 'admin_init', array_keys( FOP_Test_State::$actions ) );
	}

	public function test_only_the_plugins_own_two_options_are_ever_written() {
		FOP_Chat_Bundled::import( $this->config( "return array( 'site_key' => '" . self::KEY . "' );" ) );
		foreach ( FOP_Test_State::$updates as $u ) {
			$this->assertContains( $u[0], array( FOP_CHAT_OPTION, FOP_CHAT_BUNDLED_OPTION ) );
		}
	}

	public function test_the_settings_page_says_where_an_imported_key_came_from() {
		FOP_Chat_Bundled::import( $this->config( "return array( 'site_key' => '" . self::KEY . "' );" ) );
		ob_start();
		FOP_Chat_Settings::render_page();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'Added from your Front Office Pilot download.', $html );
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = '';
		ob_start();
		FOP_Chat_Settings::render_page();
		$this->assertStringNotContainsString( 'Added from your', ob_get_clean() );
	}
}
