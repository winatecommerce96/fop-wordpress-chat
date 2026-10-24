<?php
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase {
	const KEY = 'fop_pk_AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

	protected function setUp(): void {
		FOP_Test_State::reset();
	}

	public function test_constants() {
		$this->assertSame( 'https://chat.frontofficepilot.com/widget/v1/fop-chat.js', FOP_CHAT_SCRIPT_URL );
		$this->assertSame( 'fop_chat_site_key', FOP_CHAT_OPTION );
		$this->assertSame( 'https://app.frontofficepilot.com/chat-widget', FOP_CHAT_DASHBOARD_URL );
	}

	public function test_plugin_header() {
		$src = file_get_contents( dirname( __DIR__ ) . '/fop-chat.php' );
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Plugin Name:\s*Front Office Pilot Chat\s*$/m', $src );
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Version:\s*1\.0\.0\s*$/m', $src );
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Requires PHP:\s*7\.4\s*$/m', $src );
		$this->assertMatchesRegularExpression( '/^\s*\*\s*License:\s*GPLv2 or later\s*$/m', $src );
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Text Domain:\s*fop-chat\s*$/m', $src );
	}

	public function test_registers_one_setting_with_sanitize_callback() {
		FOP_Chat_Settings::register();
		$this->assertSame( array( FOP_CHAT_OPTION ), array_keys( FOP_Test_State::$settings ) );
		$args = FOP_Test_State::$settings[ FOP_CHAT_OPTION ]['args'];
		$this->assertSame( array( 'FOP_Chat_Settings', 'sanitize_key' ), $args['sanitize_callback'] );
		$this->assertSame( FOP_Chat_Settings::GROUP, FOP_Test_State::$settings[ FOP_CHAT_OPTION ]['group'] );
	}

	public function test_sanitize_trims_and_accepts_valid_key() {
		$this->assertSame( self::KEY, FOP_Chat_Settings::sanitize_key( "  " . self::KEY . "\n" ) );
		$this->assertSame( array(), FOP_Test_State::$settings_errors );
	}

	public function test_sanitize_accepts_empty_to_clear() {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = self::KEY;
		$this->assertSame( '', FOP_Chat_Settings::sanitize_key( '   ' ) );
		$this->assertSame( array(), FOP_Test_State::$settings_errors );
	}

	/**
	 * @dataProvider invalid_keys
	 */
	public function test_sanitize_rejects_invalid_and_keeps_previous( $bad ) {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = self::KEY;
		$this->assertSame( self::KEY, FOP_Chat_Settings::sanitize_key( $bad ) );
		$this->assertCount( 1, FOP_Test_State::$settings_errors );
		$this->assertSame( FOP_CHAT_OPTION, FOP_Test_State::$settings_errors[0]['setting'] );
		$this->assertSame( 'error', FOP_Test_State::$settings_errors[0]['type'] );
	}

	public function invalid_keys() {
		return array(
			'too short'       => array( 'fop_pk_AAAA' ),
			'too long'        => array( 'fop_pk_' . str_repeat( 'A', 33 ) ),
			'wrong prefix'    => array( 'fop_sk_' . str_repeat( 'A', 32 ) ),
			'upper prefix'    => array( 'FOP_PK_' . str_repeat( 'A', 32 ) ),
			'bad char'        => array( 'fop_pk_' . str_repeat( 'A', 31 ) . '-' ),
			'markup'          => array( '"><script>alert(1)</script>' ),
			'inner space'     => array( 'fop_pk_' . str_repeat( 'A', 16 ) . ' ' . str_repeat( 'A', 15 ) ),
			'trailing newline in middle' => array( 'fop_pk_' . str_repeat( 'A', 32 ) . "\nx" ),
			'non-string'      => array( array( 'x' ) ),
		);
	}

	public function test_sanitize_invalid_with_no_previous_returns_empty() {
		$this->assertSame( '', FOP_Chat_Settings::sanitize_key( 'nope' ) );
		$this->assertCount( 1, FOP_Test_State::$settings_errors );
	}

	public function test_menu_page_requires_manage_options() {
		FOP_Chat_Settings::add_page();
		$this->assertCount( 1, FOP_Test_State::$menu_pages );
		$page = FOP_Test_State::$menu_pages[0];
		$this->assertSame( 'manage_options', $page['capability'] );
		$this->assertSame( 'Front Office Pilot Chat', $page['menu_title'] );
		$this->assertSame( FOP_Chat_Settings::PAGE_SLUG, $page['slug'] );
	}

	public function test_render_page_denies_without_capability() {
		FOP_Test_State::$can = false;
		$this->expectException( FOP_Test_Die::class );
		ob_start();
		try {
			FOP_Chat_Settings::render_page();
		} finally {
			$out = ob_get_clean();
		}
	}

	public function test_render_page_outputs_settings_fields_form() {
		ob_start();
		FOP_Chat_Settings::render_page();
		$out = ob_get_clean();
		$this->assertSame( array( FOP_Chat_Settings::GROUP ), FOP_Test_State::$settings_fields_calls );
		$this->assertStringContainsString( 'action="options.php"', $out );
		$this->assertStringContainsString( 'method="post"', $out );
		$this->assertStringContainsString( '<!--submit_button-->', $out );
	}

	public function test_render_field_escapes_stored_value() {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = '"><script>alert(1)</script>';
		ob_start();
		FOP_Chat_Settings::render_field();
		$out = ob_get_clean();
		$this->assertStringNotContainsString( '<script>', $out );
		$this->assertStringContainsString( '&quot;&gt;&lt;script&gt;', $out );
		$this->assertContains( '"><script>alert(1)</script>', FOP_Test_State::$esc_calls['esc_attr'] );
		$this->assertContains( FOP_CHAT_OPTION, FOP_Test_State::$esc_calls['esc_attr'] );
	}

	public function test_render_field_shows_saved_key() {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = self::KEY;
		ob_start();
		FOP_Chat_Settings::render_field();
		$out = ob_get_clean();
		$this->assertStringContainsString( 'name="fop_chat_site_key"', $out );
		$this->assertStringContainsString( 'value="' . self::KEY . '"', $out );
	}

	public function test_render_page_escapes_title() {
		ob_start();
		FOP_Chat_Settings::render_page();
		ob_end_clean();
		$this->assertContains( 'Front Office Pilot Chat', FOP_Test_State::$esc_calls['esc_html'] );
	}
}
