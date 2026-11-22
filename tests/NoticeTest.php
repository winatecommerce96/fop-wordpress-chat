<?php
/**
 * The one-time "clear your cache" notice after activation (1.1.1).
 *
 * @package FOP_Chat
 */

use PHPUnit\Framework\TestCase;

class NoticeTest extends TestCase {

	protected function setUp(): void {
		FOP_Test_State::reset();
	}

	private function render() {
		ob_start();
		FOP_Chat_Notice::render();
		return ob_get_clean();
	}

	public function test_nothing_shows_before_activation() {
		$this->assertSame( '', $this->render() );
	}

	public function test_activation_shows_it_once_to_an_admin_then_clears() {
		FOP_Chat_Notice::on_activate();
		$out = $this->render();
		$this->assertStringContainsString( 'notice-info is-dismissible', $out );
		$this->assertStringContainsString( 'Clear its cache now so visitors see the chat', $out );
		$this->assertStringContainsString( 'WP Rocket', $out );
		$this->assertSame( '', $this->render() );
		$this->assertFalse( get_option( FOP_Chat_Notice::OPTION, false ) );
	}

	public function test_a_user_who_cannot_manage_options_never_sees_it_and_it_stays_queued() {
		FOP_Chat_Notice::on_activate();
		FOP_Test_State::$can = false;
		$this->assertSame( '', $this->render() );
		$this->assertSame( '1', get_option( FOP_Chat_Notice::OPTION, '' ) );
		FOP_Test_State::$can = true;
		$this->assertStringContainsString( 'Clear its cache now', $this->render() );
	}

	public function test_the_text_is_escaped_plain_text() {
		$this->assertStringNotContainsString( '<', FOP_Chat_Notice::text() );
	}
}
