<?php
use PHPUnit\Framework\TestCase;

class BuildZipTest extends TestCase {
	public function test_zip_contains_only_runtime_files() {
		if ( ! class_exists( 'ZipArchive' ) || '' === trim( (string) shell_exec( 'command -v zip' ) ) ) {
			$this->markTestSkipped( 'zip or ZipArchive not available' );
		}
		$root = dirname( __DIR__ );
		$out  = trim( (string) shell_exec( escapeshellarg( $root . '/bin/build-zip.sh' ) . ' 2>&1' ) );
		$this->assertSame( $root . '/dist/fop-wordpress-chat-1.0.0.zip', $out );

		$zip = new ZipArchive();
		$this->assertTrue( true === $zip->open( $out ) );
		$names = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );
			if ( '/' !== substr( $name, -1 ) ) {
				$names[] = $name;
			}
		}
		$zip->close();
		sort( $names );
		$this->assertSame(
			array(
				'fop-wordpress-chat/fop-chat.php',
				'fop-wordpress-chat/includes/class-fop-chat-embed.php',
				'fop-wordpress-chat/includes/class-fop-chat-settings.php',
				'fop-wordpress-chat/readme.txt',
				'fop-wordpress-chat/uninstall.php',
			),
			$names
		);
	}
}
