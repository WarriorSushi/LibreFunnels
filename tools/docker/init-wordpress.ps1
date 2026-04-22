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
}

& $docker compose up -d

$ready = $false

for ($attempt = 1; $attempt -le 30; $attempt++) {
	& $docker compose run --rm wpcli wp db check --path=/var/www/html --allow-root *> $null

	if ($LASTEXITCODE -eq 0) {
		$ready = $true
		break
	}

	Start-Sleep -Seconds 3
}

if (-not $ready) {
	throw 'WordPress database did not become ready in time.'
}

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

if ($LASTEXITCODE -ne 0) {
	Invoke-WP -Arguments @(
		'core',
		'is-installed'
	)
}

Invoke-WP -Arguments @(
	'plugin',
	'is-installed',
	'woocommerce'
)

if ($LASTEXITCODE -ne 0) {
	Invoke-WP -Arguments @(
		'plugin',
		'install',
		'woocommerce'
	)
}

Invoke-WP -Arguments @(
	'plugin',
	'activate',
	'woocommerce',
	'librefunnels'
)

Invoke-WP -Arguments @(
	'eval',
	'$products = wc_get_products( array( "limit" => 1, "return" => "ids" ) ); if ( empty( $products ) ) { $items = array( array( "LibreFunnels Sample Hoodie", "49" ), array( "LibreFunnels Setup Service", "199" ), array( "LibreFunnels Digital Guide", "29" ) ); foreach ( $items as $item ) { $product = new WC_Product_Simple(); $product->set_name( $item[0] ); $product->set_regular_price( $item[1] ); $product->set_status( "publish" ); $product->save(); } }'
)

Write-Host 'LibreFunnels local WordPress is ready at http://localhost:8080/wp-admin'
Write-Host 'Username: admin'
Write-Host 'Password: password'
