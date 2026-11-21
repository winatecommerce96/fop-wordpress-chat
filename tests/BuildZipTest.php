<?php
use PHPUnit\Framework\TestCase;

class BuildZipTest extends TestCase {
	public function test_zip_contains_only_runtime_files() {
		if ( ! class_exists( 'ZipArchive' ) || '' === trim( (string) shell_exec( 'command -v zip' ) ) ) {
			$this->markTestSkipped( 'zip or ZipArchive not available' );
		}
		$root = dirname( __DIR__ );
		// A per-site fop-config.php left in the working tree must never reach the generic zip.
		$stray = $root . '/fop-config.php';
		file_put_contents( $stray, "<?php return array( 'site_key' => 'fop_pk_DDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDD' );\n" );
		try {
			$out = trim( (string) shell_exec( escapeshellarg( $root . '/bin/build-zip.sh' ) . ' 2>&1' ) );
		} finally {
			unlink( $stray );
		}
		$this->assertSame( $root . '/dist/front-office-pilot-chat-1.1.0.zip', $out );

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
				'front-office-pilot-chat/LICENSE',
				'front-office-pilot-chat/front-office-pilot-chat.php',
				'front-office-pilot-chat/includes/class-fop-chat-bundled.php',
				'front-office-pilot-chat/includes/class-fop-chat-embed.php',
				'front-office-pilot-chat/includes/class-fop-chat-settings.php',
				'front-office-pilot-chat/readme.txt',
				'front-office-pilot-chat/uninstall.php',
			),
			$names
		);
	}
}
