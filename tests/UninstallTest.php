<?php
use PHPUnit\Framework\TestCase;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'fop-wordpress-chat/fop-chat.php' );
}

class UninstallTest extends TestCase {
	protected function setUp(): void {
		FOP_Test_State::reset();
	}

	public function test_single_site_deletes_option() {
		FOP_Test_State::$options[1] = array( 'fop_chat_site_key' => 'fop_pk_' . str_repeat( 'A', 32 ), 'blogname' => 'x' );
		include dirname( __DIR__ ) . '/uninstall.php';
		$this->assertSame( array( 'blogname' => 'x' ), FOP_Test_State::$options[1] );
	}

	public function test_multisite_deletes_option_on_every_site() {
		FOP_Test_State::$multisite = true;
		FOP_Test_State::$sites     = array( 1, 2, 5 );
		foreach ( array( 1, 2, 5 ) as $id ) {
			FOP_Test_State::$options[ $id ] = array( 'fop_chat_site_key' => 'fop_pk_' . str_repeat( 'B', 32 ), 'keep' => $id );
		}
		include dirname( __DIR__ ) . '/uninstall.php';
		foreach ( array( 1, 2, 5 ) as $id ) {
			$this->assertSame( array( 'keep' => $id ), FOP_Test_State::$options[ $id ] );
		}
		$this->assertSame( 1, FOP_Test_State::$blog_id );
	}
}
