const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const LOCAL_BASE_URL_PATTERN = /^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?\/?$/;

function cleanupLibreFunnelsTestArtifacts() {
	if ( process.env.LIBREFUNNELS_SKIP_TEST_CLEANUP === '1' ) {
		return;
	}

	const baseUrl = process.env.LIBREFUNNELS_WP_BASE_URL || 'http://localhost:8080';

	if ( ! LOCAL_BASE_URL_PATTERN.test( baseUrl ) ) {
		console.warn( `Skipping LibreFunnels cleanup for non-local URL: ${ baseUrl }` );
		return;
	}

	const repoRoot = path.resolve( __dirname, '../../..' );

	execFileSync(
		'docker',
		[
			'compose',
			'run',
			'--rm',
			'wpcli',
			'wp',
			'eval-file',
			'wp-content/plugins/librefunnels/tools/cleanup-test-artifacts.php',
			'--allow-root',
		],
		{
			cwd: repoRoot,
			stdio: 'inherit',
		}
	);
}

if ( require.main === module ) {
	cleanupLibreFunnelsTestArtifacts();
}

module.exports = {
	cleanupLibreFunnelsTestArtifacts,
};
