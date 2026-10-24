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
}
