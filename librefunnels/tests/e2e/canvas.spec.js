const { test, expect } = require( '@playwright/test' );

const adminUser = process.env.LIBREFUNNELS_WP_ADMIN_USER || 'admin';
const adminPassword = process.env.LIBREFUNNELS_WP_ADMIN_PASSWORD || 'password';

async function loginToWordPress( page ) {
	await page.goto( '/wp-login.php' );

	const username = page.locator( '#user_login' );

	if ( await username.isVisible().catch( () => false ) ) {
		await username.fill( '' );
		await username.fill( adminUser );
		const password = page.locator( '#user_pass' );
		await password.fill( '' );
		await password.fill( adminPassword );
		await username.evaluate( ( element, value ) => {
			element.value = value;
			element.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			element.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}, adminUser );
		await password.evaluate( ( element, value ) => {
			element.value = value;
			element.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			element.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}, adminPassword );
		await expect( username ).toHaveValue( adminUser );
		await expect( password ).toHaveValue( adminPassword );
		await page.getByRole( 'button', { name: /log in/i } ).click();
		await expect( page ).toHaveURL( /wp-admin/ );
	}
}

async function createFunnelFromCanvasButton( page ) {
	await page.getByRole( 'button', { name: 'Create funnel' } ).first().click();
	await expect( page.getByText( 'Funnel created' ) ).toBeVisible();

	const selectedFunnelId = await page.evaluate( () =>
		Number( window.localStorage.getItem( 'librefunnels.selectedFunnelId' ) || 0 )
	);

	expect( selectedFunnelId ).toBeGreaterThan( 0 );
	await page.goto( '/wp-admin/admin.php?page=librefunnels-funnels' );
	await expect( page.getByRole( 'button', { name: 'Build checkout path' } ) ).toBeVisible();

	return selectedFunnelId;
}

