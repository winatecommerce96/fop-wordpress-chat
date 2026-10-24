<?php
use PHPUnit\Framework\TestCase;

class StatusCheckTest extends TestCase {
	const KEY = 'fop_pk_AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

	protected function setUp(): void {
		FOP_Test_State::reset();
		FOP_Chat_Settings::$status = null;
	}

	private function response( $code, $body ) {
		return array( 'response' => array( 'code' => $code ), 'body' => $body );
	}

	public function test_config_url_derived_from_script_url() {
		$this->assertSame( 'https://chat.frontofficepilot.com/widget/v1/config', FOP_Chat_Settings::config_url() );
		$this->assertSame(
			'https://fop-routing-abc123-uc.a.run.app/widget/v1/config',
			FOP_Chat_Settings::config_url( 'https://fop-routing-abc123-uc.a.run.app/widget/v1/fop-chat.js' )
		);
		$this->assertSame( 'http://localhost:8080/widget/v1/config', FOP_Chat_Settings::config_url( 'http://localhost:8080/widget/v1/fop-chat.js' ) );
	}

	/**
	 * @dataProvider origins
	 */
	public function test_site_origin( $home, $expected ) {
		FOP_Test_State::$home_url = $home;
		$this->assertSame( $expected, FOP_Chat_Settings::site_origin() );
	}

	public function origins() {
		return array(
			array( 'https://shop.example.com', 'https://shop.example.com' ),
			array( 'https://shop.example.com/blog', 'https://shop.example.com' ),
			array( 'http://localhost:8888/wp', 'http://localhost:8888' ),
			array( 'https://WWW.Example.com/', 'https://www.example.com' ),
			'https :443 dropped'   => array( 'https://shop.example.com:443', 'https://shop.example.com' ),
			'http :80 dropped'     => array( 'http://shop.example.com:80/', 'http://shop.example.com' ),
			'https :8443 kept'     => array( 'https://shop.example.com:8443', 'https://shop.example.com:8443' ),
			'http :443 kept'       => array( 'http://shop.example.com:443', 'http://shop.example.com:443' ),
			'subdirectory dropped' => array( 'https://example.com/shop/wp', 'https://example.com' ),
		);
	}

	public function test_request_shape() {
		FOP_Test_State::$home_url        = 'https://shop.example.com/sub';
		FOP_Test_State::$remote_response = $this->response( 200, '{"enabled":true}' );
		FOP_Chat_Settings::check_status( self::KEY );
		$this->assertCount( 1, FOP_Test_State::$remote_calls );
		$call = FOP_Test_State::$remote_calls[0];
		$this->assertSame( 'https://chat.frontofficepilot.com/widget/v1/config?k=' . rawurlencode( self::KEY ), $call['url'] );
		$this->assertSame( array( 'timeout' => 3, 'headers' => array( 'Origin' => 'https://shop.example.com' ) ), $call['args'] );
	}

	public function test_live_when_enabled_true() {
		FOP_Test_State::$remote_response = $this->response( 200, '{"enabled":true,"assistantId":"x"}' );
		$this->assertSame( array( 'state' => 'live', 'origin' => 'https://shop.example.com' ), FOP_Chat_Settings::check_status( self::KEY ) );
	}

	/**
	 * @dataProvider not_live
	 */
	public function test_not_live( $response ) {
		FOP_Test_State::$remote_response = $response;
		$status = FOP_Chat_Settings::check_status( self::KEY );
		$this->assertSame( array( 'state' => 'needs_origin', 'origin' => 'https://shop.example.com' ), $status );
	}

	public function not_live() {
		return array(
			'wp_error'       => array( new WP_Error( 'http_request_failed', 'timeout' ) ),
			'500'            => array( $this->response( 500, '{"enabled":true}' ) ),
			'enabled false'  => array( $this->response( 200, '{"enabled":false}' ) ),
			'enabled string' => array( $this->response( 200, '{"enabled":"true"}' ) ),
			'enabled 1'      => array( $this->response( 200, '{"enabled":1}' ) ),
			'bad json'       => array( $this->response( 200, 'not json' ) ),
			'json array'     => array( $this->response( 200, '[true]' ) ),
			'empty'          => array( $this->response( 200, '' ) ),
		);
	}

	public function test_render_live_notice() {
		ob_start();
		FOP_Chat_Settings::render_status( array( 'state' => 'live', 'origin' => 'https://shop.example.com' ) );
		$out = ob_get_clean();
		$this->assertStringContainsString( 'Chat is live on https://shop.example.com.', $out );
		$this->assertStringNotContainsString( FOP_CHAT_DASHBOARD_URL, $out );
		$this->assertContains( 'Chat is live on https://shop.example.com.', FOP_Test_State::$esc_calls['esc_html'] );
	}

	public function test_render_needs_origin_notice_with_dashboard_link() {
		ob_start();
		FOP_Chat_Settings::render_status( array( 'state' => 'needs_origin', 'origin' => 'https://shop.example.com' ) );
		$out = ob_get_clean();
		$this->assertStringContainsString( 'Add https://shop.example.com in your Front Office Pilot dashboard under Chat widget.', $out );
		$this->assertStringContainsString( 'href="https://app.frontofficepilot.com/chat-widget"', $out );
		$this->assertContains( FOP_CHAT_DASHBOARD_URL, FOP_Test_State::$esc_calls['esc_url'] );
		$this->assertContains( 'Add https://shop.example.com in your Front Office Pilot dashboard under Chat widget.', FOP_Test_State::$esc_calls['esc_html'] );
	}

