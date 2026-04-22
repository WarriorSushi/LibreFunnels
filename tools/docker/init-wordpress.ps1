$ErrorActionPreference = 'Stop'

$docker = $env:DOCKER_EXE

if (-not $docker) {
	$docker = 'C:\Program Files\Docker\Docker\resources\bin\docker.exe'
}

if (-not (Test-Path -LiteralPath $docker)) {
	throw 'Docker was not found. Set DOCKER_EXE to docker.exe or install Docker Desktop.'
}

function Invoke-WP {
	param(
		[Parameter(Mandatory = $true)]
		[string[]] $Arguments
	)

	& $docker compose run --rm wpcli wp @Arguments --path=/var/www/html --allow-root

	if ($LASTEXITCODE -ne 0) {
		throw "WP-CLI command failed: wp $($Arguments -join ' ')"
	}
}

function Test-WP {
	param(
		[Parameter(Mandatory = $true)]
		[string[]] $Arguments
	)

	& $docker compose run --rm wpcli wp @Arguments --path=/var/www/html --allow-root *> $null

	return $LASTEXITCODE -eq 0
}

& $docker compose up -d

$ready = $false

for ($attempt = 1; $attempt -le 30; $attempt++) {
	& $docker compose run --rm wpcli wp db query 'SELECT 1' --path=/var/www/html --allow-root *> $null

	if ($LASTEXITCODE -eq 0) {
		$ready = $true
		break
	}

	Start-Sleep -Seconds 3
}

if (-not $ready) {
	throw 'WordPress database did not become ready in time.'
}

& $docker compose exec -T -u root wordpress chown -R www-data:www-data /var/www/html/wp-content

if (-not (Test-WP -Arguments @( 'core', 'is-installed' ))) {
	Invoke-WP -Arguments @(
		'core',
		'install',
		'--url=http://localhost:8080',
		'--title=LibreFunnels Local',
		'--admin_user=admin',
		'--admin_password=password',
		'--admin_email=admin@example.test',
		'--skip-email'
	)
}

if (-not (Test-WP -Arguments @( 'plugin', 'is-installed', 'woocommerce' ))) {
	Invoke-WP -Arguments @(
		'plugin',
		'install',
		'woocommerce'
	)
}

Invoke-WP -Arguments @(
	'plugin',
	'activate',
	'woocommerce'
)

Invoke-WP -Arguments @(
	'plugin',
	'activate',
	'librefunnels'
)

Invoke-WP -Arguments @(
	'eval',
	'$products = wc_get_products( array( "limit" => 1, "return" => "ids" ) ); if ( empty( $products ) ) { $items = array( array( "LibreFunnels Sample Hoodie", "49" ), array( "LibreFunnels Setup Service", "199" ), array( "LibreFunnels Digital Guide", "29" ) ); foreach ( $items as $item ) { $product = new WC_Product_Simple(); $product->set_name( $item[0] ); $product->set_regular_price( $item[1] ); $product->set_status( "publish" ); $product->save(); } }'
)

Write-Host 'LibreFunnels local WordPress is ready at http://localhost:8080/wp-admin'
Write-Host 'Username: admin'
Write-Host 'Password: password'
