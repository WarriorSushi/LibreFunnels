const { test, expect } = require( '@playwright/test' );

const adminUser = process.env.LIBREFUNNELS_WP_ADMIN_USER || 'admin';
const adminPassword = process.env.LIBREFUNNELS_WP_ADMIN_PASSWORD || 'password';

async function loginToWordPress( page ) {
	await page.goto( '/wp-login.php' );

	const username = page.locator( '#user_login' );

	if ( await username.isVisible().catch( () => false ) ) {
		await username.fill( adminUser );
		await page.locator( '#user_pass' ).fill( adminPassword );
		await page.getByRole( 'button', { name: /log in/i } ).click();
		await expect( page ).toHaveURL( /wp-admin/ );
	}
}

test.describe( 'LibreFunnels canvas smoke', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await page.goto( '/wp-admin/admin.php?page=librefunnels' );
		await expect( page.getByText( 'Canvas Builder' ).first() ).toBeVisible();
	} );

	test( 'creates a checkout funnel with a page, products, and an order bump', async ( { page } ) => {
		await expect( page.getByText( 'Build funnels with clarity, not clutter.' ) ).toHaveCount( 0 );

		await page.getByRole( 'button', { name: 'Create funnel' } ).first().click();
		await expect( page.getByText( 'Funnel created' ) ).toBeVisible();

		await page.getByRole( 'button', { name: 'Build checkout path' } ).click();
		await expect( page.getByText( 'Starter path created' ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: 'Checkout', exact: true } ) ).toBeVisible();
		await expect( page.getByText( 'Thank You' ).first() ).toBeVisible();

		const pageTitle = `Playwright checkout ${ Date.now() }`;
		await page.getByLabel( 'Create page' ).fill( pageTitle );
		await page.getByRole( 'button', { name: 'Create and assign' } ).click();
		await expect( page.getByText( 'Page created and assigned' ) ).toBeVisible();
		await expect( page.getByRole( 'link', { name: 'Edit page design' } ) ).toBeVisible();

		const checkoutList = page.locator( '.lf-commerce-list--checkout-products' );
		const firstCheckoutCard = checkoutList.locator( '.lf-commerce-card' ).first();
		await firstCheckoutCard.locator( 'select' ).first().selectOption( { index: 1 } );
		await firstCheckoutCard.getByLabel( 'Quantity' ).fill( '2' );

		await page.getByRole( 'button', { name: 'Add product' } ).click();
		const secondCheckoutCard = checkoutList.locator( '.lf-commerce-card' ).nth( 1 );
		await secondCheckoutCard.locator( 'select' ).first().selectOption( { index: 2 } );
		await secondCheckoutCard.getByLabel( 'Quantity' ).fill( '1' );
		await page.getByRole( 'button', { name: 'Save checkout products' } ).click();
		await expect( page.getByText( 'Step saved' ) ).toBeVisible();

		const bumpList = page.locator( '.lf-commerce-list--order-bumps' );
		const firstBumpCard = bumpList.locator( '.lf-commerce-card' ).first();
		await firstBumpCard.locator( 'select' ).first().selectOption( { index: 1 } );
		await firstBumpCard.getByLabel( 'Offer title' ).fill( 'Priority setup' );
		await firstBumpCard.getByLabel( 'Quantity' ).fill( '1' );
		await page.getByRole( 'button', { name: 'Save order bumps' } ).click();
		await expect( page.getByText( 'Step saved' ) ).toBeVisible();
		await expect( page.getByText( 'Unsaved' ) ).toHaveCount( 0 );
	} );
} );
