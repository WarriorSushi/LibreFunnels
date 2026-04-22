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
		await expect( page.getByText( 'Draft page' ).first() ).toBeVisible();
		await expect( page.getByRole( 'link', { name: 'Edit page design' } ) ).toBeVisible();
		await expect( page.getByRole( 'link', { name: 'Preview page' } ) ).toBeVisible();

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

		const checkoutNode = page.locator( '.lf-node' ).filter( { hasText: 'Checkout' } ).first();
		const beforeDrag = await checkoutNode.boundingBox();
		expect( beforeDrag ).not.toBeNull();
		await checkoutNode.hover( { position: { x: 24, y: 24 } } );
		await page.mouse.down();
		await page.mouse.move( beforeDrag.x + 120, beforeDrag.y + 48, { steps: 8 } );
		await page.mouse.up();

		const afterDrag = await checkoutNode.boundingBox();
		expect( afterDrag.x ).toBeGreaterThan( beforeDrag.x + 60 );

		await page.reload();
		await expect( page.getByText( 'Canvas Builder' ).first() ).toBeVisible();
		const persistedCheckoutNode = page.locator( '.lf-node' ).filter( { hasText: 'Checkout' } ).first();
		const afterReload = await persistedCheckoutNode.boundingBox();
		expect( afterReload.x ).toBeGreaterThan( beforeDrag.x + 60 );

		const routeButton = page.getByRole( 'button', { name: /Route Continue from Checkout to Thank You/ } );
		await routeButton.focus();
		await routeButton.press( 'Enter' );
		await expect( page.getByRole( 'heading', { name: 'Continue' } ) ).toBeVisible();
		await page.getByLabel( 'Route label' ).selectOption( 'conditional' );

		const conditionPanel = page.locator( '.lf-panellet' ).filter( { hasText: 'Condition' } );
		await expect( conditionPanel.getByText( 'Condition', { exact: true } ) ).toBeVisible();
		await conditionPanel.getByLabel( 'Rule' ).selectOption( 'cart_contains_product' );
		await conditionPanel.getByLabel( 'Find product' ).fill( 'Digital' );
		await expect( conditionPanel.locator( 'select' ).last() ).toContainText( 'Digital' );
		await conditionPanel.locator( 'select' ).last().selectOption( { index: 1 } );
		await page.getByRole( 'button', { name: 'Save route' } ).click();
		await expect( page.getByText( 'Route saved' ) ).toBeVisible();

		await page.getByRole( 'button', { name: 'Add offer' } ).click();
		await expect( page.getByRole( 'heading', { name: 'Upsell' } ) ).toBeVisible();
		const offerPanel = page.locator( '.lf-panellet' ).filter( { hasText: 'Offer product' } );
		await offerPanel.getByLabel( 'Find product' ).fill( 'Setup' );
		await expect( offerPanel.locator( 'select' ).first() ).toContainText( 'Setup' );
		await offerPanel.locator( 'select' ).first().selectOption( { index: 1 } );
		await offerPanel.getByLabel( 'Offer title' ).fill( 'Setup boost' );
		await page.getByRole( 'button', { name: 'Save offer' } ).click();
		await expect( page.getByText( 'Step saved' ) ).toBeVisible();
	} );
} );
