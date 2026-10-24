<?php
use PHPUnit\Framework\TestCase;

class EmbedTest extends TestCase {
	const KEY = 'fop_pk_AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

	protected function setUp(): void {
		FOP_Test_State::reset();
	}

	public function test_init_hooks_enqueue_and_attributes_filter_only() {
		FOP_Chat_Embed::init();
		$this->assertSame( array( array( 'FOP_Chat_Embed', 'enqueue' ) ), FOP_Test_State::$actions['wp_enqueue_scripts'] );
		$this->assertSame( array( 'FOP_Chat_Embed', 'filter_attributes' ), FOP_Test_State::$filters['wp_script_attributes'][0][0] );
		$this->assertArrayNotHasKey( 'script_loader_tag', FOP_Test_State::$filters );
	}

	public function test_no_key_enqueues_nothing() {
		FOP_Chat_Embed::enqueue();
		$this->assertSame( array(), FOP_Test_State::$enqueued );
	}

	public function test_invalid_stored_key_enqueues_nothing() {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = '"><script>';
		FOP_Chat_Embed::enqueue();
		$this->assertSame( array(), FOP_Test_State::$enqueued );
	}

	public function test_key_enqueues_async_footer_script_without_version() {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = self::KEY;
		FOP_Chat_Embed::enqueue();
		$this->assertSame(
			array(
				'handle' => 'fop-chat',
				'src'    => FOP_CHAT_SCRIPT_URL,
				'deps'   => array(),
				'ver'    => null,
				'args'   => array( 'in_footer' => true, 'strategy' => 'async' ),
			),
			FOP_Test_State::$enqueued['fop-chat']
		);
	}

	public function test_enqueue_makes_no_remote_call() {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = self::KEY;
		FOP_Chat_Embed::enqueue();
		$this->assertSame( array(), FOP_Test_State::$remote_calls );
	}

	public function test_attributes_filter_adds_site_key_for_our_id() {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = self::KEY;
		$attrs = array( 'src' => FOP_CHAT_SCRIPT_URL, 'id' => 'fop-chat-js', 'async' => true, 'data-wp-strategy' => 'async' );
		$this->assertSame(
			array( 'src' => FOP_CHAT_SCRIPT_URL, 'id' => 'fop-chat-js', 'async' => true, 'data-wp-strategy' => 'async', 'data-site-key' => self::KEY ),
			FOP_Chat_Embed::filter_attributes( $attrs )
		);
	}

	/**
	 * @dataProvider other_attrs
	 */
	public function test_attributes_filter_leaves_other_scripts_alone( $attrs ) {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = self::KEY;
		$this->assertSame( $attrs, FOP_Chat_Embed::filter_attributes( $attrs ) );
	}

	public function other_attrs() {
		return array(
			'other id'    => array( array( 'src' => 'https://example.com/x.js', 'id' => 'jquery-core-js' ) ),
			'similar id'  => array( array( 'src' => 'https://example.com/x.js', 'id' => 'fop-chat-js-extra' ) ),
			'no id'       => array( array( 'src' => 'https://example.com/x.js' ) ),
			'inline'      => array( array( 'id' => 'fop-chat-js-after' ) ),
		);
	}

	public function test_attributes_filter_without_valid_key_adds_nothing() {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = '"><script>';
		$attrs = array( 'src' => FOP_CHAT_SCRIPT_URL, 'id' => 'fop-chat-js', 'async' => true );
		$this->assertSame( $attrs, FOP_Chat_Embed::filter_attributes( $attrs ) );
	}

	public function test_attributes_filter_tolerates_non_array() {
		$this->assertSame( 'x', FOP_Chat_Embed::filter_attributes( 'x' ) );
	}

	public function test_requires_wordpress_6_4_for_script_attributes() {
		$root = dirname( __DIR__ );
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Requires at least:\s*6\.4\s*$/m', file_get_contents( $root . '/front-office-pilot-chat.php' ) );
		$this->assertMatchesRegularExpression( '/^Requires at least:\s*6\.4\s*$/m', file_get_contents( $root . '/readme.txt' ) );
	}
}
