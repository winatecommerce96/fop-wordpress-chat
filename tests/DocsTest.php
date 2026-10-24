<?php
use PHPUnit\Framework\TestCase;

class DocsTest extends TestCase {
	private function readme_section( $file, $start_regex ) {
		$src = file_get_contents( dirname( __DIR__ ) . '/' . $file );
		$this->assertSame( 1, preg_match( $start_regex, $src, $m, PREG_OFFSET_CAPTURE ), "section missing in $file" );
		$rest = substr( $src, $m[0][1] + strlen( $m[0][0] ) );
		$end  = preg_match( '/^(==|## )[^=#]/m', $rest, $n, PREG_OFFSET_CAPTURE ) ? $n[0][1] : strlen( $rest );
		return substr( $rest, 0, $end );
	}

	public function services() {
		return array(
			'readme.txt' => array( 'readme.txt', '/^== External services ==\s*$/m' ),
			'README.md'  => array( 'README.md', '/^## What it sends\s*$/m' ),
		);
	}

	/**
	 * @dataProvider services
	 */
	public function test_names_both_services_with_terms_and_privacy( $file, $heading ) {
		$s = $this->readme_section( $file, $heading );
		foreach ( array(
			'chat.frontofficepilot.com',
			'https://frontofficepilot.com/terms-of-service/',
			'https://frontofficepilot.com/privacy-policy/',
			'api.vapi.ai',
			'https://vapi.ai/terms-of-service',
			'https://vapi.ai/privacy',
			'_ga',
			'site key',
			'origin',
			'page path',
		) as $needle ) {
			$this->assertStringContainsStringIgnoringCase( $needle, $s, "$file: $needle" );
		}
	}

	public function test_readme_headers() {
		$src = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
		$this->assertMatchesRegularExpression( '/^Tested up to:\s*7\.1\s*$/m', $src );
		$this->assertMatchesRegularExpression( '/^Requires PHP:\s*7\.4\s*$/m', $src );
		$this->assertMatchesRegularExpression( '/^Stable tag:\s*1\.0\.0\s*$/m', $src );
	}

	public function test_plugin_and_author_uri_are_distinct_real_pages() {
		$src = file_get_contents( dirname( __DIR__ ) . '/front-office-pilot-chat.php' );
		$this->assertMatchesRegularExpression( '#^\s*\*\s*Plugin URI:\s*https://frontofficepilot\.com/chat-pilot/\s*$#m', $src );
		$this->assertMatchesRegularExpression( '#^\s*\*\s*Author URI:\s*https://frontofficepilot\.com/\s*$#m', $src );
	}

	public function test_readme_md_has_contributors_todo() {
		$src = file_get_contents( dirname( __DIR__ ) . '/README.md' );
		$this->assertMatchesRegularExpression( '/TODO.*Contributors.*WordPress\.org/i', $src );
	}

	public function test_gplv2_license_file() {
		$src = file_get_contents( dirname( __DIR__ ) . '/LICENSE' );
		$this->assertStringContainsString( 'GNU GENERAL PUBLIC LICENSE', $src );
		$this->assertStringContainsString( 'Version 2, June 1991', $src );
		$this->assertStringContainsString( 'END OF TERMS AND CONDITIONS', $src );
	}
}