async function createPublishedFunnelPages( page, options = {} ) {
	const includeOffer = Boolean( options.includeOffer );
	const checkoutProductPrice = options.checkoutProductPrice ? String( options.checkoutProductPrice ) : '';
	const stamp = Date.now();

	return page.evaluate( async ( { includeOffer: shouldIncludeOffer, checkoutProductPrice: setupCheckoutProductPrice, stamp: setupStamp } ) => {
		const apiFetch = window.wp.apiFetch;
		const canvasPath = window.libreFunnelsAdmin.rest.canvas;

		function findStep( workspace, funnelId, title ) {
			return workspace.steps.find( ( step ) => Number( step.funnelId ) === Number( funnelId ) && step.title === title );
		}

		async function createStep( funnelId, title, type, order, position ) {
			const workspace = await apiFetch( {
				path: `${ canvasPath }/funnels/${ funnelId }/steps`,
				method: 'POST',
				data: {
					title,
					type,
					order,
					page_id: 0,
					position,
				},
			} );

			return {
				step: findStep( workspace, funnelId, title ),
				workspace,
			};
		}

		async function createAndPublishPage( stepId, title ) {
			const created = await apiFetch( {
				path: `${ canvasPath }/pages`,
				method: 'POST',
				data: {
					step_id: stepId,
					title,
				},
			} );
			const pageId = created.page.id;
			const published = await apiFetch( {
				path: `/wp/v2/pages/${ pageId }`,
				method: 'POST',
				data: {
					status: 'publish',
				},
			} );

			return {
				id: pageId,
				url: published.link,
			};
		}

		const funnelTitle = `Public smoke funnel ${ setupStamp }`;
		const createdFunnel = await apiFetch( {
			path: `${ canvasPath }/funnels`,
			method: 'POST',
			data: {
				title: funnelTitle,
			},
		} );
		const funnelId = createdFunnel.selectedFunnelId;
		const checkoutTitle = `Public Checkout ${ setupStamp }`;
		const thankYouTitle = `Public Thank You ${ setupStamp }`;
		const offerTitle = `Public Upsell ${ setupStamp }`;
		const checkoutResult = await createStep( funnelId, checkoutTitle, 'checkout', 1, { x: 120, y: 160 } );
		const checkoutStep = checkoutResult.step;
		const offerResult = shouldIncludeOffer
			? await createStep( funnelId, offerTitle, 'upsell', 2, { x: 440, y: 160 } )
			: null;
		const offerStep = offerResult?.step || null;
		const thankYouResult = await createStep(
			funnelId,
			thankYouTitle,
			'thank_you',
			shouldIncludeOffer ? 3 : 2,
			{ x: shouldIncludeOffer ? 760 : 440, y: 160 }
		);
		const thankYouStep = thankYouResult.step;
		const latestWorkspace = thankYouResult.workspace;
		let checkoutProduct =
			latestWorkspace.products.find( ( product ) => product.name.includes( 'Digital' ) ) ||
			latestWorkspace.products[ 0 ];
		const offerProduct =
			latestWorkspace.products.find( ( product ) => product.name.includes( 'Setup' ) ) ||
			latestWorkspace.products[ 1 ] ||
			checkoutProduct;

		if ( setupCheckoutProductPrice ) {
			checkoutProduct = await apiFetch( {
				path: '/wc/v3/products',
				method: 'POST',
				data: {
					name: `LibreFunnels Revenue Smoke ${ setupStamp }`,
					type: 'simple',
					regular_price: setupCheckoutProductPrice,
					status: 'publish',
					virtual: true,
				},
			} );

			await apiFetch( {
				path: '/wc/v3/payment_gateways/cheque',
				method: 'PUT',
				data: {
					enabled: true,
				},
			} );
		}

		await apiFetch( {
			path: `${ canvasPath }/steps/${ checkoutStep.id }`,
			method: 'POST',
			data: {
				checkout_products: [
					{
						product_id: checkoutProduct.id,
						variation_id: 0,
						quantity: 1,
						variation: {},
					},
				],
			},
		} );

		if ( shouldIncludeOffer ) {
			await apiFetch( {
				path: `${ canvasPath }/steps/${ offerStep.id }`,
				method: 'POST',
				data: {
					offer: {
						id: `offer-${ setupStamp }`,
						product_id: offerProduct.id,
						variation_id: 0,
						quantity: 1,
						variation: {},
						title: `Setup boost ${ setupStamp }`,
						description: 'A focused implementation boost before checkout.',
						discount_type: 'none',
						discount_amount: 0,
						enabled: true,
					},
				},
			} );
		}

		const checkoutPage = await createAndPublishPage( checkoutStep.id, `${ checkoutTitle } Page` );
		const offerPage = shouldIncludeOffer ? await createAndPublishPage( offerStep.id, `${ offerTitle } Page` ) : null;
		const thankYouPage = await createAndPublishPage( thankYouStep.id, `${ thankYouTitle } Page` );
		const nodes = [
			{ id: `node-${ checkoutStep.id }`, stepId: checkoutStep.id, type: 'checkout', position: { x: 120, y: 160 } },
			...( shouldIncludeOffer ? [ { id: `node-${ offerStep.id }`, stepId: offerStep.id, type: 'upsell', position: { x: 440, y: 160 } } ] : [] ),
			{
				id: `node-${ thankYouStep.id }`,
				stepId: thankYouStep.id,
				type: 'thank_you',
				position: { x: shouldIncludeOffer ? 760 : 440, y: 160 },
			},
		];
		const edges = shouldIncludeOffer
			? [
				{ id: `edge-checkout-offer-${ setupStamp }`, source: `node-${ checkoutStep.id }`, target: `node-${ offerStep.id }`, route: 'next', rule: {} },
				{ id: `edge-offer-accept-${ setupStamp }`, source: `node-${ offerStep.id }`, target: `node-${ thankYouStep.id }`, route: 'accept', rule: {} },
				{ id: `edge-offer-reject-${ setupStamp }`, source: `node-${ offerStep.id }`, target: `node-${ thankYouStep.id }`, route: 'reject', rule: {} },
			]
			: [
				{ id: `edge-checkout-thanks-${ setupStamp }`, source: `node-${ checkoutStep.id }`, target: `node-${ thankYouStep.id }`, route: 'next', rule: {} },
			];

		await apiFetch( {
			path: `${ canvasPath }/funnels/${ funnelId }/graph`,
			method: 'POST',
			data: {
				graph: {
					version: 1,
					nodes,
					edges,
				},
				start_step_id: checkoutStep.id,
			},
		} );

		return {
			funnelId,
			checkoutTitle,
			checkoutUrl: checkoutPage.url,
			checkoutProductName: checkoutProduct.name,
			checkoutProductPrice: Number( setupCheckoutProductPrice || checkoutProduct.price || 0 ),
			offerTitle,
			offerUrl: offerPage?.url || '',
			offerHeadline: shouldIncludeOffer ? `Setup boost ${ setupStamp }` : '',
			offerProductName: offerProduct.name,
			thankYouTitle,
			thankYouUrl: thankYouPage.url,
		};
	}, { includeOffer, checkoutProductPrice, stamp } );
}

