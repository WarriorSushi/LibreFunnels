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
	await page.goto( '/wp-admin/admin.php?page=librefunnels' );
	await expect( page.getByRole( 'button', { name: 'Build checkout path' } ) ).toBeVisible();

	return selectedFunnelId;
}

async function createPublishedFunnelPages( page, options = {} ) {
	const includeOffer = Boolean( options.includeOffer );
	const stamp = Date.now();

	return page.evaluate( async ( { includeOffer: shouldIncludeOffer, stamp: setupStamp } ) => {
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
		const checkoutProduct =
			latestWorkspace.products.find( ( product ) => product.name.includes( 'Digital' ) ) ||
			latestWorkspace.products[ 0 ];
		const offerProduct =
			latestWorkspace.products.find( ( product ) => product.name.includes( 'Setup' ) ) ||
			latestWorkspace.products[ 1 ] ||
			checkoutProduct;

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
			offerTitle,
			offerUrl: offerPage?.url || '',
			offerHeadline: shouldIncludeOffer ? `Setup boost ${ setupStamp }` : '',
			offerProductName: offerProduct.name,
			thankYouTitle,
			thankYouUrl: thankYouPage.url,
		};
	}, { includeOffer, stamp } );
}

test.describe( 'LibreFunnels canvas smoke', () => {
	test.beforeEach( async ( { page } ) => {
		await loginToWordPress( page );
		await page.goto( '/wp-admin/admin.php?page=librefunnels' );
		await expect( page.getByText( 'Canvas Builder' ).first() ).toBeVisible();
	} );

	test( 'creates a checkout funnel with a page, products, and an order bump', async ( { page } ) => {
		await expect( page.getByText( 'Build funnels with clarity, not clutter.' ) ).toHaveCount( 0 );

		await createFunnelFromCanvasButton( page );
		await expect( page.locator( '.lf-analytics' ) ).toContainText( 'Waiting for shopper data' );

		await page.getByRole( 'button', { name: 'Build checkout path' } ).click();
		await expect( page.getByText( 'Starter path created' ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: 'Checkout', exact: true } ) ).toBeVisible();
		await expect( page.getByText( 'Thank You' ).first() ).toBeVisible();
		await expect( page.getByText( '3 of 7 ready' ) ).toBeVisible();
		await expect( page.getByText( 'Create pages for Checkout, Thank You.' ) ).toBeVisible();

		const pageTitle = `Playwright checkout ${ Date.now() }`;
		await page.getByLabel( 'Create page' ).fill( pageTitle );
		await page.getByRole( 'button', { name: 'Create and assign' } ).click();
		await expect( page.getByText( 'Page created and assigned' ) ).toBeVisible();
		await expect( page.getByText( 'Draft page' ).first() ).toBeVisible();
		await expect( page.getByText( 'Create a page for Thank You.' ) ).toBeVisible();
		await expect( page.getByText( 'Edit and publish Checkout.' ) ).toBeVisible();
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
		await expect( page.getByText( 'Canvas Builder' ).first() ).toBeVisible();
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