	/**
	 * @dataProvider unsupported_homes
	 */
	public function test_unsupported_origin_makes_no_remote_call( $home, $origin ) {
		FOP_Test_State::$home_url        = $home;
		FOP_Test_State::$remote_response = $this->response( 200, '{"enabled":true}' );
		$this->assertSame( array( 'state' => 'unsupported', 'origin' => $origin ), FOP_Chat_Settings::check_status( self::KEY ) );
		$this->assertSame( array(), FOP_Test_State::$remote_calls );
	}

	public function unsupported_homes() {
		return array(
			'http'            => array( 'http://shop.example.com', 'http://shop.example.com' ),
			'http :80'        => array( 'http://shop.example.com:80', 'http://shop.example.com' ),
			'https :8443'     => array( 'https://shop.example.com:8443/wp', 'https://shop.example.com:8443' ),
			'http localhost'  => array( 'http://localhost:8888/wp', 'http://localhost:8888' ),
		);
	}

	public function test_https_443_is_checked_without_port() {
		FOP_Test_State::$home_url        = 'https://shop.example.com:443/blog';
		FOP_Test_State::$remote_response = $this->response( 200, '{"enabled":true}' );
		$this->assertSame( array( 'state' => 'live', 'origin' => 'https://shop.example.com' ), FOP_Chat_Settings::check_status( self::KEY ) );
		$this->assertSame( 'https://shop.example.com', FOP_Test_State::$remote_calls[0]['args']['headers']['Origin'] );
	}

	public function test_render_unsupported_notice() {
		ob_start();
		FOP_Chat_Settings::render_status( array( 'state' => 'unsupported', 'origin' => 'http://shop.example.com' ) );
		$out = ob_get_clean();
		$msg = "The chat widget needs your site on https without a custom port; http://shop.example.com can't be added.";
		$this->assertStringContainsString( htmlspecialchars( $msg, ENT_QUOTES, 'UTF-8' ), $out );
		$this->assertContains( $msg, FOP_Test_State::$esc_calls['esc_html'] );
		$this->assertStringNotContainsString( 'Chat is live', $out );
		$this->assertStringNotContainsString( 'under Chat widget.', $out );
	}

	public function test_on_load_unsupported_shows_notice_and_no_call() {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = self::KEY;
		FOP_Test_State::$home_url                     = 'http://shop.example.com';
		FOP_Chat_Settings::on_load();
		$this->assertSame( array(), FOP_Test_State::$remote_calls );
		ob_start();
		FOP_Chat_Settings::render_page();
		$out = ob_get_clean();
		$this->assertStringContainsString( 'needs your site on https without a custom port', $out );
	}

	public function test_status_hooked_only_on_settings_page_load() {
		FOP_Chat_Settings::init();
		$this->assertArrayNotHasKey( 'wp_enqueue_scripts', FOP_Test_State::$actions );
		$this->assertArrayNotHasKey( 'admin_notices', FOP_Test_State::$actions );
		foreach ( FOP_Test_State::$actions['admin_menu'] as $cb ) {
			call_user_func( $cb );
		}
		$this->assertArrayHasKey( 'load-settings_page_fop-chat', FOP_Test_State::$actions );
		$this->assertSame( array( array( 'FOP_Chat_Settings', 'on_load' ) ), FOP_Test_State::$actions['load-settings_page_fop-chat'] );
	}

	public function test_admin_init_and_menu_make_no_remote_call() {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = self::KEY;
		FOP_Chat_Settings::register();
		FOP_Chat_Settings::add_page();
		$this->assertSame( array(), FOP_Test_State::$remote_calls );
	}

	public function test_on_load_without_key_makes_no_call() {
		FOP_Chat_Settings::on_load();
		$this->assertSame( array(), FOP_Test_State::$remote_calls );
		$this->assertNull( FOP_Chat_Settings::$status );
	}

	public function test_on_load_with_key_checks_and_page_shows_result() {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = self::KEY;
		FOP_Test_State::$remote_response              = $this->response( 200, '{"enabled":false}' );
		FOP_Chat_Settings::on_load();
		$this->assertCount( 1, FOP_Test_State::$remote_calls );
		ob_start();
		FOP_Chat_Settings::render_page();
		$out = ob_get_clean();
		$this->assertStringContainsString( 'under Chat widget.', $out );
	}

	public function test_override_moves_both_urls() {
		$script = 'define( "FOP_CHAT_SCRIPT_URL", "https://fop-routing-abc123-uc.a.run.app/widget/v1/fop-chat.js" );'
			. 'require ' . var_export( __DIR__ . '/bootstrap.php', true ) . ';'
			. 'FOP_Chat_Settings::check_status( "' . self::KEY . '" );'
			. 'echo FOP_CHAT_SCRIPT_URL, "\n", FOP_Test_State::$remote_calls[0]["url"];';
		$out = shell_exec( escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $script ) . ' 2>&1' );
		$this->assertSame(
			"https://fop-routing-abc123-uc.a.run.app/widget/v1/fop-chat.js\nhttps://fop-routing-abc123-uc.a.run.app/widget/v1/config?k=" . self::KEY,
			$out
		);
	}
}