async function fillCheckoutBillingDetails( page, stamp = Date.now() ) {
	await page.locator( '#billing_first_name' ).fill( 'Liberty' );
	await page.locator( '#billing_last_name' ).fill( 'Buyer' );
	await page.locator( '#billing_address_1' ).fill( '100 Funnel Street' );
	await page.locator( '#billing_city' ).fill( 'San Francisco' );
	await page.locator( '#billing_postcode' ).fill( '94105' );
	await page.locator( '#billing_phone' ).fill( '4155550199' );
	await page.locator( '#billing_email' ).fill( `librefunnels-${ stamp }@example.test` );

	const country = page.locator( '#billing_country' );
	if ( await country.count() ) {
		await country.selectOption( 'US' );
	}

	const state = page.locator( '#billing_state' );
	if ( await state.count() ) {
		const tagName = await state.evaluate( ( element ) => element.tagName.toLowerCase() );

		if ( tagName === 'select' ) {
			await state.selectOption( 'CA' );
		} else {
			await state.fill( 'CA' );
		}
	}
}

test.describe( 'LibreFunnels canvas smoke', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await page.goto( '/wp-admin/admin.php?page=librefunnels-funnels' );
		await expect( page.getByText( 'Funnel Workspace' ).first() ).toBeVisible();
	} );

	test( 'renders distinct LibreFunnels submenu screens', async ( { page } ) => {
		const app = page.locator( '#librefunnels-admin-app' );

		await page.goto( '/wp-admin/admin.php?page=librefunnels' );
		await expect( app.getByRole( 'heading', { name: 'Dashboard', exact: true } ) ).toBeVisible();
		await expect( app.getByText( 'Store owner command center', { exact: true } ) ).toBeVisible();

		await page.goto( '/wp-admin/admin.php?page=librefunnels-templates' );
		await expect( app.getByRole( 'heading', { name: 'Templates', exact: true } ) ).toBeVisible();
		await expect( app.getByText( 'Template library', { exact: true } ) ).toBeVisible();
		await expect( app.getByText( 'Starter Checkout Funnel', { exact: true } ) ).toBeVisible();

		await page.goto( '/wp-admin/admin.php?page=librefunnels-analytics' );
		await expect( app.getByRole( 'heading', { name: 'Analytics', exact: true } ) ).toBeVisible();
		await expect( app.getByText( 'Local analytics', { exact: true } ) ).toBeVisible();

		await page.goto( '/wp-admin/admin.php?page=librefunnels-settings' );
		await expect( app.getByRole( 'heading', { name: 'Settings', exact: true } ) ).toBeVisible();
		await expect( app.getByText( 'Global controls', { exact: true } ) ).toBeVisible();

		await page.goto( '/wp-admin/admin.php?page=librefunnels-setup' );
		await expect( app.getByRole( 'heading', { name: 'Setup', exact: true } ) ).toBeVisible();
		await expect( app.getByText( 'Launch readiness', { exact: true } ) ).toBeVisible();
		await expect( app.getByText( 'Permalinks', { exact: true } ) ).toBeVisible();
	} );

	test( 'creates starter funnels from templates and imports exported JSON', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=librefunnels-templates' );

		const guidedStarter = page.locator( '.lf-guided-starter' );
		await guidedStarter.getByLabel( 'Starter funnel title (optional)' ).fill( 'Guided starter funnel' );
		await guidedStarter.getByLabel( 'Checkout product' ).fill( 'Digital' );
		await expect( guidedStarter.locator( '.lf-product-picker' ).first().locator( 'select' ) ).toContainText( 'Digital' );
		await guidedStarter.locator( '.lf-product-picker' ).first().locator( 'select' ).selectOption( { index: 1 } );
		const selectedCheckoutProductId = Number(
			await guidedStarter.locator( '.lf-product-picker' ).first().locator( 'select' ).inputValue()
		);

		expect( selectedCheckoutProductId ).toBeGreaterThan( 0 );
		await guidedStarter.getByRole( 'button', { name: 'Create guided starter' } ).click();

		await expect( page ).toHaveURL( /librefunnels-funnels.*tab=steps/, { timeout: 30000 } );
		await expect( page.getByRole( 'heading', { level: 1, name: 'Guided starter funnel', exact: true } ) ).toBeVisible();
		const starterStepTypes = await page.locator( '.lf-step-table .lf-node__type' ).allTextContents();
		expect( starterStepTypes ).toEqual( expect.arrayContaining( [ 'Landing', 'Checkout', 'Thank You' ] ) );
		await expect( page.getByRole( 'link', { name: 'Edit page design' } ).first() ).toBeVisible();
		await expect( page.getByRole( 'link', { name: 'Preview page' } ).first() ).toBeVisible();

		const exportedPackage = await page.evaluate( async () => {
			const selectedFunnelId = Number( window.localStorage.getItem( 'librefunnels.selectedFunnelId' ) || 0 );

			return window.wp.apiFetch( {
				path: `${ window.libreFunnelsAdmin.rest.exportBase }/${ selectedFunnelId }/export`,
			} );
		} );

		expect( exportedPackage.package.format ).toBe( 'librefunnels.funnel' );
		const exportedCheckoutStep = exportedPackage.package.steps.find( ( step ) => step.type === 'checkout' );
		expect( Number( exportedCheckoutStep.checkoutProducts[ 0 ].product_id ) ).toBe( selectedCheckoutProductId );

		await page.goto( '/wp-admin/admin.php?page=librefunnels-templates' );
		await page.getByLabel( 'Imported funnel title (optional)' ).fill( 'Imported starter funnel' );
		await page.getByLabel( 'Paste funnel JSON' ).fill( JSON.stringify( exportedPackage.package, null, 2 ) );
		await page.getByRole( 'button', { name: 'Import funnel' } ).click();
		await expect( page.getByText( 'Funnel imported' ) ).toBeVisible();

		await page.goto( '/wp-admin/admin.php?page=librefunnels-funnels' );
		await expect( page.getByRole( 'heading', { level: 1, name: 'Imported starter funnel', exact: true } ) ).toBeVisible();
		await page.getByRole( 'button', { name: 'Steps', exact: true } ).click();
		const importedStepTypes = await page.locator( '.lf-step-table .lf-node__type' ).allTextContents();
		expect( importedStepTypes ).toEqual( expect.arrayContaining( [ 'Landing', 'Checkout', 'Thank You' ] ) );
	} );

	test( 'creates a checkout funnel with a page, products, and an order bump', async ( { page } ) => {
		await expect( page.getByText( 'Build funnels with clarity, not clutter.' ) ).toHaveCount( 0 );

		await createFunnelFromCanvasButton( page );
		await page.getByRole( 'button', { name: 'Analytics' } ).click();
		await expect( page.locator( '.lf-analytics' ) ).toContainText( 'Waiting for shopper data' );
		await page.getByRole( 'button', { name: 'Overview' } ).click();

		await page.getByRole( 'button', { name: 'Build checkout path' } ).click();
		await expect( page.getByText( 'Starter path created' ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: 'Checkout', exact: true } ) ).toBeVisible();
		await expect( page.getByText( 'Thank You' ).first() ).toBeVisible();
		await expect( page.getByText( '3 of 7 ready' ) ).toBeVisible();
		await page.getByRole( 'button', { name: 'Overview', exact: true } ).click();
		await expect( page.getByText( 'Create pages for Checkout, Thank You.' ) ).toBeVisible();
		await page.getByRole( 'button', { name: 'Canvas', exact: true } ).click();

		const pageTitle = `Playwright checkout ${ Date.now() }`;
		await page.getByLabel( 'Create page' ).fill( pageTitle );
		await page.getByRole( 'button', { name: 'Create and assign' } ).click();
		await expect( page.getByText( 'Page created and assigned' ) ).toBeVisible();
		await expect( page.getByText( 'Draft page' ).first() ).toBeVisible();
		await page.getByRole( 'button', { name: 'Overview', exact: true } ).click();
		await expect( page.getByText( 'Create a page for Thank You.' ) ).toBeVisible();
		await expect( page.getByText( 'Edit and publish Checkout.' ) ).toBeVisible();
		await page.getByRole( 'button', { name: 'Canvas', exact: true } ).click();
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
		await expect( page.getByText( '4 of 7 ready' ) ).toBeVisible();

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
		await expect( page.getByText( 'Canvas saved' ) ).toBeVisible();

		const afterDrag = await checkoutNode.boundingBox();
		expect( afterDrag.x ).toBeGreaterThan( beforeDrag.x + 60 );

		await page.reload();
		await expect( page.getByText( 'Funnel Workspace' ).first() ).toBeVisible();
		await page.getByRole( 'button', { name: 'Canvas', exact: true } ).click();
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

		await page.getByRole( 'button', { name: 'Add Upsell' } ).click();
		await expect( page.getByRole( 'heading', { name: 'Upsell' } ) ).toBeVisible();
		const offerPanel = page.locator( '.lf-panellet' ).filter( { hasText: 'Offer product' } );
		await offerPanel.getByLabel( 'Find product' ).fill( 'Digital' );
		await expect( offerPanel.locator( 'select' ).first() ).toContainText( 'Digital' );
		await offerPanel.locator( 'select' ).first().selectOption( { index: 1 } );
		await offerPanel.getByLabel( 'Offer title' ).fill( 'Setup boost' );
		await page.getByRole( 'button', { name: 'Save offer' } ).click();
		await expect( page.getByText( 'Step saved' ) ).toBeVisible();
	} );

	test( 'keeps imported broken routes visible and selectable', async ( { page } ) => {
		await createFunnelFromCanvasButton( page );

		await page.getByRole( 'button', { name: 'Build checkout path' } ).click();
		await expect( page.getByText( 'Starter path created' ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: 'Checkout', exact: true } ) ).toBeVisible();

		await page.evaluate( async () => {
			const apiFetch = window.wp.apiFetch;
			const canvasPath = window.libreFunnelsAdmin.rest.canvas;
			const workspace = await apiFetch( { path: canvasPath } );
			const funnel = workspace.funnels[ 0 ];
			const brokenGraph = {
				...funnel.graph,
				edges: funnel.graph.edges.map( ( edge, index ) =>
					index === 0 ? { ...edge, target: 'missing-node' } : edge
				),
			};

			await apiFetch( {
				path: `${ canvasPath }/funnels/${ funnel.id }/graph`,
				method: 'POST',
				data: {
					graph: brokenGraph,
					start_step_id: funnel.startStepId,
				},
			} );
		} );

		await page.reload();
		await expect( page.getByText( 'Funnel Workspace' ).first() ).toBeVisible();
		await page.getByRole( 'button', { name: 'Canvas', exact: true } ).click();
		await expect( page.getByRole( 'button', { name: /Broken route/ } ) ).toBeVisible();

		await page.getByRole( 'button', { name: /Broken route/ } ).click();
		await expect( page.getByRole( 'heading', { name: 'Continue' } ) ).toBeVisible();
		await expect( page.getByText( 'Target step is missing.' ) ).toBeVisible();
	} );

	test( 'renders a published checkout step and prepares the WooCommerce checkout', async ( { page } ) => {
		const setup = await createPublishedFunnelPages( page );

		await page.goto( setup.checkoutUrl );

		await expect( page.locator( '.librefunnels-step--checkout' ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: setup.checkoutTitle, exact: true } ) ).toBeVisible();
		await expect( page.locator( 'form.checkout' ) ).toBeVisible();
		await expect( page.locator( '#order_review' ) ).toBeVisible();
		await expect( page.getByText( setup.checkoutProductName ).first() ).toBeVisible();
	} );

	test( 'creates a checkout order and surfaces attributed revenue analytics', async ( { page } ) => {
		const setup = await createPublishedFunnelPages( page, { checkoutProductPrice: '17' } );

		await page.goto( setup.checkoutUrl );
		await expect( page.locator( '.librefunnels-step--checkout' ) ).toBeVisible();
		await fillCheckoutBillingDetails( page );

		const chequeMethod = page.locator( 'input[name="payment_method"][value="cheque"]' );
		if ( await chequeMethod.count() ) {
			await chequeMethod.check( { force: true } );
		}

		const terms = page.locator( '#terms' );
		if ( await terms.count() ) {
			await terms.check( { force: true } );
		}

		await page.getByRole( 'button', { name: /place order/i } ).click();
		await expect( page.getByText( /order received|thank you/i ).first() ).toBeVisible( { timeout: 30000 } );

		await page.evaluate( ( funnelId ) => {
			window.localStorage.setItem( 'librefunnels.selectedFunnelId', String( funnelId ) );
		}, setup.funnelId );
		await page.goto( '/wp-admin/admin.php?page=librefunnels-analytics' );
		await expect( page.locator( '.lf-analytics' ) ).toContainText( 'Attributed revenue' );

		const summary = await page.evaluate( async ( funnelId ) => {
			return window.wp.apiFetch( {
				path: `${ window.libreFunnelsAdmin.rest.analytics }?funnel_id=${ encodeURIComponent( funnelId ) }&days=30`,
			} );
		}, setup.funnelId );

		expect( summary.events.order_revenue.count ).toBeGreaterThan( 0 );
		expect( Number( summary.revenue ) ).toBeGreaterThanOrEqual( setup.checkoutProductPrice );
		await expect( page.locator( '.lf-analytics' ) ).toContainText( 'Local funnel signals' );
	} );

	test( 'renders an offer step and routes reject actions to the next public page', async ( { page } ) => {
		const setup = await createPublishedFunnelPages( page, { includeOffer: true } );

		await page.goto( setup.offerUrl );

		await expect( page.locator( '.librefunnels-step--offer' ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: setup.offerHeadline } ) ).toBeVisible();
		await expect( page.getByText( 'A focused implementation boost before checkout.' ) ).toBeVisible();
		await expect( page.getByRole( 'button', { name: 'Add offer and continue' } ) ).toBeVisible();
		await page.getByRole( 'button', { name: 'No thanks, continue' } ).click();

		await expect( page ).toHaveURL( setup.thankYouUrl );
		await expect( page.locator( '.librefunnels-step--thank-you' ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: setup.thankYouTitle, exact: true } ) ).toBeVisible();
	} );

	test( 'accepts a public offer, adds it to the cart, and routes forward', async ( { page } ) => {
		const setup = await createPublishedFunnelPages( page, { includeOffer: true } );

		await page.goto( setup.offerUrl );
		await expect( page.locator( '.librefunnels-step--offer' ) ).toBeVisible();
		await page.getByRole( 'button', { name: 'Add offer and continue' } ).click();

		await expect( page ).toHaveURL( setup.thankYouUrl );
		await expect( page.locator( '.librefunnels-step--thank-you' ) ).toBeVisible();

		await page.goto( '/cart/' );
		await expect( page.locator( 'body' ) ).toContainText( setup.offerProductName );
	} );
} );
