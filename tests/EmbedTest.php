<?php
use PHPUnit\Framework\TestCase;

class EmbedTest extends TestCase {
	const KEY = 'fop_pk_AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

	protected function setUp(): void {
		FOP_Test_State::reset();
	}

	public function test_init_hooks_front_end_only() {
		FOP_Chat_Embed::init();
		$this->assertArrayHasKey( 'wp_enqueue_scripts', FOP_Test_State::$actions );
		$this->assertArrayHasKey( 'script_loader_tag', FOP_Test_State::$filters );
		$this->assertSame( 3, FOP_Test_State::$filters['script_loader_tag'][0][2] );
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

	public function test_key_enqueues_script_without_version_in_footer() {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = self::KEY;
		FOP_Chat_Embed::enqueue();
		$this->assertArrayHasKey( FOP_Chat_Embed::HANDLE, FOP_Test_State::$enqueued );
		$s = FOP_Test_State::$enqueued[ FOP_Chat_Embed::HANDLE ];
		$this->assertSame( FOP_CHAT_SCRIPT_URL, $s['src'] );
		$this->assertSame( array(), $s['deps'] );
		$this->assertNull( $s['ver'] );
		$this->assertTrue( $s['in_footer'] );
	}

	public function test_enqueue_makes_no_remote_call() {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = self::KEY;
		FOP_Chat_Embed::enqueue();
		$this->assertSame( array(), FOP_Test_State::$remote_calls );
	}

	public function test_tag_is_exact_contract_shape() {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = self::KEY;
		$wp_tag = "<script src=\"" . FOP_CHAT_SCRIPT_URL . "\" id=\"fop-chat-js\"></script>\n";
		$out    = FOP_Chat_Embed::filter_tag( $wp_tag, FOP_Chat_Embed::HANDLE, FOP_CHAT_SCRIPT_URL );
		$this->assertSame(
			'<script src="https://chat.frontofficepilot.com/widget/v1/fop-chat.js" data-site-key="' . self::KEY . '" async></script>' . "\n",
			$out
		);
		$this->assertContains( self::KEY, FOP_Test_State::$esc_calls['esc_attr'] );
		$this->assertContains( FOP_CHAT_SCRIPT_URL, FOP_Test_State::$esc_calls['esc_url'] );
	}

	public function test_other_handles_untouched() {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = self::KEY;
		$tag = "<script src=\"https://example.com/x.js\" id=\"x-js\"></script>\n";
		$this->assertSame( $tag, FOP_Chat_Embed::filter_tag( $tag, 'jquery', 'https://example.com/x.js' ) );
	}

	public function test_hostile_stored_key_never_reaches_tag() {
		FOP_Test_State::$options[1][ FOP_CHAT_OPTION ] = '"><script>alert(1)</script>';
		$tag = "<script src=\"" . FOP_CHAT_SCRIPT_URL . "\" id=\"fop-chat-js\"></script>\n";
		$out = FOP_Chat_Embed::filter_tag( $tag, FOP_Chat_Embed::HANDLE, FOP_CHAT_SCRIPT_URL );
		$this->assertSame( '', $out );
	}
}
