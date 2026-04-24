import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import { createRoot, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import './style.css';

const settings = window.libreFunnelsAdmin || {};

if ( settings.nonce && apiFetch.createNonceMiddleware ) {
	apiFetch.use( apiFetch.createNonceMiddleware( settings.nonce ) );
}

const stepTypes = settings.stepTypes || {};
const routes = settings.routes || {};
const canvasPath = settings.rest?.canvas || '/librefunnels/v1/canvas';
const pagesPath = settings.rest?.pages || '/librefunnels/v1/canvas/pages';
const productsPath = settings.rest?.products || '/librefunnels/v1/canvas/products';
const templatesPath = settings.rest?.templates || '/librefunnels/v1/canvas/templates';
const templateCreateBasePath = settings.rest?.templateCreateBase || '/librefunnels/v1/canvas/templates';
const importPath = settings.rest?.import || '/librefunnels/v1/canvas/import';
const exportBasePath = settings.rest?.exportBase || '/librefunnels/v1/canvas/funnels';
const analyticsPath = settings.rest?.analytics || '/librefunnels/v1/analytics/summary';
const selectedFunnelStorageKey = 'librefunnels.selectedFunnelId';
const adminSection = settings.activeSection || 'dashboard';
const adminPages = settings.adminPages || {};
const siteReadiness = settings.siteReadiness || {};
const canvasNodeWidth = 220;
const canvasNodeHeight = 96;

const workspaceTabs = [
	{ id: 'overview', label: __( 'Overview', 'librefunnels' ) },
	{ id: 'canvas', label: __( 'Canvas', 'librefunnels' ) },
	{ id: 'steps', label: __( 'Steps', 'librefunnels' ) },
	{ id: 'products', label: __( 'Products', 'librefunnels' ) },
	{ id: 'offers', label: __( 'Offers', 'librefunnels' ) },
	{ id: 'rules', label: __( 'Rules', 'librefunnels' ) },
	{ id: 'analytics', label: __( 'Analytics', 'librefunnels' ) },
	{ id: 'settings', label: __( 'Settings', 'librefunnels' ) },
];

const sectionDefaultTabs = {
	dashboard: 'overview',
	funnels: 'overview',
	analytics: 'analytics',
	settings: 'settings',
	setup: 'overview',
	templates: 'steps',
};

const sectionLabels = {
	dashboard: __( 'Dashboard', 'librefunnels' ),
	funnels: __( 'Funnels', 'librefunnels' ),
	templates: __( 'Templates', 'librefunnels' ),
	analytics: __( 'Analytics', 'librefunnels' ),
	settings: __( 'Settings', 'librefunnels' ),
	setup: __( 'Setup', 'librefunnels' ),
};

const starterStepTypes = [ 'landing', 'optin', 'checkout', 'pre_checkout_offer', 'upsell', 'downsell', 'cross_sell', 'thank_you', 'custom' ];

const stepTypeDescriptions = {
	landing: __( 'Introduce the offer before checkout.', 'librefunnels' ),
	optin: __( 'Capture a lead before the purchase path.', 'librefunnels' ),
	checkout: __( 'Prepare WooCommerce products and payment.', 'librefunnels' ),
	pre_checkout_offer: __( 'Present one focused paid step before checkout.', 'librefunnels' ),
	upsell: __( 'Offer a post-choice upgrade after checkout intent.', 'librefunnels' ),
	downsell: __( 'Recover value when an upsell is declined.', 'librefunnels' ),
	cross_sell: __( 'Add a complementary offer before the final confirmation.', 'librefunnels' ),
	thank_you: __( 'Confirm the purchase and guide the next action.', 'librefunnels' ),
	custom: __( 'Add a flexible page for any other funnel moment.', 'librefunnels' ),
};

const emptyGraph = {
	version: 1,
	nodes: [],
	edges: [],
};

const routeClassNames = {
	next: 'continue',
	accept: 'accept',
	reject: 'reject',
	conditional: 'conditional',
	fallback: 'fallback',
};

const ruleLabels = {
	always: __( 'Always match', 'librefunnels' ),
	cart_contains_product: __( 'Cart contains product', 'librefunnels' ),
	cart_subtotal_gte: __( 'Cart subtotal is at least', 'librefunnels' ),
	cart_subtotal_lte: __( 'Cart subtotal is at most', 'librefunnels' ),
	order_contains_product: __( 'Order contains product', 'librefunnels' ),
	order_total_gte: __( 'Order total is at least', 'librefunnels' ),
	order_total_lte: __( 'Order total is at most', 'librefunnels' ),
	customer_logged_in: __( 'Customer is logged in', 'librefunnels' ),
};

const ruleGroups = [
	{
		label: __( 'General', 'librefunnels' ),
		rules: [ 'always', 'customer_logged_in' ],
	},
	{
		label: __( 'Cart', 'librefunnels' ),
		rules: [ 'cart_contains_product', 'cart_subtotal_gte', 'cart_subtotal_lte' ],
	},
	{
		label: __( 'Order', 'librefunnels' ),
		rules: [ 'order_contains_product', 'order_total_gte', 'order_total_lte' ],
	},
];

function getPostTitle( post, fallback ) {
	if ( post?.title?.raw ) {
		return post.title.raw;
	}

	if ( post?.title?.rendered ) {
		return post.title.rendered.replace( /<[^>]+>/g, '' );
	}

	if ( post?.title ) {
		return post.title;
	}

	return fallback;
}

function getGraph( funnel ) {
	const graph = funnel?.graph || emptyGraph;

	return {
		version: 1,
		nodes: Array.isArray( graph.nodes ) ? graph.nodes : [],
		edges: Array.isArray( graph.edges ) ? graph.edges : [],
	};
}

function normalizeNodePosition( node, index ) {
	const position = node.position && typeof node.position === 'object' ? node.position : {};

	return {
		x: Number.isFinite( Number( position.x ) ) ? Number( position.x ) : 120 + index * 260,
		y: Number.isFinite( Number( position.y ) ) ? Number( position.y ) : 120 + ( index % 2 ) * 140,
	};
}

function getNodeConnectionPoint( node, side = 'out' ) {
	const position = normalizeNodePosition( node, 0 );
	const y = position.y + canvasNodeHeight / 2;

	if ( side === 'in' ) {
		return {
			x: position.x,
			y,
		};
	}

	return {
		x: position.x + canvasNodeWidth,
		y,
	};
}

function getStepById( steps, stepId ) {
	return steps.find( ( step ) => Number( step.id ) === Number( stepId ) );
}

function getStoredSelectedFunnelId() {
	try {
		return Number( window.localStorage?.getItem( selectedFunnelStorageKey ) || 0 );
	} catch ( error ) {
		return 0;
	}
}

function getRequestedFunnelIdFromUrl() {
	try {
		const url = new window.URL( window.location.href );
		return Number( url.searchParams.get( 'funnel_id' ) || 0 );
	} catch ( error ) {
		return 0;
	}
}

function getRequestedWorkspaceTabFromUrl() {
	try {
		const url = new window.URL( window.location.href );
		const requestedTab = url.searchParams.get( 'tab' ) || '';

		return workspaceTabs.some( ( tab ) => tab.id === requestedTab ) ? requestedTab : '';
	} catch ( error ) {
		return '';
	}
}

function storeSelectedFunnelId( funnelId ) {
	try {
		if ( Number( funnelId ) > 0 ) {
			window.localStorage?.setItem( selectedFunnelStorageKey, String( Number( funnelId ) ) );
		} else {
			window.localStorage?.removeItem( selectedFunnelStorageKey );
		}
	} catch ( error ) {
		// Browsers can block storage in strict privacy modes; selection still works in memory.
	}
}

function getNodeWarnings( node, steps, selectedFunnel ) {
	const warnings = [];
	const step = getStepById( steps, node.stepId );

	if ( ! step ) {
		warnings.push( __( 'This node points to a step that no longer exists.', 'librefunnels' ) );
		return warnings;
	}

	if ( Number( step.funnelId ) !== Number( selectedFunnel.id ) ) {
		warnings.push( __( 'This step belongs to another funnel.', 'librefunnels' ) );
	}

	if ( Number( step.pageId ) < 1 ) {
		warnings.push( __( 'Assign a page before sending shoppers here.', 'librefunnels' ) );
	}

	return warnings;
}

function getEdgeWarnings( edge, nodes ) {
	const warnings = [];

	if ( ! nodes.some( ( node ) => node.id === edge.source ) ) {
		warnings.push( __( 'Source step is missing.', 'librefunnels' ) );
	}

	if ( ! nodes.some( ( node ) => node.id === edge.target ) ) {
		warnings.push( __( 'Target step is missing.', 'librefunnels' ) );
	}

	if ( edge.route === 'conditional' && ! edge.rule?.type ) {
		warnings.push( __( 'Conditional routes need a rule.', 'librefunnels' ) );
	}

	return warnings;
}

function formatStepTitles( stepList, fallback ) {
	const titles = stepList
		.slice( 0, 2 )
		.map( ( step ) => getPostTitle( step, __( 'Untitled step', 'librefunnels' ) ) );

	if ( titles.length === 0 ) {
		return fallback;
	}

	if ( stepList.length > titles.length ) {
		return sprintf(
			_n( '%1$s and %2$d more', '%1$s and %2$d more', stepList.length - titles.length, 'librefunnels' ),
			titles.join( ', ' ),
			stepList.length - titles.length
		);
	}

	return titles.join( ', ' );
}

function getCreatePagesDetail( missingPageSteps ) {
	return sprintf(
		_n( 'Create a page for %s.', 'Create pages for %s.', missingPageSteps.length, 'librefunnels' ),
		formatStepTitles( missingPageSteps, __( 'these steps', 'librefunnels' ) )
	);
}

function getCreatePagesMessage( missingPageSteps ) {
	return sprintf(
		_n(
			'Create or assign a page for %s, then edit it with your page builder.',
			'Create or assign pages for %s, then edit them with your page builder.',
			missingPageSteps.length,
			'librefunnels'
		),
		formatStepTitles( missingPageSteps, __( 'these steps', 'librefunnels' ) )
	);
}

function formatPlainNumber( value, options = {} ) {
	const nextValue = Number( value || 0 );

	return new Intl.NumberFormat( undefined, options ).format( nextValue );
}

function formatCurrency( value, currency = 'USD' ) {
	try {
		return new Intl.NumberFormat( undefined, {
			style: 'currency',
			currency: currency || 'USD',
			maximumFractionDigits: 2,
		} ).format( Number( value || 0 ) );
	} catch ( error ) {
		return formatPlainNumber( value, {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2,
		} );
	}
}

function formatPercent( value ) {
	return `${ formatPlainNumber( value, { maximumFractionDigits: 1 } ) }%`;
}

function formatMetricComparison( comparison, formatter = formatPlainNumber ) {
	if ( ! comparison ) {
		return '';
	}

	const current = Number( comparison.current || 0 );
	const previous = Number( comparison.previous || 0 );
	const delta = Number( comparison.delta || 0 );

	if ( current === 0 && previous === 0 ) {
		return __( 'No change vs previous period', 'librefunnels' );
	}

	if ( previous === 0 && current > 0 ) {
		return __( 'New vs previous period', 'librefunnels' );
	}

	if ( delta === 0 ) {
		return __( 'Flat vs previous period', 'librefunnels' );
	}

	return sprintf(
		__( '%s vs previous period', 'librefunnels' ),
		`${ delta > 0 ? '+' : '' }${ formatter( delta ) }`
	);
}

function slugifyFilename( value ) {
	return String( value || 'librefunnels-export' )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /(^-|-$)/g, '' ) || 'librefunnels-export';
}

function downloadJsonFile( filename, data ) {
	const blob = new window.Blob( [ JSON.stringify( data, null, 2 ) ], { type: 'application/json' } );
	const url = window.URL.createObjectURL( blob );
	const link = window.document.createElement( 'a' );

	link.href = url;
	link.download = filename;
	window.document.body.appendChild( link );
	link.click();
	link.remove();
	window.URL.revokeObjectURL( url );
}

function getSetupGuide( selectedFunnel, graph, funnelSteps, warnings ) {
	if ( ! selectedFunnel ) {
		return {
			status: 'start',
			label: __( 'Start here', 'librefunnels' ),
			message: __( 'Create a funnel, then LibreFunnels will guide you through steps, pages, products, and routes.', 'librefunnels' ),
		};
	}

	const nodes = graph.nodes || [];
	const startStepId = Number( selectedFunnel.startStepId || 0 );
	const nodeStepIds = nodes.map( ( node ) => Number( node.stepId || 0 ) );
	const canvasSteps = funnelSteps.filter( ( step ) => nodeStepIds.includes( Number( step.id ) ) );
	const missingPageSteps = canvasSteps.filter( ( step ) => Number( step.pageId || 0 ) < 1 );
	const unpublishedPageSteps = canvasSteps.filter(
		( step ) => Number( step.pageId || 0 ) > 0 && step.pageStatus !== 'publish'
	);
	const checkoutStep = canvasSteps.find( ( step ) => 'checkout' === step.type );
	const offerSteps = canvasSteps.filter( ( step ) => [ 'pre_checkout_offer', 'upsell', 'downsell', 'cross_sell' ].includes( step.type ) );
	const offerStepsMissingProduct = offerSteps.filter( ( step ) => ! Number( step.offer?.product_id || 0 ) );
	const hasValidStartStep = startStepId > 0 && funnelSteps.some( ( step ) => Number( step.id ) === startStepId );
	const hasCheckoutProducts = Boolean( checkoutStep?.checkoutProducts?.length );
	const offersReady = offerStepsMissingProduct.length === 0;
	const hasConnectedRoute = nodes.length < 2 || graph.edges.length > 0;
	let publishDetail = __( 'All assigned pages are published.', 'librefunnels' );

	if ( unpublishedPageSteps.length > 0 ) {
		publishDetail = sprintf(
			__( 'Edit and publish %s.', 'librefunnels' ),
			formatStepTitles( unpublishedPageSteps, __( 'these pages', 'librefunnels' ) )
		);
	} else if ( canvasSteps.length < 1 || missingPageSteps.length > 0 ) {
		publishDetail = __( 'Create pages before publishing.', 'librefunnels' );
	}

	const tasks = [
		{
			id: 'path',
			label: __( 'Path', 'librefunnels' ),
			detail: nodes.length > 0
				? __( 'Checkout path exists.', 'librefunnels' )
				: __( 'Build checkout and thank-you steps.', 'librefunnels' ),
			done: nodes.length > 0,
		},
		{
			id: 'start',
			label: __( 'Start', 'librefunnels' ),
			detail: hasValidStartStep
				? __( 'Shopper entry step is set.', 'librefunnels' )
				: __( 'Choose where shoppers enter.', 'librefunnels' ),
			done: hasValidStartStep,
		},
		{
			id: 'pages',
			label: __( 'Pages', 'librefunnels' ),
			detail: canvasSteps.length < 1
				? __( 'Create steps before assigning pages.', 'librefunnels' )
				: missingPageSteps.length > 0
					? getCreatePagesDetail( missingPageSteps )
					: __( 'Every step has a page.', 'librefunnels' ),
			done: canvasSteps.length > 0 && missingPageSteps.length === 0,
		},
		{
			id: 'publish',
			label: __( 'Publish', 'librefunnels' ),
			detail: publishDetail,
			done: canvasSteps.length > 0 && missingPageSteps.length === 0 && unpublishedPageSteps.length === 0,
		},
		{
			id: 'products',
			label: __( 'Products', 'librefunnels' ),
			detail: ! hasCheckoutProducts
				? __( 'Choose at least one checkout product.', 'librefunnels' )
				: offerStepsMissingProduct.length > 0
					? sprintf(
						_n( 'Add an offer product for %s.', 'Add offer products for %s.', offerStepsMissingProduct.length, 'librefunnels' ),
						formatStepTitles( offerStepsMissingProduct, __( 'these offers', 'librefunnels' ) )
					)
					: __( 'Checkout and offer products are selected.', 'librefunnels' ),
			done: hasCheckoutProducts && offersReady,
		},
		{
			id: 'route',
			label: __( 'Route', 'librefunnels' ),
			detail: hasConnectedRoute
				? __( 'Shopper route is connected.', 'librefunnels' )
				: __( 'Connect the steps shoppers should follow.', 'librefunnels' ),
			done: hasConnectedRoute,
		},
		{
			id: 'review',
			label: __( 'Review', 'librefunnels' ),
			detail: warnings.length > 0
				? sprintf(
					_n( '%d item still needs attention.', '%d items still need attention.', warnings.length, 'librefunnels' ),
					warnings.length
				)
				: __( 'No blocking validation issues.', 'librefunnels' ),
			done: warnings.length === 0,
		},
	];
	const nextTask = tasks.find( ( task ) => ! task.done );
	const completed = tasks.filter( ( task ) => task.done ).length;
	const baseGuide = {
		tasks,
		completed,
		total: tasks.length,
		nextTaskId: nextTask?.id || '',
	};

	if ( nodes.length === 0 ) {
		return {
			...baseGuide,
			status: 'next',
			label: __( 'Next step', 'librefunnels' ),
			message: __( 'Add a checkout step first. You can add offers and thank-you pages after the basic path exists.', 'librefunnels' ),
		};
	}

	if ( ! hasValidStartStep ) {
		return {
			...baseGuide,
			status: 'next',
			label: __( 'Next step', 'librefunnels' ),
			message: __( 'Choose which step shoppers enter first. Most funnels start at the checkout or landing step.', 'librefunnels' ),
		};
	}

	if ( missingPageSteps.length > 0 ) {
		return {
			...baseGuide,
			status: 'next',
			label: __( 'Next step', 'librefunnels' ),
			message: getCreatePagesMessage( missingPageSteps ),
		};
	}

	if ( unpublishedPageSteps.length > 0 ) {
		return {
			...baseGuide,
			status: 'next',
			label: __( 'Next step', 'librefunnels' ),
			message: sprintf(
				__( 'Edit and publish %s so shoppers can reach the funnel.', 'librefunnels' ),
				formatStepTitles( unpublishedPageSteps, __( 'these pages', 'librefunnels' ) )
			),
		};
	}

	if ( ! hasCheckoutProducts ) {
		return {
			...baseGuide,
			status: 'next',
			label: __( 'Next step', 'librefunnels' ),
			message: __( 'Choose the product this checkout should sell so LibreFunnels can prepare the cart.', 'librefunnels' ),
		};
	}

	if ( offerStepsMissingProduct.length > 0 ) {
		return {
			...baseGuide,
			status: 'next',
			label: __( 'Next step', 'librefunnels' ),
			message: sprintf(
				_n(
					'Choose an offer product for %s before testing this funnel path.',
					'Choose offer products for %s before testing this funnel path.',
					offerStepsMissingProduct.length,
					'librefunnels'
				),
				formatStepTitles( offerStepsMissingProduct, __( 'these offer steps', 'librefunnels' ) )
			),
		};
	}

	if ( ! hasConnectedRoute ) {
		return {
			...baseGuide,
			status: 'next',
			label: __( 'Next step', 'librefunnels' ),
			message: __( 'Connect the route shoppers should follow from one step to the next.', 'librefunnels' ),
		};
	}

	if ( warnings.length > 0 ) {
		return {
			...baseGuide,
			status: 'warning',
			label: __( 'Needs attention', 'librefunnels' ),
			message: __( 'Fix the highlighted items in the inspector. Broken steps stay visible so you do not lose context.', 'librefunnels' ),
		};
	}

	return {
		...baseGuide,
		status: 'ready',
		label: __( 'Ready', 'librefunnels' ),
		message: __( 'The basic path is configured. Preview the pages, then add offers, bumps, and conditions.', 'librefunnels' ),
	};
}

function createRuleFromType( type, previous = {} ) {
	if ( type === 'cart_contains_product' || type === 'order_contains_product' ) {
		return {
			type,
			product_id: Number( previous.product_id || 0 ),
		};
	}

	if ( type === 'cart_subtotal_gte' || type === 'cart_subtotal_lte' || type === 'order_total_gte' || type === 'order_total_lte' ) {
		return {
			type,
			amount: Number( previous.amount || 0 ),
		};
	}

	return { type };
}

function getProductById( products, productId ) {
	return products.find( ( product ) => Number( product.id ) === Number( productId ) );
}

function getRulePreview( rule = {}, products = [] ) {
	const type = rule.type || 'always';

	if ( type === 'always' ) {
		return __( 'This route is available to every shopper.', 'librefunnels' );
	}

	if ( type === 'customer_logged_in' ) {
		return __( 'This route is used when the shopper is logged in.', 'librefunnels' );
	}

	if ( type === 'cart_contains_product' || type === 'order_contains_product' ) {
		const product = getProductById( products, Number( rule.product_id || 0 ) );

		if ( ! product ) {
			return __( 'Choose a product to finish this condition.', 'librefunnels' );
		}

		return type === 'cart_contains_product'
			? sprintf( __( 'Use this route when the cart contains %s.', 'librefunnels' ), product.name )
			: sprintf( __( 'Use this route when the order contains %s.', 'librefunnels' ), product.name );
	}

	if ( type === 'cart_subtotal_gte' || type === 'cart_subtotal_lte' || type === 'order_total_gte' || type === 'order_total_lte' ) {
		const amount = Number( rule.amount || 0 );
		const amountLabel = amount > 0 ? formatPlainNumber( amount, { minimumFractionDigits: 2, maximumFractionDigits: 2 } ) : __( 'the amount you enter', 'librefunnels' );

		if ( type === 'cart_subtotal_gte' ) {
			return sprintf( __( 'Use this route when the cart subtotal is at least %s.', 'librefunnels' ), amountLabel );
		}

		if ( type === 'cart_subtotal_lte' ) {
			return sprintf( __( 'Use this route when the cart subtotal is at most %s.', 'librefunnels' ), amountLabel );
		}

		if ( type === 'order_total_gte' ) {
			return sprintf( __( 'Use this route when the order total is at least %s.', 'librefunnels' ), amountLabel );
		}

		return sprintf( __( 'Use this route when the order total is at most %s.', 'librefunnels' ), amountLabel );
	}

	return __( 'LibreFunnels will evaluate this condition before choosing the next route.', 'librefunnels' );
}

function getPageStatusLabel( status ) {
	const labels = {
		publish: __( 'Published page', 'librefunnels' ),
		draft: __( 'Draft page', 'librefunnels' ),
		private: __( 'Private page', 'librefunnels' ),
		pending: __( 'Pending page', 'librefunnels' ),
	};

	return labels[ status ] || __( 'Page assigned', 'librefunnels' );
}

function getNodePageMeta( step, isStart ) {
	if ( step?.pageTitle ) {
		const pageMeta = `${ getPageStatusLabel( step.pageStatus ) }: ${ step.pageTitle }`;
		return isStart ? `${ __( 'Start step', 'librefunnels' ) } · ${ pageMeta }` : pageMeta;
	}

	return isStart ? __( 'Start step', 'librefunnels' ) : __( 'No page assigned', 'librefunnels' );
}

function createEmptyCheckoutProduct() {
	return {
		product_id: 0,
		variation_id: 0,
		quantity: 1,
		variation: {},
	};
}

function createEmptyOffer() {
	return {
		id: `offer-${ Date.now() }`,
		product_id: 0,
		variation_id: 0,
		quantity: 1,
		variation: {},
		title: '',
		description: '',
		discount_type: 'none',
		discount_amount: 0,
		enabled: true,
	};
}

function normalizeVariation( variation = {} ) {
	return Object.keys( variation || {} )
		.sort()
		.reduce( ( normalized, key ) => {
			const value = variation[ key ];

			if ( key && value !== undefined && value !== null && String( value ).trim() !== '' ) {
				normalized[ key ] = String( value ).trim();
			}

			return normalized;
		}, {} );
}

function variationToText( variation = {} ) {
	return Object.entries( normalizeVariation( variation ) )
		.map( ( [ key, value ] ) => `${ key }=${ value }` )
		.join( '\n' );
}

function textToVariation( value ) {
	return String( value || '' )
		.split( '\n' )
		.reduce( ( variation, line ) => {
			const [ rawKey, ...rawValue ] = line.split( '=' );
			const key = String( rawKey || '' ).trim();
			const attributeValue = rawValue.join( '=' ).trim();

			if ( key && attributeValue ) {
				variation[ key ] = attributeValue;
			}

			return variation;
		}, {} );
}

function normalizeCheckoutProductForCompare( product = {} ) {
	return {
		product_id: Number( product.product_id || 0 ),
		variation_id: Number( product.variation_id || 0 ),
		quantity: Number( product.quantity || 1 ),
		variation: normalizeVariation( product.variation || {} ),
	};
}

function checkoutProductsMatch( firstProducts = [], secondProducts = [] ) {
	const first = Array.isArray( firstProducts ) ? firstProducts.map( normalizeCheckoutProductForCompare ) : [];
	const second = Array.isArray( secondProducts ) ? secondProducts.map( normalizeCheckoutProductForCompare ) : [];

	return JSON.stringify( first ) === JSON.stringify( second );
}

function normalizeOfferForCompare( offer = {} ) {
	const productId = Number( offer.product_id || 0 );

	return {
		id: productId > 0 ? offer.id || '' : '',
		product_id: productId,
		variation_id: Number( offer.variation_id || 0 ),
		quantity: Number( offer.quantity || 1 ),
		variation: normalizeVariation( offer.variation || {} ),
		title: offer.title || '',
		description: offer.description || '',
		discount_type: offer.discount_type || 'none',
		discount_amount: Number( offer.discount_amount || 0 ),
		enabled: Boolean( offer.enabled ?? true ),
	};
}

function offersMatch( firstOffer, secondOffer ) {
	return JSON.stringify( normalizeOfferForCompare( firstOffer ) ) === JSON.stringify( normalizeOfferForCompare( secondOffer ) );
}

function offerListsMatch( firstOffers = [], secondOffers = [] ) {
	const first = Array.isArray( firstOffers ) ? firstOffers.map( normalizeOfferForCompare ) : [];
	const second = Array.isArray( secondOffers ) ? secondOffers.map( normalizeOfferForCompare ) : [];

	return JSON.stringify( first ) === JSON.stringify( second );
}

function App() {
	const [ funnels, setFunnels ] = useState( [] );
	const [ steps, setSteps ] = useState( [] );
	const [ pages, setPages ] = useState( [] );
	const [ products, setProducts ] = useState( [] );
	const [ selectedFunnelId, setSelectedFunnelId ] = useState( () => getRequestedFunnelIdFromUrl() || getStoredSelectedFunnelId() );
	const [ selectedItem, setSelectedItem ] = useState( { type: 'funnel' } );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ notice, setNotice ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ activeWorkspaceTab, setActiveWorkspaceTab ] = useState( () => getRequestedWorkspaceTabFromUrl() || sectionDefaultTabs[ adminSection ] || 'overview' );
	const [ analyticsSummary, setAnalyticsSummary ] = useState( null );
	const [ isAnalyticsLoading, setIsAnalyticsLoading ] = useState( false );
	const [ analyticsError, setAnalyticsError ] = useState( '' );
	const [ templateLibrary, setTemplateLibrary ] = useState( [] );
	const [ isTemplatesLoading, setIsTemplatesLoading ] = useState( false );
	const [ dragging, setDragging ] = useState( null );
	const [ connectionDraft, setConnectionDraft ] = useState( null );

	const selectedFunnel = funnels.find( ( funnel ) => Number( funnel.id ) === Number( selectedFunnelId ) );
	const graph = selectedFunnel ? getGraph( selectedFunnel ) : emptyGraph;
	const funnelSteps = useMemo(
		() => steps.filter( ( step ) => Number( step.funnelId ) === Number( selectedFunnelId ) ),
		[ steps, selectedFunnelId ]
	);

	useEffect( () => {
		loadWorkspace();
		loadTemplates();
	}, [] );

	useEffect( () => {
		let ignore = false;
		const nextFunnelId = Number( selectedFunnelId || 0 );

		if ( nextFunnelId < 1 ) {
			setAnalyticsSummary( null );
			setAnalyticsError( '' );
			setIsAnalyticsLoading( false );

			return () => {
				ignore = true;
			};
		}

		setIsAnalyticsLoading( true );
		setAnalyticsError( '' );

		apiFetch( {
			path: `${ analyticsPath }?funnel_id=${ encodeURIComponent( nextFunnelId ) }&days=30`,
		} )
			.then( ( summary ) => {
				if ( ! ignore ) {
					setAnalyticsSummary( summary );
				}
			} )
			.catch( ( nextError ) => {
				if ( ! ignore ) {
					setAnalyticsSummary( null );
					setAnalyticsError( nextError.message || __( 'LibreFunnels could not load analytics yet.', 'librefunnels' ) );
				}
			} )
			.finally( () => {
				if ( ! ignore ) {
					setIsAnalyticsLoading( false );
				}
			} );

		return () => {
			ignore = true;
		};
	}, [ selectedFunnelId ] );

	useEffect( () => {
		if ( ! dragging ) {
			return undefined;
		}

		function handleMove( event ) {
			const nextGraph = {
				...dragging.graph,
				nodes: dragging.graph.nodes.map( ( node ) => {
					if ( node.id !== dragging.nodeId ) {
						return node;
					}

					return {
						...node,
						position: {
							x: Math.max( 24, dragging.origin.x + event.clientX - dragging.pointer.x ),
							y: Math.max( 24, dragging.origin.y + event.clientY - dragging.pointer.y ),
						},
					};
				} ),
			};

			updateLocalGraph( nextGraph );
			setDragging( {
				...dragging,
				nextGraph,
			} );
		}

		function handleUp() {
			const graphToSave = dragging.nextGraph || dragging.graph;
			setDragging( null );
			saveGraph( graphToSave, Number( selectedFunnel?.startStepId || 0 ), { optimistic: true } );
		}

		window.addEventListener( 'pointermove', handleMove );
		window.addEventListener( 'pointerup', handleUp, { once: true } );

		return () => {
			window.removeEventListener( 'pointermove', handleMove );
			window.removeEventListener( 'pointerup', handleUp );
		};
	}, [ dragging, selectedFunnel?.startStepId ] );

	useEffect( () => {
		if ( ! connectionDraft ) {
			return undefined;
		}

		function getPointerPosition( event ) {
			return {
				x: Math.max( 0, event.clientX - connectionDraft.stageRect.left ),
				y: Math.max( 0, event.clientY - connectionDraft.stageRect.top ),
			};
		}

		function handleMove( event ) {
			setConnectionDraft( ( current ) =>
				current
					? {
							...current,
							pointer: getPointerPosition( event ),
					  }
					: current
			);
		}

		function handleUp( event ) {
			const targetElement = window.document
				.elementsFromPoint( event.clientX, event.clientY )
				.map( ( element ) => element.closest?.( '[data-lf-connect-target]' ) )
				.find( Boolean );
			const targetNodeId = targetElement?.getAttribute( 'data-lf-connect-target' ) || '';

			setConnectionDraft( null );

			if ( targetNodeId && targetNodeId !== connectionDraft.sourceNodeId ) {
				void createEdgeBetween( connectionDraft.sourceNodeId, targetNodeId );
			}
		}

		window.addEventListener( 'pointermove', handleMove );
		window.addEventListener( 'pointerup', handleUp, { once: true } );
		window.addEventListener( 'pointercancel', handleUp, { once: true } );

		return () => {
			window.removeEventListener( 'pointermove', handleMove );
			window.removeEventListener( 'pointerup', handleUp );
			window.removeEventListener( 'pointercancel', handleUp );
		};
	}, [ connectionDraft, graph ] );

	function selectFunnel( funnelId ) {
		const nextFunnelId = Number( funnelId || 0 );
		setSelectedFunnelId( nextFunnelId );
		storeSelectedFunnelId( nextFunnelId );
	}

	function applyWorkspace( payload, preferredFunnelId = selectedFunnelId ) {
		const workspace = payload?.workspace || payload || {};
		const nextFunnels = Array.isArray( workspace.funnels ) ? workspace.funnels : [];
		const nextSteps = Array.isArray( workspace.steps ) ? workspace.steps : [];
		const nextPages = Array.isArray( workspace.pages ) ? workspace.pages : pages;
		const nextProducts = Array.isArray( workspace.products ) ? workspace.products : products;
		const candidateId = Number( workspace.selectedFunnelId || preferredFunnelId || getStoredSelectedFunnelId() );

		setFunnels( nextFunnels );
		setSteps( nextSteps );
		setPages( nextPages );
		setProducts( nextProducts );

		if ( nextFunnels.length > 0 ) {
			const stillExists = nextFunnels.some( ( funnel ) => Number( funnel.id ) === Number( candidateId ) );
			selectFunnel( stillExists ? candidateId : nextFunnels[ 0 ].id );
		} else {
			selectFunnel( 0 );
		}
	}

	async function loadWorkspace( preferredFunnelId = selectedFunnelId || getRequestedFunnelIdFromUrl() || getStoredSelectedFunnelId() ) {
		setIsLoading( true );
		setError( '' );

	try {
		const nextFunnelId = Number( preferredFunnelId || 0 );
		const path = nextFunnelId > 0 ? `${ canvasPath }?funnel_id=${ encodeURIComponent( nextFunnelId ) }` : canvasPath;
		const workspace = await apiFetch( { path } );
		applyWorkspace( workspace, preferredFunnelId );
		setSelectedItem( { type: 'funnel' } );
	} catch ( nextError ) {
			setError( nextError.message || __( 'LibreFunnels could not load the workspace.', 'librefunnels' ) );
		} finally {
			setIsLoading( false );
		}
	}

	async function loadTemplates() {
		setIsTemplatesLoading( true );

		try {
			const response = await apiFetch( { path: templatesPath } );
			const nextTemplates = Array.isArray( response?.templates ) ? response.templates : [];
			setTemplateLibrary( nextTemplates );
		} catch ( nextError ) {
			setTemplateLibrary( [] );
			setError( nextError.message || __( 'LibreFunnels could not load the bundled templates.', 'librefunnels' ) );
		} finally {
			setIsTemplatesLoading( false );
		}
	}

	async function runSave( action, successMessage ) {
		setIsSaving( true );
		setError( '' );
		setNotice( __( 'Saving...', 'librefunnels' ) );

	try {
		const payload = await action();
		const workspace = payload?.workspace || payload || {};
		applyWorkspace( payload, workspace.selectedFunnelId || selectedFunnelId );
		setNotice( successMessage || __( 'Saved', 'librefunnels' ) );
		return payload;
	} catch ( nextError ) {
			setNotice( '' );
			setError( nextError.message || __( 'LibreFunnels could not save this change.', 'librefunnels' ) );
			return null;
		} finally {
			setIsSaving( false );
		}
	}

	async function createFunnel() {
		const payload = await runSave(
			() =>
				apiFetch( {
					path: `${ canvasPath }/funnels`,
					method: 'POST',
					data: {
						title: __( 'New checkout funnel', 'librefunnels' ),
					},
				} ),
			__( 'Funnel created', 'librefunnels' )
		);

		const workspace = payload?.workspace || payload || {};
		const nextFunnelId = Number( workspace.selectedFunnelId || 0 );

		if ( nextFunnelId > 0 ) {
			applyWorkspace( payload, nextFunnelId );
			selectFunnel( nextFunnelId );
			setSelectedItem( { type: 'funnel' } );
		}
	}

	function openBuilderForFunnel( funnelId, options = {} ) {
		const nextFunnelId = Number( funnelId || 0 );

		if ( nextFunnelId < 1 ) {
			return;
		}

		const builderUrl = new window.URL(
			adminPages.funnels || 'admin.php?page=librefunnels-funnels',
			window.location.href
		);

		builderUrl.searchParams.set( 'funnel_id', String( nextFunnelId ) );
		if ( options.tab ) {
			builderUrl.searchParams.set( 'tab', options.tab );
		}
		storeSelectedFunnelId( nextFunnelId );
		window.location.assign( builderUrl.toString() );
	}

	async function createFromTemplate( templateSlug, options = {} ) {
		const payload = await runSave(
			() =>
				apiFetch( {
					path: `${ templateCreateBasePath }/${ encodeURIComponent( templateSlug ) }/create`,
					method: 'POST',
					data: {
						title: options.title || '',
						checkout_product_id: Number( options.checkoutProductId || 0 ),
						offer_product_id: Number( options.offerProductId || 0 ),
					},
				} ),
			__( 'Template funnel created', 'librefunnels' )
		);

		const workspace = payload?.workspace || payload || {};
		const nextFunnelId = Number( workspace.selectedFunnelId || 0 );

		if ( nextFunnelId > 0 ) {
			setSelectedItem( { type: 'funnel' } );
		}

		if ( options.redirectToBuilder && nextFunnelId > 0 ) {
			openBuilderForFunnel( nextFunnelId, { tab: options.redirectTab || '' } );
		}
	}

	function createGuidedStarterFunnel( options = {} ) {
		const starterOptions = typeof options?.preventDefault === 'function' ? {} : options;

		return createFromTemplate( 'starter_checkout', {
			...starterOptions,
			redirectToBuilder: true,
			redirectTab: starterOptions.redirectTab || 'steps',
		} );
	}

	async function importFunnelPackage( packageText, options = {} ) {
		const payload = await runSave(
			() =>
				apiFetch( {
					path: importPath,
					method: 'POST',
					data: {
						package: packageText,
						title: options.title || '',
					},
				} ),
			__( 'Funnel imported', 'librefunnels' )
		);

		const workspace = payload?.workspace || payload || {};
		const nextFunnelId = Number( workspace.selectedFunnelId || 0 );

		if ( options.redirectToBuilder && nextFunnelId > 0 ) {
			openBuilderForFunnel( nextFunnelId );
		}
	}

	async function exportFunnelPackage( funnel ) {
		if ( ! funnel?.id ) {
			setError( __( 'Choose a funnel before exporting.', 'librefunnels' ) );
			return;
		}

		setError( '' );
		setNotice( __( 'Preparing export...', 'librefunnels' ) );

		try {
			const response = await apiFetch( {
				path: `${ exportBasePath }/${ encodeURIComponent( funnel.id ) }/export`,
			} );
			const filename = response?.filename || `${ slugifyFilename( getPostTitle( funnel, 'librefunnels-export' ) ) }.json`;

			downloadJsonFile( filename, response?.package || {} );
			setNotice( __( 'Export downloaded', 'librefunnels' ) );
		} catch ( nextError ) {
			setNotice( '' );
			setError( nextError.message || __( 'LibreFunnels could not export this funnel.', 'librefunnels' ) );
		}
	}

	function updateLocalGraph( nextGraph ) {
		if ( ! selectedFunnel ) {
			return;
		}

		setFunnels( ( current ) =>
			current.map( ( funnel ) =>
				Number( funnel.id ) === Number( selectedFunnel.id )
					? {
							...funnel,
							graph: nextGraph,
					  }
					: funnel
			)
		);
	}

	async function saveGraph( nextGraph, nextStartStepId = Number( selectedFunnel?.startStepId || 0 ), options = {} ) {
		if ( ! selectedFunnel ) {
			return null;
		}

		updateLocalGraph( nextGraph );
		return runSave(
			() =>
				apiFetch( {
					path: `${ canvasPath }/funnels/${ selectedFunnel.id }/graph`,
					method: 'POST',
					data: {
						graph: nextGraph,
						start_step_id: nextStartStepId,
					},
				} ),
			options.optimistic ? __( 'Canvas saved', 'librefunnels' ) : __( 'Route saved', 'librefunnels' )
		);
	}

	async function createStep( type = 'checkout' ) {
		if ( ! selectedFunnel ) {
			return;
		}

		const position = {
			x: 120 + graph.nodes.length * 260,
			y: 140 + ( graph.nodes.length % 2 ) * 150,
		};

		const payload = await runSave(
			() =>
				apiFetch( {
					path: `${ canvasPath }/funnels/${ selectedFunnel.id }/steps`,
					method: 'POST',
					data: {
						title: stepTypes[ type ] || __( 'New step', 'librefunnels' ),
						type,
						order: funnelSteps.length + 1,
						page_id: 0,
						position,
					},
				} ),
			__( 'Step added', 'librefunnels' )
		);

		const workspace = payload?.workspace || payload;
		const latestFunnel = workspace?.funnels?.find( ( funnel ) => Number( funnel.id ) === Number( selectedFunnel.id ) );
		const latestNodes = latestFunnel ? getGraph( latestFunnel ).nodes : [];
		const latestNode = latestNodes.length > 0 ? latestNodes[ latestNodes.length - 1 ] : null;

		if ( latestNode ) {
			setSelectedItem( { type: 'node', id: latestNode.id } );
			setActiveWorkspaceTab( 'canvas' );
		}
	}

	async function createStarterPath() {
		if ( ! selectedFunnel ) {
			return;
		}

		setIsSaving( true );
		setError( '' );
		setNotice( __( 'Building starter path...', 'librefunnels' ) );

		try {
			const checkoutPayload = await apiFetch( {
				path: `${ canvasPath }/funnels/${ selectedFunnel.id }/steps`,
				method: 'POST',
				data: {
					title: stepTypes.checkout || __( 'Checkout', 'librefunnels' ),
					type: 'checkout',
					order: funnelSteps.length + 1,
					page_id: 0,
					position: {
						x: 120,
						y: 160,
					},
				},
			} );
			const checkoutWorkspace = checkoutPayload?.workspace || checkoutPayload;
			const checkoutFunnel = checkoutWorkspace?.funnels?.find( ( funnel ) => Number( funnel.id ) === Number( selectedFunnel.id ) );
			const checkoutNodes = checkoutFunnel ? getGraph( checkoutFunnel ).nodes : [];
			const checkoutNode = checkoutNodes.length > 0 ? checkoutNodes[ checkoutNodes.length - 1 ] : null;

			if ( ! checkoutNode ) {
				throw new Error( __( 'LibreFunnels could not create the checkout step.', 'librefunnels' ) );
			}

			const thankYouPayload = await apiFetch( {
				path: `${ canvasPath }/funnels/${ selectedFunnel.id }/steps`,
				method: 'POST',
				data: {
					title: stepTypes.thank_you || __( 'Thank You', 'librefunnels' ),
					type: 'thank_you',
					order: funnelSteps.length + 2,
					page_id: 0,
					position: {
						x: 440,
						y: 160,
					},
				},
			} );
			const thankYouWorkspace = thankYouPayload?.workspace || thankYouPayload;
			const thankYouFunnel = thankYouWorkspace?.funnels?.find( ( funnel ) => Number( funnel.id ) === Number( selectedFunnel.id ) );
			const nextGraph = thankYouFunnel ? getGraph( thankYouFunnel ) : emptyGraph;
			const latestCheckoutNode = nextGraph.nodes.find( ( node ) => Number( node.stepId ) === Number( checkoutNode.stepId ) );
			const thankYouNode = nextGraph.nodes.find( ( node ) => 'thank_you' === node.type && Number( node.stepId ) !== Number( checkoutNode.stepId ) );

			if ( ! latestCheckoutNode || ! thankYouNode ) {
				throw new Error( __( 'LibreFunnels could not connect the starter path.', 'librefunnels' ) );
			}

			const savedWorkspace = await apiFetch( {
				path: `${ canvasPath }/funnels/${ selectedFunnel.id }/graph`,
				method: 'POST',
				data: {
					graph: {
						...nextGraph,
						edges: [
							...nextGraph.edges,
							{
								id: `edge-${ Date.now() }`,
								source: latestCheckoutNode.id,
								target: thankYouNode.id,
								route: 'next',
								rule: {},
							},
						],
					},
					start_step_id: Number( latestCheckoutNode.stepId || 0 ),
				},
			} );

			applyWorkspace( savedWorkspace, selectedFunnel.id );
			setSelectedItem( { type: 'node', id: latestCheckoutNode.id } );
			setActiveWorkspaceTab( 'canvas' );
			setNotice( __( 'Starter path created', 'librefunnels' ) );
		} catch ( nextError ) {
			setNotice( '' );
			setError( nextError.message || __( 'LibreFunnels could not build the starter path.', 'librefunnels' ) );
		} finally {
			setIsSaving( false );
		}
	}

	async function updateStep( step, fields ) {
		return runSave(
			() =>
				apiFetch( {
					path: `${ canvasPath }/steps/${ step.id }`,
					method: 'POST',
					data: fields,
				} ),
			__( 'Step saved', 'librefunnels' )
		);
	}

	async function deleteStep( step ) {
		if ( ! step ) {
			return;
		}

		await runSave(
			() =>
				apiFetch( {
					path: `${ canvasPath }/steps/${ step.id }`,
					method: 'DELETE',
				} ),
			__( 'Step archived', 'librefunnels' )
		);
		setSelectedItem( { type: 'funnel' } );
	}

	async function createEdge() {
		if ( graph.nodes.length < 2 ) {
			setError( __( 'Add at least two steps before connecting a route.', 'librefunnels' ) );
			return;
		}

		const source = graph.nodes[ graph.nodes.length - 2 ];
		const target = graph.nodes[ graph.nodes.length - 1 ];
		await createEdgeBetween( source.id, target.id );
	}

	async function createEdgeBetween( sourceNodeId, targetNodeId ) {
		const source = graph.nodes.find( ( node ) => node.id === sourceNodeId );
		const target = graph.nodes.find( ( node ) => node.id === targetNodeId );

		if ( ! source || ! target ) {
			setError( __( 'LibreFunnels could not connect those steps because one of them is missing.', 'librefunnels' ) );
			return;
		}

		const existingEdge = graph.edges.find(
			( item ) => item.source === source.id && item.target === target.id && item.route === 'next'
		);

		if ( existingEdge ) {
			setSelectedItem( { type: 'edge', id: existingEdge.id } );
			setNotice( __( 'Route already exists', 'librefunnels' ) );
			return;
		}

		const edge = {
			id: `edge-${ Date.now() }`,
			source: source.id,
			target: target.id,
			route: 'next',
			rule: {},
		};

		await saveGraph( {
			...graph,
			edges: [ ...graph.edges, edge ],
		} );
		setSelectedItem( { type: 'edge', id: edge.id } );
	}

	async function searchPages( searchTerm ) {
		const suffix = searchTerm ? `?search=${ encodeURIComponent( searchTerm ) }` : '';
		const nextPages = await apiFetch( { path: `${ pagesPath }${ suffix }` } );

		if ( Array.isArray( nextPages ) ) {
			setPages( nextPages );
		}
	}

	async function searchProducts( searchTerm ) {
		const suffix = searchTerm ? `?search=${ encodeURIComponent( searchTerm ) }` : '';
		const nextProducts = await apiFetch( { path: `${ productsPath }${ suffix }` } );

		if ( Array.isArray( nextProducts ) ) {
			setProducts( nextProducts );
		}
	}

	async function createPageForStep( step, title ) {
		await runSave(
			() =>
				apiFetch( {
					path: pagesPath,
					method: 'POST',
					data: {
						step_id: step.id,
						title,
					},
				} ),
			__( 'Page created and assigned', 'librefunnels' )
		);
	}

	function setStartStep( stepId ) {
		if ( ! selectedFunnel ) {
			return;
		}

		saveGraph( graph, Number( stepId ) );
	}

	function getValidationSummary() {
		if ( ! selectedFunnel ) {
			return [];
		}

		const warnings = Array.isArray( selectedFunnel.warnings ) ? [ ...selectedFunnel.warnings ] : [];
		const startStepId = Number( selectedFunnel.startStepId || 0 );

		if ( startStepId < 1 ) {
			warnings.push( __( 'Choose a start step so shoppers know where to enter.', 'librefunnels' ) );
		}

		if ( startStepId > 0 && ! funnelSteps.some( ( step ) => Number( step.id ) === startStepId ) ) {
			warnings.push( __( 'The start step no longer belongs to this funnel.', 'librefunnels' ) );
		}

		graph.nodes.forEach( ( node ) => {
			getNodeWarnings( node, steps, selectedFunnel ).forEach( ( warning ) => warnings.push( warning ) );
		} );

		graph.edges.forEach( ( edge ) => {
			getEdgeWarnings( edge, graph.nodes ).forEach( ( warning ) => warnings.push( warning ) );
		} );

		return [ ...new Set( warnings ) ];
	}

	function startNodeDrag( event, node ) {
		if ( event.button !== 0 ) {
			return;
		}

		if ( event.target.closest?.( '.lf-node-handle' ) ) {
			return;
		}

		event.preventDefault();
		setSelectedItem( { type: 'node', id: node.id } );
		setDragging( {
			nodeId: node.id,
			graph,
			pointer: {
				x: event.clientX,
				y: event.clientY,
			},
			origin: normalizeNodePosition( node, graph.nodes.findIndex( ( item ) => item.id === node.id ) ),
		} );
	}

	function startConnectionDrag( event, node, stageElement ) {
		if ( event.button !== 0 || ! stageElement ) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		const sourcePoint = getNodeConnectionPoint( node, 'out' );
		const stageRect = stageElement.getBoundingClientRect();

		setSelectedItem( { type: 'node', id: node.id } );
		setConnectionDraft( {
			sourceNodeId: node.id,
			stageRect,
			source: sourcePoint,
			pointer: {
				x: Math.max( 0, event.clientX - stageRect.left ),
				y: Math.max( 0, event.clientY - stageRect.top ),
			},
		} );
	}

	async function createStarterPathAndOpenCanvas() {
		await createStarterPath();
		setActiveWorkspaceTab( 'canvas' );
	}

	const validationSummary = getValidationSummary();
	const setupGuide = getSetupGuide( selectedFunnel, graph, funnelSteps, validationSummary );

	if ( adminSection !== 'funnels' ) {
		return (
			<div className={ `wrap librefunnels-canvas-app lf-section-app lf-section-app--${ adminSection }` }>
				<SectionPage
					section={ adminSection }
					funnels={ funnels }
					steps={ steps }
					templateLibrary={ templateLibrary }
					selectedFunnel={ selectedFunnel }
					graph={ graph }
					funnelSteps={ funnelSteps }
					setupGuide={ setupGuide }
					warnings={ validationSummary }
					analyticsSummary={ analyticsSummary }
					isAnalyticsLoading={ isAnalyticsLoading }
					analyticsError={ analyticsError }
					products={ products }
					notice={ notice }
					error={ error }
					onCreateFunnel={ createFunnel }
					onCreateStarterFunnel={ createGuidedStarterFunnel }
					onCreateTemplate={ createFromTemplate }
					onImportFunnelPackage={ importFunnelPackage }
					onExportFunnelPackage={ exportFunnelPackage }
					onSelectFunnel={ selectFunnel }
					onSearchProducts={ searchProducts }
					isLoading={ isLoading }
					isTemplatesLoading={ isTemplatesLoading }
					isSaving={ isSaving }
				/>
			</div>
		);
	}

	return (
		<div className={ `wrap librefunnels-canvas-app lf-console lf-console--${ adminSection }` }>
			<Sidebar
				funnels={ funnels }
				selectedFunnelId={ selectedFunnelId }
				onSelect={ ( id ) => {
					selectFunnel( id );
					setSelectedItem( { type: 'funnel' } );
				} }
				onCreate={ createFunnel }
				isLoading={ isLoading }
				isSaving={ isSaving }
			/>

			<main className="lf-workspace-shell" aria-busy={ isLoading || isSaving }>
				<Header
					selectedFunnel={ selectedFunnel }
					warnings={ validationSummary }
					setupGuide={ setupGuide }
					isSaving={ isSaving }
					notice={ notice }
					onCreateEdge={ createEdge }
				/>

				{ error && <div className="lf-alert">{ error }</div> }

				{ selectedFunnel && (
					<WorkspaceTabs activeTab={ activeWorkspaceTab } onChange={ setActiveWorkspaceTab } />
				) }

				<WorkspaceContent
					activeTab={ activeWorkspaceTab }
					isLoading={ isLoading }
					graph={ graph }
					steps={ steps }
					pages={ pages }
					products={ products }
					selectedFunnel={ selectedFunnel }
					selectedItem={ selectedItem }
					connectionDraft={ connectionDraft }
					setupGuide={ setupGuide }
					warnings={ validationSummary }
					analyticsSummary={ analyticsSummary }
					isAnalyticsLoading={ isAnalyticsLoading }
					analyticsError={ analyticsError }
					funnelSteps={ funnelSteps }
					onSelect={ setSelectedItem }
					onStartDrag={ startNodeDrag }
					onStartConnection={ startConnectionDrag }
					onCreateFunnel={ createFunnel }
					onCreateStarterFunnel={ createGuidedStarterFunnel }
					onCreateStep={ createStep }
					onCreateStarterPath={ createStarterPathAndOpenCanvas }
					onCreateEdge={ createEdge }
					onUpdateStep={ updateStep }
					onDeleteStep={ deleteStep }
					onSetStartStep={ setStartStep }
					onSearchPages={ searchPages }
					onSearchProducts={ searchProducts }
					onCreatePageForStep={ createPageForStep }
					onSaveGraph={ saveGraph }
					onOpenTab={ setActiveWorkspaceTab }
					isSaving={ isSaving }
				/>
			</main>
		</div>
	);
}

function SectionPage( {
	section,
	funnels,
	steps,
	templateLibrary,
	selectedFunnel,
	graph,
	funnelSteps,
	setupGuide,
	warnings,
	analyticsSummary,
	isAnalyticsLoading,
	analyticsError,
	products,
	notice,
	error,
	onCreateFunnel,
	onCreateStarterFunnel,
	onCreateTemplate,
	onImportFunnelPackage,
	onExportFunnelPackage,
	onSelectFunnel,
	onSearchProducts,
	isLoading,
	isTemplatesLoading,
	isSaving,
} ) {
	const title = sectionLabels[ section ] || sectionLabels.dashboard;

	return (
		<main className="lf-section-shell" aria-busy={ isLoading || isSaving }>
			<section className="lf-section-hero">
				<div>
					<p className="lf-label">{ __( 'LibreFunnels', 'librefunnels' ) }</p>
					<h1>{ title }</h1>
				</div>
				<div className="lf-section-actions">
					{ notice && <span className="lf-save-state">{ notice }</span> }
					<a className="lf-button" href={ adminPages.funnels || 'admin.php?page=librefunnels-funnels' }>
						{ __( 'Open builder', 'librefunnels' ) }
					</a>
					<button className="lf-button lf-button--primary" type="button" onClick={ onCreateFunnel } disabled={ isLoading || isSaving }>
						{ __( 'Create funnel', 'librefunnels' ) }
					</button>
				</div>
			</section>

			{ error && <div className="lf-alert">{ error }</div> }

			{ section === 'templates' && (
				<TemplatesSection
					templates={ templateLibrary }
					selectedFunnel={ selectedFunnel }
					onCreateFunnel={ onCreateFunnel }
					onCreateStarterFunnel={ onCreateStarterFunnel }
					onCreateTemplate={ onCreateTemplate }
					onImportFunnelPackage={ onImportFunnelPackage }
					onExportFunnelPackage={ onExportFunnelPackage }
					products={ products }
					onSearchProducts={ onSearchProducts }
					isLoading={ isTemplatesLoading }
					isSaving={ isSaving }
				/>
			) }
			{ section === 'analytics' && (
					<AnalyticsSection
						funnels={ funnels }
						selectedFunnel={ selectedFunnel }
						funnelSteps={ funnelSteps }
						summary={ analyticsSummary }
						isLoading={ isAnalyticsLoading }
						error={ analyticsError }
					onSelectFunnel={ onSelectFunnel }
				/>
			) }
			{ section === 'settings' && <SettingsSection selectedFunnel={ selectedFunnel } /> }
			{ section === 'setup' && (
				<SetupSection
					selectedFunnel={ selectedFunnel }
					setupGuide={ setupGuide }
					products={ products }
					onSearchProducts={ onSearchProducts }
					onCreateStarterFunnel={ onCreateStarterFunnel }
					isSaving={ isSaving }
				/>
			) }
			{ section === 'dashboard' && (
				<DashboardSection
					funnels={ funnels }
					steps={ steps }
					selectedFunnel={ selectedFunnel }
					graph={ graph }
					funnelSteps={ funnelSteps }
					setupGuide={ setupGuide }
					warnings={ warnings }
					analyticsSummary={ analyticsSummary }
					isAnalyticsLoading={ isAnalyticsLoading }
					onSelectFunnel={ onSelectFunnel }
					onCreateFunnel={ onCreateFunnel }
					onCreateStarterFunnel={ onCreateStarterFunnel }
					isSaving={ isSaving }
				/>
			) }
		</main>
	);
}

function DashboardSection( { funnels, steps, selectedFunnel, graph, funnelSteps, setupGuide, warnings, analyticsSummary, isAnalyticsLoading, onSelectFunnel, onCreateFunnel, onCreateStarterFunnel, isSaving } ) {
	const revenue = Number( analyticsSummary?.revenue || 0 );
	const funnelIssues = funnels.reduce( ( count, funnel ) => count + Number( funnel.warnings?.length || 0 ), 0 );
	const currency = analyticsSummary?.currency || 'USD';
	const revenueComparison = formatMetricComparison( analyticsSummary?.comparison?.revenue, ( value ) => formatCurrency( value, currency ) );

	return (
		<div className="lf-section-stack">
			<div className="lf-dashboard-hero">
				<div>
					<p className="lf-label">{ __( 'Store owner command center', 'librefunnels' ) }</p>
					<h2>{ __( 'See what needs attention before opening the builder.', 'librefunnels' ) }</h2>
					<p>{ __( 'Dashboard is for orientation: funnel health, setup progress, and quick jumps into the areas that should not crowd the canvas.', 'librefunnels' ) }</p>
				</div>
				<div className="lf-overview__actions">
					<button className="lf-button lf-button--primary" type="button" onClick={ funnels.length === 0 ? onCreateStarterFunnel : onCreateFunnel } disabled={ isSaving }>
						{ funnels.length === 0 ? __( 'Create first funnel', 'librefunnels' ) : __( 'Create funnel', 'librefunnels' ) }
					</button>
					<a className="lf-button" href={ adminPages.analytics || 'admin.php?page=librefunnels-analytics' }>
						{ __( 'View analytics', 'librefunnels' ) }
					</a>
				</div>
			</div>

			<div className="lf-overview-grid">
				<OverviewStat label={ __( 'Funnels', 'librefunnels' ) } value={ formatPlainNumber( funnels.length ) } detail={ __( 'Draft and active funnel workspaces.', 'librefunnels' ) } />
				<OverviewStat label={ __( 'Steps', 'librefunnels' ) } value={ formatPlainNumber( steps.length ) } detail={ __( 'Pages, offers, checkouts, and thank-you steps.', 'librefunnels' ) } />
				<OverviewStat label={ __( 'Open issues', 'librefunnels' ) } value={ formatPlainNumber( funnelIssues ) } detail={ __( 'Validation warnings across loaded funnels.', 'librefunnels' ) } />
				<OverviewStat
					label={ __( 'Selected revenue', 'librefunnels' ) }
					value={ isAnalyticsLoading ? __( 'Loading', 'librefunnels' ) : formatCurrency( revenue, currency ) }
					detail={ revenueComparison || __( 'Attributed revenue for the selected funnel.', 'librefunnels' ) }
				/>
			</div>

			<div className="lf-section-grid">
				<section className="lf-section-card lf-section-card--wide">
					<p className="lf-label">{ __( 'Next best action', 'librefunnels' ) }</p>
					<h3>{ setupGuide?.label || __( 'Start here', 'librefunnels' ) }</h3>
					<p>{ setupGuide?.message || __( 'Create a funnel, then LibreFunnels will guide the next setup step.', 'librefunnels' ) }</p>
					{ selectedFunnel && <SetupProgress setupGuide={ setupGuide } />}
					{ ! selectedFunnel && (
						<button className="lf-button lf-button--primary" type="button" onClick={ onCreateStarterFunnel } disabled={ isSaving }>
							{ __( 'Create starter funnel', 'librefunnels' ) }
						</button>
					) }
				</section>

				<section className="lf-section-card">
					<p className="lf-label">{ __( 'Selected funnel', 'librefunnels' ) }</p>
					<h3>{ selectedFunnel ? getPostTitle( selectedFunnel, __( 'Untitled funnel', 'librefunnels' ) ) : __( 'No funnel selected', 'librefunnels' ) }</h3>
					<p>
						{ selectedFunnel
							? sprintf( __( '%1$d step(s), %2$d route(s), %3$d issue(s).', 'librefunnels' ), funnelSteps.length, graph.edges.length, warnings.length )
							: __( 'Create or choose a funnel to see launch readiness here.', 'librefunnels' ) }
					</p>
					<a className="lf-button" href={ adminPages.funnels || 'admin.php?page=librefunnels-funnels' }>
						{ __( 'Open funnel workspace', 'librefunnels' ) }
					</a>
				</section>
			</div>

			<RecentFunnels funnels={ funnels } selectedFunnel={ selectedFunnel } onSelectFunnel={ onSelectFunnel } />

			<div className="lf-next-cards">
				<SectionLinkCard title={ __( 'Templates', 'librefunnels' ) } text={ __( 'Start from proven funnel shapes without making the canvas a template browser.', 'librefunnels' ) } href={ adminPages.templates } />
				<SectionLinkCard title={ __( 'Setup', 'librefunnels' ) } text={ __( 'Check WooCommerce readiness, pages, products, and publishing tasks.', 'librefunnels' ) } href={ adminPages.setup } />
				<SectionLinkCard title={ __( 'Settings', 'librefunnels' ) } text={ __( 'Keep global behavior and compatibility options away from step editing.', 'librefunnels' ) } href={ adminPages.settings } />
			</div>
		</div>
	);
}

function RecentFunnels( { funnels, selectedFunnel, onSelectFunnel } ) {
	return (
		<section className="lf-section-card">
			<div className="lf-section-heading">
				<div>
					<p className="lf-label">{ __( 'Recent funnels', 'librefunnels' ) }</p>
					<h2>{ __( 'Choose where to work next', 'librefunnels' ) }</h2>
				</div>
				<a className="lf-button" href={ adminPages.funnels || 'admin.php?page=librefunnels-funnels' }>
					{ __( 'Open all funnels', 'librefunnels' ) }
				</a>
			</div>
			{ funnels.length === 0 ? (
				<div className="lf-empty-small">{ __( 'No funnels yet. Create one and it will appear here.', 'librefunnels' ) }</div>
			) : (
				<div className="lf-compact-list">
					{ funnels.slice( 0, 6 ).map( ( funnel ) => (
						<button
							key={ funnel.id }
							className={ `lf-compact-row ${ Number( selectedFunnel?.id || 0 ) === Number( funnel.id ) ? 'is-selected' : '' }` }
							type="button"
							onClick={ () => onSelectFunnel( funnel.id ) }
						>
							<span>
								<strong>{ getPostTitle( funnel, __( 'Untitled funnel', 'librefunnels' ) ) }</strong>
								<small>{ funnel.status }</small>
							</span>
							<em>{ funnel.warnings?.length ? sprintf( __( '%d issue(s)', 'librefunnels' ), funnel.warnings.length ) : __( 'No issues loaded', 'librefunnels' ) }</em>
						</button>
					) ) }
				</div>
			) }
		</section>
	);
}

function TemplatesSection( { templates, selectedFunnel, onCreateFunnel, onCreateStarterFunnel, onCreateTemplate, onImportFunnelPackage, onExportFunnelPackage, products, onSearchProducts, isLoading, isSaving } ) {
	const [ importValue, setImportValue ] = useState( '' );
	const [ importTitle, setImportTitle ] = useState( '' );

	async function handleImport() {
		if ( ! importValue.trim() ) {
			return;
		}

		await onImportFunnelPackage( importValue, { title: importTitle } );
		setImportValue( '' );
		setImportTitle( '' );
	}

	async function handleImportFile( event ) {
		const file = event.target.files?.[0];

		if ( ! file ) {
			return;
		}

		const text = await file.text();
		setImportValue( text );
		event.target.value = '';
	}

	return (
		<div className="lf-section-stack">
			<div className="lf-dashboard-hero">
				<div>
					<p className="lf-label">{ __( 'Template library', 'librefunnels' ) }</p>
					<h2>{ __( 'Funnel shapes belong here, not inside the canvas.', 'librefunnels' ) }</h2>
					<p>{ __( 'Start from bundled funnel patterns, import a portable JSON package, or export the selected funnel without relying on any remote template service.', 'librefunnels' ) }</p>
				</div>
				<div className="lf-overview__actions">
					<button className="lf-button lf-button--primary" type="button" onClick={ onCreateStarterFunnel } disabled={ isSaving }>
						{ __( 'Use starter funnel', 'librefunnels' ) }
					</button>
					<button className="lf-button" type="button" onClick={ onCreateFunnel } disabled={ isSaving }>
						{ __( 'Create blank funnel', 'librefunnels' ) }
					</button>
				</div>
			</div>

			<GuidedStarterPanel
				products={ products }
				onSearchProducts={ onSearchProducts }
				onCreate={ onCreateStarterFunnel }
				isSaving={ isSaving }
			/>

			<div className="lf-template-grid">
				{ isLoading ? (
					<div className="lf-empty-small">{ __( 'Loading bundled templates...', 'librefunnels' ) }</div>
				) : (
					templates.map( ( template ) => (
						<section className="lf-template-card" key={ template.slug }>
							<div className="lf-template-card__header">
								<span className={ `lf-page-status ${ template.isRecommended ? 'is-publish' : '' }` }>
									{ template.isRecommended ? __( 'Recommended', 'librefunnels' ) : template.category }
								</span>
								<small>{ sprintf( _n( '%d step', '%d steps', template.stepCount, 'librefunnels' ), template.stepCount ) }</small>
							</div>
							<h3>{ template.title }</h3>
							<p>{ template.description }</p>
							<small>{ template.stepSummary }</small>
							<div className="lf-action-row">
								<button className="lf-button lf-button--primary" type="button" onClick={ () => onCreateTemplate( template.slug, { redirectToBuilder: true, redirectTab: 'steps' } ) } disabled={ isSaving }>
									{ __( 'Create funnel', 'librefunnels' ) }
								</button>
							</div>
						</section>
					) )
				) }
			</div>

			<div className="lf-section-grid">
				<section className="lf-section-card lf-section-card--wide">
					<p className="lf-label">{ __( 'Import funnel JSON', 'librefunnels' ) }</p>
					<h3>{ __( 'Bring a portable funnel package into this store', 'librefunnels' ) }</h3>
					<p>{ __( 'LibreFunnels imports normalized JSON only. Imported funnels and pages are created as drafts so you can review them before publishing.', 'librefunnels' ) }</p>
					<label className="lf-field">
						<span>{ __( 'Imported funnel title (optional)', 'librefunnels' ) }</span>
						<input value={ importTitle } onChange={ ( event ) => setImportTitle( event.target.value ) } placeholder={ __( 'Keep the package title', 'librefunnels' ) } />
					</label>
					<label className="lf-field">
						<span>{ __( 'Paste funnel JSON', 'librefunnels' ) }</span>
						<textarea rows="10" value={ importValue } onChange={ ( event ) => setImportValue( event.target.value ) } placeholder={ __( '{ \"format\": \"librefunnels.funnel\", ... }', 'librefunnels' ) } />
					</label>
					<div className="lf-action-row">
						<label className="lf-button" htmlFor="lf-import-json-file">
							{ __( 'Load file', 'librefunnels' ) }
						</label>
						<input id="lf-import-json-file" className="lf-hidden-input" type="file" accept=".json,application/json" onChange={ handleImportFile } />
						<button className="lf-button lf-button--primary" type="button" onClick={ handleImport } disabled={ isSaving || ! importValue.trim() }>
							{ __( 'Import funnel', 'librefunnels' ) }
						</button>
					</div>
				</section>

				<section className="lf-section-card">
					<p className="lf-label">{ __( 'Export current funnel', 'librefunnels' ) }</p>
					<h3>{ selectedFunnel ? getPostTitle( selectedFunnel, __( 'Untitled funnel', 'librefunnels' ) ) : __( 'No funnel selected', 'librefunnels' ) }</h3>
					<p>{ selectedFunnel ? __( 'Download the selected funnel as a reusable LibreFunnels JSON package.', 'librefunnels' ) : __( 'Select a funnel on Dashboard, Analytics, or the builder sidebar, then come back here to export it.', 'librefunnels' ) }</p>
					<button className="lf-button lf-button--primary" type="button" onClick={ () => onExportFunnelPackage( selectedFunnel ) } disabled={ isSaving || ! selectedFunnel }>
						{ __( 'Export funnel JSON', 'librefunnels' ) }
					</button>
				</section>
			</div>
		</div>
	);
}

function GuidedStarterPanel( { products = [], onSearchProducts, onCreate, isSaving } ) {
	const [ title, setTitle ] = useState( '' );
	const [ checkoutProductId, setCheckoutProductId ] = useState( 0 );
	const [ offerProductId, setOfferProductId ] = useState( 0 );

	function handleCreate() {
		onCreate( {
			title,
			checkoutProductId,
			offerProductId,
			redirectToBuilder: true,
			redirectTab: 'steps',
		} );
	}

	return (
		<section className="lf-section-card lf-guided-starter">
			<div className="lf-guided-starter__header">
				<div>
					<p className="lf-label">{ __( 'Guided starter', 'librefunnels' ) }</p>
					<h3>{ __( 'Create the pages and connect the first product for me', 'librefunnels' ) }</h3>
					<p>{ __( 'LibreFunnels will create landing, checkout, and thank-you draft pages, assign the checkout product, then open the Steps view so you can edit each page design in WordPress.', 'librefunnels' ) }</p>
				</div>
				<button className="lf-button lf-button--primary" type="button" onClick={ handleCreate } disabled={ isSaving }>
					{ __( 'Create guided starter', 'librefunnels' ) }
				</button>
			</div>

			<div className="lf-guided-starter__grid">
				<label className="lf-field">
					<span>{ __( 'Starter funnel title (optional)', 'librefunnels' ) }</span>
					<input value={ title } onChange={ ( event ) => setTitle( event.target.value ) } placeholder={ __( 'Starter Checkout Funnel', 'librefunnels' ) } />
				</label>

				<ProductPicker
					value={ checkoutProductId }
					products={ products }
					onSearch={ onSearchProducts }
					onChange={ setCheckoutProductId }
					label={ __( 'Checkout product', 'librefunnels' ) }
					placeholder={ __( 'Search for the product shoppers will buy...', 'librefunnels' ) }
				/>

				<ProductPicker
					value={ offerProductId }
					products={ products }
					onSearch={ onSearchProducts }
					onChange={ setOfferProductId }
					label={ __( 'Optional offer product', 'librefunnels' ) }
					placeholder={ __( 'Search for an upsell or downsell product...', 'librefunnels' ) }
				/>
			</div>
		</section>
	);
}

function AnalyticsSection( { funnels, selectedFunnel, funnelSteps, summary, isLoading, error, onSelectFunnel } ) {
	return (
		<div className="lf-section-stack">
			<div className="lf-dashboard-hero">
				<div>
					<p className="lf-label">{ __( 'Local analytics', 'librefunnels' ) }</p>
					<h2>{ __( 'Revenue and offer signals get their own room.', 'librefunnels' ) }</h2>
					<p>{ __( 'This keeps conversion analysis separate from the visual map while still using first-party WooCommerce attribution.', 'librefunnels' ) }</p>
				</div>
			</div>
			<FunnelSelect funnels={ funnels } selectedFunnel={ selectedFunnel } onSelectFunnel={ onSelectFunnel } />
			{ selectedFunnel ? (
				<AnalyticsSummary selectedFunnel={ selectedFunnel } funnelSteps={ funnelSteps } summary={ summary } isLoading={ isLoading } error={ error } />
			) : (
				<div className="lf-empty-small">{ __( 'Create or select a funnel to see analytics.', 'librefunnels' ) }</div>
			) }
		</div>
	);
}

function FunnelSelect( { funnels, selectedFunnel, onSelectFunnel } ) {
	if ( funnels.length === 0 ) {
		return null;
	}

	return (
		<label className="lf-field lf-section-select">
			<span>{ __( 'Report funnel', 'librefunnels' ) }</span>
			<select value={ Number( selectedFunnel?.id || 0 ) } onChange={ ( event ) => onSelectFunnel( event.target.value ) }>
				{ funnels.map( ( funnel ) => (
					<option key={ funnel.id } value={ funnel.id }>
						{ getPostTitle( funnel, __( 'Untitled funnel', 'librefunnels' ) ) }
					</option>
				) ) }
			</select>
		</label>
	);
}

function SettingsSection( { selectedFunnel } ) {
	return (
		<div className="lf-section-stack">
			<div className="lf-dashboard-hero">
				<div>
					<p className="lf-label">{ __( 'Global controls', 'librefunnels' ) }</p>
					<h2>{ __( 'Settings should feel deliberate, not buried in a step inspector.', 'librefunnels' ) }</h2>
					<p>{ __( 'The actual option persistence will arrive in later slices; this page now establishes the correct product home for global behavior.', 'librefunnels' ) }</p>
				</div>
			</div>
			<div className="lf-settings-grid">
				<SettingsCard title={ __( 'WooCommerce checkout takeover', 'librefunnels' ) } status={ __( 'Planned', 'librefunnels' ) } text={ __( 'Choose whether the default WooCommerce checkout redirects into a selected LibreFunnels funnel.', 'librefunnels' ) } />
				<SettingsCard title={ __( 'Universal payment behavior', 'librefunnels' ) } status={ __( 'Safe fallback', 'librefunnels' ) } text={ __( 'Keep gateway handling compatible by default, then expose one-click options only where gateways support them safely.', 'librefunnels' ) } />
				<SettingsCard title={ __( 'Privacy and analytics', 'librefunnels' ) } status={ __( 'Local only', 'librefunnels' ) } text={ __( 'Analytics stay first-party in the WordPress database. No tracking service or remote executable code.', 'librefunnels' ) } />
				<SettingsCard title={ __( 'Selected funnel defaults', 'librefunnels' ) } status={ selectedFunnel ? getPostTitle( selectedFunnel, __( 'Untitled funnel', 'librefunnels' ) ) : __( 'None selected', 'librefunnels' ) } text={ __( 'Future global defaults can use the selected funnel context without mixing with visual editing.', 'librefunnels' ) } />
			</div>
		</div>
	);
}

function SettingsCard( { title, status, text } ) {
	return (
		<section className="lf-section-card">
			<span className="lf-page-status">{ status }</span>
			<h3>{ title }</h3>
			<p>{ text }</p>
		</section>
	);
}

function SetupSection( { selectedFunnel, setupGuide, products, onSearchProducts, onCreateStarterFunnel, isSaving } ) {
	const storeTasks = [
		{
			id: 'permalinks',
			label: __( 'Permalinks', 'librefunnels' ),
			detail: siteReadiness.prettyPermalinks
				? __( 'Pretty permalinks are enabled.', 'librefunnels' )
				: __( 'Switch permalinks away from Plain before sending traffic.', 'librefunnels' ),
			done: Boolean( siteReadiness.prettyPermalinks ),
		},
		{
			id: 'products',
			label: __( 'Products', 'librefunnels' ),
			detail:
				Number( siteReadiness.productCount || 0 ) > 0
					? sprintf( __( '%d WooCommerce product(s) are ready to assign.', 'librefunnels' ), Number( siteReadiness.productCount || 0 ) )
					: __( 'Publish at least one WooCommerce product before testing funnel checkout.', 'librefunnels' ),
			done: Number( siteReadiness.productCount || 0 ) > 0,
		},
		{
			id: 'gateway',
			label: __( 'Gateway', 'librefunnels' ),
			detail:
				Number( siteReadiness.enabledGateways || 0 ) > 0
					? sprintf( __( '%d payment gateway(s) are enabled.', 'librefunnels' ), Number( siteReadiness.enabledGateways || 0 ) )
					: __( 'Enable a payment gateway so test orders can complete.', 'librefunnels' ),
			done: Number( siteReadiness.enabledGateways || 0 ) > 0,
		},
		{
			id: 'checkout',
			label: __( 'Woo checkout', 'librefunnels' ),
			detail:
				Number( siteReadiness.checkoutPageId || 0 ) > 0
					? __( 'WooCommerce checkout page exists.', 'librefunnels' )
					: __( 'WooCommerce still needs its core checkout page.', 'librefunnels' ),
			done: Number( siteReadiness.checkoutPageId || 0 ) > 0,
		},
	];

	return (
		<div className="lf-section-stack">
			<div className="lf-dashboard-hero">
				<div>
					<p className="lf-label">{ __( 'Launch readiness', 'librefunnels' ) }</p>
					<h2>{ __( 'A guided setup page for beginners.', 'librefunnels' ) }</h2>
					<p>{ __( 'Setup collects the operational checklist so the builder can stay focused on funnel structure.', 'librefunnels' ) }</p>
				</div>
				<div className="lf-overview__actions">
					{ ! selectedFunnel && (
						<button className="lf-button lf-button--primary" type="button" onClick={ onCreateStarterFunnel } disabled={ isSaving }>
							{ __( 'Create starter funnel', 'librefunnels' ) }
						</button>
					) }
					<a className="lf-button" href={ adminPages.funnels || 'admin.php?page=librefunnels-funnels' }>
						{ __( 'Open builder', 'librefunnels' ) }
					</a>
				</div>
			</div>
			{ ! selectedFunnel && (
				<GuidedStarterPanel
					products={ products }
					onSearchProducts={ onSearchProducts }
					onCreate={ onCreateStarterFunnel }
					isSaving={ isSaving }
				/>
			) }
			<section className="lf-section-card">
				<p className="lf-label">{ __( 'Selected funnel setup', 'librefunnels' ) }</p>
				<h3>{ selectedFunnel ? getPostTitle( selectedFunnel, __( 'Untitled funnel', 'librefunnels' ) ) : __( 'No funnel selected', 'librefunnels' ) }</h3>
				{ selectedFunnel ? <SetupProgress setupGuide={ setupGuide } /> : <p>{ __( 'Create or select a funnel to see its launch checklist.', 'librefunnels' ) }</p> }
			</section>
			<section className="lf-section-card">
				<p className="lf-label">{ __( 'Store checklist', 'librefunnels' ) }</p>
				<h3>{ __( 'Before sending traffic', 'librefunnels' ) }</h3>
				<ol className="lf-setup-list">
					{ storeTasks.map( ( task ) => (
						<li key={ task.id } className={ `lf-setup-item ${ task.done ? 'is-done' : '' }` }>
							<span className="lf-setup-item__mark" aria-hidden="true" />
							<span>
								<strong>{ task.label }</strong>
								<small>{ task.detail }</small>
							</span>
						</li>
					) ) }
				</ol>
			</section>
		</div>
	);
}

function SectionLinkCard( { title, text, href } ) {
	return (
		<a className="lf-next-card" href={ href || '#' }>
			<strong>{ title }</strong>
			<span>{ text }</span>
		</a>
	);
}

function WorkspaceTabs( { activeTab, onChange } ) {
	return (
		<nav className="lf-workspace-tabs" aria-label={ __( 'Funnel workspace sections', 'librefunnels' ) }>
			{ workspaceTabs.map( ( tab ) => (
				<button
					key={ tab.id }
					className={ activeTab === tab.id ? 'is-active' : '' }
					type="button"
					aria-current={ activeTab === tab.id ? 'page' : undefined }
					onClick={ () => onChange( tab.id ) }
				>
					{ tab.label }
				</button>
			) ) }
		</nav>
	);
}

function WorkspaceContent( {
	activeTab,
	isLoading,
	graph,
	steps,
	pages,
	products,
	selectedFunnel,
	selectedItem,
	connectionDraft,
	setupGuide,
	warnings,
	analyticsSummary,
	isAnalyticsLoading,
	analyticsError,
	funnelSteps,
	onSelect,
	onStartDrag,
	onStartConnection,
	onCreateFunnel,
	onCreateStarterFunnel,
	onCreateStep,
	onCreateStarterPath,
	onCreateEdge,
	onUpdateStep,
	onDeleteStep,
	onSetStartStep,
	onSearchPages,
	onSearchProducts,
	onCreatePageForStep,
	onSaveGraph,
	onOpenTab,
	isSaving,
} ) {
	if ( ! selectedFunnel ) {
		return (
			<div className="lf-workspace-panel">
				<Canvas
					isLoading={ isLoading }
					graph={ graph }
					steps={ steps }
					selectedFunnel={ selectedFunnel }
					selectedItem={ selectedItem }
					connectionDraft={ connectionDraft }
					onSelect={ onSelect }
					onStartDrag={ onStartDrag }
					onStartConnection={ onStartConnection }
					onCreateFunnel={ onCreateFunnel }
					onCreateStarterFunnel={ onCreateStarterFunnel }
					onCreateStep={ onCreateStep }
					onCreateStarterPath={ onCreateStarterPath }
					isSaving={ isSaving }
				/>
			</div>
		);
	}

	if ( activeTab === 'canvas' ) {
		return (
			<div className="lf-canvas-workspace">
				<div className="lf-canvas-column">
					<StepPalette onCreateStep={ onCreateStep } isSaving={ isSaving } />
					<Canvas
						isLoading={ isLoading }
						graph={ graph }
						steps={ steps }
						selectedFunnel={ selectedFunnel }
						selectedItem={ selectedItem }
						connectionDraft={ connectionDraft }
						onSelect={ onSelect }
						onStartDrag={ onStartDrag }
						onStartConnection={ onStartConnection }
						onCreateFunnel={ onCreateFunnel }
						onCreateStarterFunnel={ onCreateStarterFunnel }
						onCreateStep={ onCreateStep }
						onCreateStarterPath={ onCreateStarterPath }
						isSaving={ isSaving }
					/>
				</div>
				<Inspector
					selectedItem={ selectedItem }
					selectedFunnel={ selectedFunnel }
					graph={ graph }
					steps={ steps }
					pages={ pages }
					products={ products }
					funnelSteps={ funnelSteps }
					onSelect={ onSelect }
					onSaveGraph={ onSaveGraph }
					onUpdateStep={ onUpdateStep }
					onDeleteStep={ onDeleteStep }
					onSetStartStep={ onSetStartStep }
					onSearchPages={ onSearchPages }
					onSearchProducts={ onSearchProducts }
					onCreatePageForStep={ onCreatePageForStep }
					isSaving={ isSaving }
				/>
			</div>
		);
	}

	if ( activeTab === 'steps' ) {
		return (
			<StepsPanel
				funnelSteps={ funnelSteps }
				graph={ graph }
				selectedFunnel={ selectedFunnel }
				onCreateStep={ onCreateStep }
				onCreateStarterPath={ onCreateStarterPath }
				onSelect={ onSelect }
				onOpenTab={ onOpenTab }
				isSaving={ isSaving }
			/>
		);
	}

	if ( activeTab === 'products' ) {
		return <ProductsPanel funnelSteps={ funnelSteps } graph={ graph } onSelect={ onSelect } onOpenTab={ onOpenTab } />;
	}

	if ( activeTab === 'offers' ) {
		return <OffersPanel funnelSteps={ funnelSteps } graph={ graph } onSelect={ onSelect } onOpenTab={ onOpenTab } />;
	}

	if ( activeTab === 'rules' ) {
		return <RulesPanel graph={ graph } steps={ steps } onSelect={ onSelect } onCreateEdge={ onCreateEdge } onOpenTab={ onOpenTab } isSaving={ isSaving } />;
	}

	if ( activeTab === 'analytics' ) {
		return (
			<div className="lf-workspace-panel">
				<AnalyticsSummary
					selectedFunnel={ selectedFunnel }
					funnelSteps={ funnelSteps }
					summary={ analyticsSummary }
					isLoading={ isAnalyticsLoading }
					error={ analyticsError }
				/>
			</div>
		);
	}

	if ( activeTab === 'settings' ) {
		return (
			<div className="lf-workspace-panel lf-workspace-panel--settings">
				<FunnelInspector
					selectedFunnel={ selectedFunnel }
					funnelSteps={ funnelSteps }
					graph={ graph }
					onSetStartStep={ onSetStartStep }
					isSaving={ isSaving }
				/>
			</div>
		);
	}

	return (
		<OverviewPanel
			selectedFunnel={ selectedFunnel }
			graph={ graph }
			funnelSteps={ funnelSteps }
			setupGuide={ setupGuide }
			warnings={ warnings }
			analyticsSummary={ analyticsSummary }
			isAnalyticsLoading={ isAnalyticsLoading }
			onCreateStarterPath={ onCreateStarterPath }
			onOpenTab={ onOpenTab }
			isSaving={ isSaving }
		/>
	);
}

function OverviewPanel( { selectedFunnel, graph, funnelSteps, setupGuide, warnings, analyticsSummary, isAnalyticsLoading, onCreateStarterPath, onOpenTab, isSaving } ) {
	const checkoutStep = funnelSteps.find( ( step ) => step.type === 'checkout' );
	const offerSteps = funnelSteps.filter( ( step ) => [ 'upsell', 'downsell', 'cross_sell', 'pre_checkout_offer' ].includes( step.type ) );
	const pageReadyCount = funnelSteps.filter( ( step ) => Number( step.pageId || 0 ) > 0 ).length;
	const revenue = Number( analyticsSummary?.revenue || 0 );

	return (
		<section className="lf-overview lf-workspace-panel">
			<div className="lf-overview__hero">
				<div>
					<p className="lf-label">{ __( 'Funnel overview', 'librefunnels' ) }</p>
					<h2>{ getPostTitle( selectedFunnel, __( 'Untitled funnel', 'librefunnels' ) ) }</h2>
					<p>{ setupGuide?.message || __( 'Shape the path, create pages, assign products, and test the shopper journey.', 'librefunnels' ) }</p>
				</div>
				<div className="lf-overview__actions">
					{ graph.nodes.length === 0 && (
						<button className="lf-button lf-button--primary" type="button" onClick={ onCreateStarterPath } disabled={ isSaving }>
							{ __( 'Build checkout path', 'librefunnels' ) }
						</button>
					) }
					<button className="lf-button" type="button" onClick={ () => onOpenTab( 'canvas' ) }>
						{ __( 'Open canvas', 'librefunnels' ) }
					</button>
				</div>
			</div>

			<SetupProgress setupGuide={ setupGuide } />

			<div className="lf-overview-grid">
				<OverviewStat label={ __( 'Steps', 'librefunnels' ) } value={ formatPlainNumber( funnelSteps.length ) } detail={ __( 'Landing, checkout, offers, and confirmation pages.', 'librefunnels' ) } />
				<OverviewStat label={ __( 'Pages assigned', 'librefunnels' ) } value={ `${ pageReadyCount }/${ funnelSteps.length || 0 }` } detail={ __( 'Normal WordPress pages ready for your page builder.', 'librefunnels' ) } />
				<OverviewStat label={ __( 'Offers', 'librefunnels' ) } value={ formatPlainNumber( offerSteps.length ) } detail={ __( 'Upsell, downsell, cross-sell, or pre-checkout offers.', 'librefunnels' ) } />
				<OverviewStat label={ __( 'Revenue', 'librefunnels' ) } value={ isAnalyticsLoading ? __( 'Loading', 'librefunnels' ) : formatCurrency( revenue, analyticsSummary?.currency || 'USD' ) } detail={ __( 'Attributed WooCommerce revenue in the last 30 days.', 'librefunnels' ) } />
			</div>

			<div className="lf-next-cards">
				<button className="lf-next-card" type="button" onClick={ () => onOpenTab( 'steps' ) }>
					<strong>{ __( 'Plan every page in the path', 'librefunnels' ) }</strong>
					<span>{ __( 'Add landing, opt-in, checkout, upsell, downsell, thank-you, or custom steps without crowding the map.', 'librefunnels' ) }</span>
				</button>
				<button className="lf-next-card" type="button" onClick={ () => onOpenTab( 'products' ) }>
					<strong>{ checkoutStep ? __( 'Review checkout products', 'librefunnels' ) : __( 'Add a checkout step', 'librefunnels' ) }</strong>
					<span>{ __( 'Keep product and bump work in its own area, then jump into the focused step inspector when needed.', 'librefunnels' ) }</span>
				</button>
				<button className="lf-next-card" type="button" onClick={ () => onOpenTab( 'rules' ) }>
					<strong>{ warnings.length ? __( 'Resolve routing issues', 'librefunnels' ) : __( 'Review route logic', 'librefunnels' ) }</strong>
					<span>{ __( 'See continue, accept, reject, conditional, and fallback routes away from the visual layout work.', 'librefunnels' ) }</span>
				</button>
			</div>
		</section>
	);
}

function OverviewStat( { label, value, detail } ) {
	return (
		<div className="lf-overview-stat">
			<span>{ label }</span>
			<strong>{ value }</strong>
			<p>{ detail }</p>
		</div>
	);
}

function StepPalette( { onCreateStep, isSaving } ) {
	return (
		<div className="lf-step-palette" aria-label={ __( 'Add funnel step', 'librefunnels' ) }>
			<span className="lf-label">{ __( 'Add step', 'librefunnels' ) }</span>
			<div>
				{ starterStepTypes.map( ( type ) => (
					<button key={ type } className="lf-button" type="button" onClick={ () => onCreateStep( type ) } disabled={ isSaving }>
						{ sprintf( __( 'Add %s', 'librefunnels' ), stepTypes[ type ] || type ) }
					</button>
				) ) }
			</div>
		</div>
	);
}

function StepsPanel( { funnelSteps, graph, selectedFunnel, onCreateStep, onCreateStarterPath, onSelect, onOpenTab, isSaving } ) {
	const nodesByStepId = new Map( graph.nodes.map( ( node ) => [ Number( node.stepId ), node ] ) );

	return (
		<section className="lf-workspace-panel lf-steps-panel">
			<div className="lf-section-heading">
				<div>
					<p className="lf-label">{ __( 'Steps', 'librefunnels' ) }</p>
					<h2>{ __( 'Build the full funnel path', 'librefunnels' ) }</h2>
					<p>{ __( 'Add landing, opt-in, checkout, upsell, downsell, thank-you, and custom steps here. The canvas can stay focused on route shape.', 'librefunnels' ) }</p>
				</div>
				{ graph.nodes.length === 0 && (
					<button className="lf-button lf-button--primary" type="button" onClick={ onCreateStarterPath } disabled={ isSaving }>
						{ __( 'Build checkout path', 'librefunnels' ) }
					</button>
				) }
			</div>

			<StepPalette onCreateStep={ onCreateStep } isSaving={ isSaving } />

			<div className="lf-step-type-grid">
				{ starterStepTypes.map( ( type ) => (
					<div key={ type } className="lf-step-type-card">
						<strong>{ stepTypes[ type ] || type }</strong>
						<p>{ stepTypeDescriptions[ type ] || __( 'Add this step to the funnel path.', 'librefunnels' ) }</p>
					</div>
				) ) }
			</div>

			<div className="lf-step-table" aria-label={ __( 'Funnel steps', 'librefunnels' ) }>
				{ funnelSteps.length === 0 ? (
					<div className="lf-empty-small">
						{ __( 'No steps yet. Start with the guided checkout path or add the exact step you need.', 'librefunnels' ) }
					</div>
				) : (
					funnelSteps.map( ( step ) => {
						const node = nodesByStepId.get( Number( step.id ) );
						const isStart = Number( selectedFunnel.startStepId || 0 ) === Number( step.id );

						return (
							<div className="lf-step-row" key={ step.id }>
								<div>
									<span className="lf-node__type">{ stepTypes[ step.type ] || step.type }</span>
									<strong>{ getPostTitle( step, __( 'Untitled step', 'librefunnels' ) ) }</strong>
									<small>{ getNodePageMeta( step, isStart ) }</small>
								</div>
								<div className="lf-step-row__actions">
									{ step.pageEditUrl && (
										<a className="lf-button" href={ step.pageEditUrl }>
											{ __( 'Edit page design', 'librefunnels' ) }
										</a>
									) }
									{ step.pageUrl && (
										<a className="lf-button" href={ step.pageUrl } target="_blank" rel="noreferrer">
											{ step.pageStatus === 'publish' ? __( 'View page', 'librefunnels' ) : __( 'Preview page', 'librefunnels' ) }
										</a>
									) }
									{ node && (
										<button
											className="lf-button"
											type="button"
											onClick={ () => {
												onSelect( { type: 'node', id: node.id } );
												onOpenTab( 'canvas' );
											} }
										>
											{ __( 'Edit step', 'librefunnels' ) }
										</button>
									) }
								</div>
							</div>
						);
					} )
				) }
			</div>
		</section>
	);
}

function ProductsPanel( { funnelSteps, graph, onSelect, onOpenTab } ) {
	const commerceSteps = funnelSteps.filter( ( step ) => step.type === 'checkout' );
	const nodesByStepId = new Map( graph.nodes.map( ( node ) => [ Number( node.stepId ), node ] ) );

	return (
		<section className="lf-workspace-panel lf-products-panel">
			<div className="lf-section-heading">
				<div>
					<p className="lf-label">{ __( 'Products', 'librefunnels' ) }</p>
					<h2>{ __( 'Commerce configuration by step', 'librefunnels' ) }</h2>
					<p>{ __( 'Keep checkout products and order bumps in one place so the canvas can stay focused on path shape and status.', 'librefunnels' ) }</p>
				</div>
			</div>
			<div className="lf-step-table">
				{ commerceSteps.length === 0 ? (
					<div className="lf-empty-small">
						{ __( 'Add a checkout step, then product and order bump controls will appear here.', 'librefunnels' ) }
					</div>
				) : (
					commerceSteps.map( ( step ) => {
						const node = nodesByStepId.get( Number( step.id ) );
						const checkoutCount = Array.isArray( step.checkoutProducts ) ? step.checkoutProducts.length : 0;
						const bumpCount = Array.isArray( step.orderBumps ) ? step.orderBumps.length : 0;

						return (
							<div className="lf-step-row" key={ step.id }>
								<div>
									<span className="lf-node__type">{ stepTypes[ step.type ] || step.type }</span>
									<strong>{ getPostTitle( step, __( 'Untitled step', 'librefunnels' ) ) }</strong>
									<small>
										{ sprintf( __( '%1$d checkout product(s), %2$d bump(s)', 'librefunnels' ), checkoutCount, bumpCount ) }
									</small>
								</div>
								{ node && (
									<button
										className="lf-button"
										type="button"
										onClick={ () => {
											onSelect( { type: 'node', id: node.id } );
											onOpenTab( 'canvas' );
										} }
									>
										{ __( 'Edit products', 'librefunnels' ) }
									</button>
								) }
							</div>
						);
					} )
				) }
			</div>
		</section>
	);
}

function OffersPanel( { funnelSteps, graph, onSelect, onOpenTab } ) {
	const offerSteps = funnelSteps.filter( ( step ) => [ 'pre_checkout_offer', 'upsell', 'downsell', 'cross_sell' ].includes( step.type ) );
	const nodesByStepId = new Map( graph.nodes.map( ( node ) => [ Number( node.stepId ), node ] ) );

	return (
		<section className="lf-workspace-panel lf-products-panel">
			<div className="lf-section-heading">
				<div>
					<p className="lf-label">{ __( 'Offers', 'librefunnels' ) }</p>
					<h2>{ __( 'Upsells, downsells, and offer routing', 'librefunnels' ) }</h2>
					<p>{ __( 'See post-checkout and pre-checkout offer steps together, then jump into the focused inspector to assign the product and copy.', 'librefunnels' ) }</p>
				</div>
			</div>
			<div className="lf-step-table">
				{ offerSteps.length === 0 ? (
					<div className="lf-empty-small">
						{ __( 'Add an upsell, downsell, cross-sell, or pre-checkout offer step to configure offer products here.', 'librefunnels' ) }
					</div>
				) : (
					offerSteps.map( ( step ) => {
						const node = nodesByStepId.get( Number( step.id ) );
						const hasOffer = Boolean( step.offer?.product_id );

						return (
							<div className="lf-step-row" key={ step.id }>
								<div>
									<span className="lf-node__type">{ stepTypes[ step.type ] || step.type }</span>
									<strong>{ getPostTitle( step, __( 'Untitled step', 'librefunnels' ) ) }</strong>
									<small>{ hasOffer ? __( 'Offer product configured', 'librefunnels' ) : __( 'Offer product still needed', 'librefunnels' ) }</small>
								</div>
								{ node && (
									<button
										className="lf-button"
										type="button"
										onClick={ () => {
											onSelect( { type: 'node', id: node.id } );
											onOpenTab( 'canvas' );
										} }
									>
										{ __( 'Edit offer', 'librefunnels' ) }
									</button>
								) }
							</div>
						);
					} )
				) }
			</div>
		</section>
	);
}

function RulesPanel( { graph, steps, onSelect, onCreateEdge, onOpenTab, isSaving } ) {
	const nodeMap = new Map( graph.nodes.map( ( node ) => [ node.id, node ] ) );

	function getStepNameFromNodeId( nodeId ) {
		const node = nodeMap.get( nodeId );
		const step = node ? getStepById( steps, node.stepId ) : null;

		return step ? getPostTitle( step, __( 'Untitled step', 'librefunnels' ) ) : __( 'Missing step', 'librefunnels' );
	}

	return (
		<section className="lf-workspace-panel lf-rules-panel">
			<div className="lf-section-heading">
				<div>
					<p className="lf-label">{ __( 'Rules', 'librefunnels' ) }</p>
					<h2>{ __( 'Route logic and conditions', 'librefunnels' ) }</h2>
					<p>{ __( 'Review continue, accept, reject, conditional, and fallback routes here, then edit detailed logic in the route inspector.', 'librefunnels' ) }</p>
				</div>
				<button className="lf-button" type="button" onClick={ onCreateEdge } disabled={ isSaving || graph.nodes.length < 2 }>
					{ __( 'Connect route', 'librefunnels' ) }
				</button>
			</div>
			<div className="lf-step-table">
				{ graph.edges.length === 0 ? (
					<div className="lf-empty-small">
						{ __( 'No routes yet. Add at least two steps, then connect the path shoppers should follow.', 'librefunnels' ) }
					</div>
				) : (
					graph.edges.map( ( edge ) => (
						<div className="lf-step-row" key={ edge.id }>
							<div>
								<span className="lf-node__type">{ routes[ edge.route ] || edge.route }</span>
								<strong>{ sprintf( __( '%1$s to %2$s', 'librefunnels' ), getStepNameFromNodeId( edge.source ), getStepNameFromNodeId( edge.target ) ) }</strong>
								<small>{ edge.route === 'conditional' && edge.rule?.type ? ruleLabels[ edge.rule.type ] || edge.rule.type : __( 'No condition required', 'librefunnels' ) }</small>
							</div>
							<button
								className="lf-button"
								type="button"
								onClick={ () => {
									onSelect( { type: 'edge', id: edge.id } );
									onOpenTab( 'canvas' );
								} }
							>
								{ __( 'Edit route', 'librefunnels' ) }
							</button>
						</div>
					) )
				) }
			</div>
		</section>
	);
}

function Sidebar( { funnels, selectedFunnelId, onSelect, onCreate, isLoading, isSaving } ) {
	return (
		<aside className="lf-sidebar">
			<div className="lf-brand">
				<span className="lf-brand__mark">LF</span>
				<div>
					<p>{ __( 'LibreFunnels', 'librefunnels' ) }</p>
					<strong>{ __( 'Funnel Workspace', 'librefunnels' ) }</strong>
				</div>
			</div>

			<button className="lf-button lf-button--primary" type="button" onClick={ onCreate } disabled={ isLoading || isSaving }>
				{ __( 'Create funnel', 'librefunnels' ) }
			</button>

			<div className="lf-sidebar__section">
				<span className="lf-label">{ __( 'Funnels', 'librefunnels' ) }</span>

				{ funnels.length === 0 ? (
					<div className="lf-empty-small">
						{ __( 'No funnels yet. Create one to start shaping your checkout flow.', 'librefunnels' ) }
					</div>
				) : (
					<div className="lf-funnel-list">
						{ funnels.map( ( funnel ) => (
							<button
								key={ funnel.id }
								className={ `lf-funnel-item ${ Number( selectedFunnelId ) === Number( funnel.id ) ? 'is-selected' : '' }` }
								type="button"
								onClick={ () => onSelect( funnel.id ) }
							>
								<strong>{ getPostTitle( funnel, __( 'Untitled funnel', 'librefunnels' ) ) }</strong>
								<span>
									{ funnel.status }
									{ funnel.warnings?.length ? ` · ${ sprintf( __( '%d issue(s)', 'librefunnels' ), funnel.warnings.length ) }` : '' }
								</span>
							</button>
						) ) }
					</div>
				) }
			</div>
		</aside>
	);
}

function Header( { selectedFunnel, warnings, setupGuide, isSaving, notice, onCreateEdge } ) {
	const healthText =
		warnings.length > 0
			? sprintf( __( '%d issue(s)', 'librefunnels' ), warnings.length )
			: setupGuide?.status === 'ready'
				? __( 'Ready', 'librefunnels' )
				: __( 'In progress', 'librefunnels' );

	return (
		<header className="lf-canvas-header">
			<div className="lf-canvas-header__title">
				<p className="lf-label">{ __( 'Funnel workspace', 'librefunnels' ) }</p>
				<h1>{ selectedFunnel ? getPostTitle( selectedFunnel, __( 'Untitled funnel', 'librefunnels' ) ) : __( 'Create your first funnel', 'librefunnels' ) }</h1>
				{ setupGuide && (
					<p className="lf-next-step">
						<strong>{ setupGuide.label }</strong>
						<span>{ setupGuide.message }</span>
					</p>
				) }
				<SetupProgress setupGuide={ setupGuide } compact />
			</div>

			<div className="lf-header-actions">
				{ notice && <span className="lf-save-state">{ notice }</span> }
				<span className={ `lf-health ${ warnings.length > 0 ? 'has-warnings' : 'is-clear' }` }>
					{ healthText }
				</span>
				<button className="lf-button" type="button" onClick={ onCreateEdge } disabled={ ! selectedFunnel || isSaving }>
					{ __( 'Connect route', 'librefunnels' ) }
				</button>
			</div>
		</header>
	);
}

function AnalyticsSummary( { selectedFunnel, funnelSteps = [], summary, isLoading, error } ) {
	const stepRows = useMemo( () => {
		const rows = Array.isArray( summary?.stepBreakdown ) ? summary.stepBreakdown : [];

		return rows.map( ( row ) => {
			const step = getStepById( funnelSteps, row.stepId );

			return {
				...row,
				title: step ? getPostTitle( step, __( 'Untitled step', 'librefunnels' ) ) : sprintf( __( 'Step #%d', 'librefunnels' ), Number( row.stepId || 0 ) ),
				typeLabel: stepTypes[ step?.type ] || __( 'Step', 'librefunnels' ),
			};
		} );
	}, [ summary, funnelSteps ] );

	if ( ! selectedFunnel ) {
		return null;
	}

	const events = summary?.events || {};
	const currency = summary?.currency || 'USD';
	const days = Number( summary?.period?.days || 30 );
	const acceptCount = Number( events.offer_accept?.count || 0 );
	const rejectCount = Number( events.offer_reject?.count || 0 );
	const impressionCount = Number( events.offer_impression?.count || 0 );
	const orders = Number( summary?.orders || 0 );
	const revenue = Number( summary?.revenue || 0 );
	const hasData = revenue > 0 || orders > 0 || impressionCount > 0 || acceptCount > 0 || rejectCount > 0;
	const comparison = summary?.comparison || {};
	const sourceRevenue = summary?.sourceRevenue || {};
	const sourceRows = [
		{
			id: 'checkout_product',
			label: __( 'Checkout products', 'librefunnels' ),
			value: Number( sourceRevenue.checkout_product || 0 ),
			detail: __( 'Primary products sold through funnel checkout.', 'librefunnels' ),
		},
		{
			id: 'order_bump',
			label: __( 'Order bumps', 'librefunnels' ),
			value: Number( sourceRevenue.order_bump || 0 ),
			detail: __( 'Inline bump revenue attributed from order lines.', 'librefunnels' ),
		},
		{
			id: 'offer',
			label: __( 'Offers', 'librefunnels' ),
			value: Number( sourceRevenue.offer || 0 ),
			detail: __( 'Upsell, downsell, and pre-checkout offer lines.', 'librefunnels' ),
		},
	];
	const maxSourceRevenue = Math.max( 1, ...sourceRows.map( ( row ) => row.value ) );
	const offerDecisionText = sprintf(
		__( '%1$d accept / %2$d reject', 'librefunnels' ),
		acceptCount,
		rejectCount
	);

	return (
		<section className={ `lf-analytics ${ isLoading ? 'is-loading' : '' }` } aria-label={ __( 'Funnel analytics summary', 'librefunnels' ) }>
			<div className="lf-analytics__intro">
				<div>
					<p className="lf-label">{ __( 'Analytics', 'librefunnels' ) }</p>
					<h2>{ sprintf( __( 'Last %d days', 'librefunnels' ), days ) }</h2>
				</div>
				<p>
					{ error
						? error
						: hasData
							? __( 'Local funnel signals from offer activity and attributed WooCommerce orders.', 'librefunnels' )
							: __( 'Waiting for shopper data. Publish the funnel, send a test order through it, then revenue and offer decisions appear here.', 'librefunnels' ) }
				</p>
			</div>

			<div className="lf-analytics__metrics" aria-live="polite">
				<AnalyticsMetric
					label={ __( 'Attributed revenue', 'librefunnels' ) }
					value={ isLoading && ! summary ? __( 'Loading', 'librefunnels' ) : formatCurrency( revenue, currency ) }
					detail={ __( 'From marked checkout, bump, and offer order lines.', 'librefunnels' ) }
					trend={ formatMetricComparison( comparison.revenue, ( value ) => formatCurrency( value, currency ) ) }
				/>
				<AnalyticsMetric
					label={ __( 'Orders', 'librefunnels' ) }
					value={ isLoading && ! summary ? '-' : formatPlainNumber( orders ) }
					detail={ __( 'Recorded once per WooCommerce order and funnel.', 'librefunnels' ) }
					trend={ formatMetricComparison( comparison.orders ) }
				/>
				<AnalyticsMetric
					label={ __( 'Offer accept rate', 'librefunnels' ) }
					value={ isLoading && ! summary ? '-' : formatPercent( summary?.offerAcceptRate || 0 ) }
					detail={ sprintf( __( '%d offer view(s) tracked locally.', 'librefunnels' ), impressionCount ) }
					trend={ formatMetricComparison( comparison.offerAcceptRate, formatPercent ) }
				/>
				<AnalyticsMetric
					label={ __( 'Offer decisions', 'librefunnels' ) }
					value={ isLoading && ! summary ? '-' : offerDecisionText }
					detail={ __( 'Accept and reject clicks from public offer steps.', 'librefunnels' ) }
					trend={ formatMetricComparison( comparison.offerImpressions ) }
				/>
			</div>

			{ hasData && (
				<div className="lf-analytics__details">
					<section className="lf-analytics-panel">
						<div className="lf-analytics-panel__head">
							<h3>{ __( 'Revenue mix', 'librefunnels' ) }</h3>
							<p>{ __( 'A quick read on where this funnel is making money.', 'librefunnels' ) }</p>
						</div>
						<div className="lf-source-list">
							{ sourceRows.map( ( row ) => (
								<div className="lf-source-row" key={ row.id }>
									<div>
										<strong>{ row.label }</strong>
										<small>{ row.detail }</small>
									</div>
									<span className="lf-source-row__value">{ formatCurrency( row.value, currency ) }</span>
									<span className="lf-source-row__bar" aria-hidden="true">
										<span style={ { width: `${ Math.round( ( row.value / maxSourceRevenue ) * 100 ) }%` } } />
									</span>
								</div>
							) ) }
						</div>
					</section>

					<section className="lf-analytics-panel lf-analytics-panel--wide">
						<div className="lf-analytics-panel__head">
							<h3>{ __( 'Step signals', 'librefunnels' ) }</h3>
							<p>{ __( 'Revenue and offer decisions grouped by the steps LibreFunnels can identify locally.', 'librefunnels' ) }</p>
						</div>
						{ stepRows.length > 0 ? (
							<div className="lf-step-analytics-table-wrap">
								<table className="lf-step-analytics-table">
									<thead>
										<tr>
											<th scope="col">{ __( 'Step', 'librefunnels' ) }</th>
											<th scope="col">{ __( 'Revenue', 'librefunnels' ) }</th>
											<th scope="col">{ __( 'Offer rate', 'librefunnels' ) }</th>
											<th scope="col">{ __( 'Decisions', 'librefunnels' ) }</th>
										</tr>
									</thead>
									<tbody>
										{ stepRows.map( ( row ) => (
											<tr key={ row.stepId }>
												<td>
													<strong>{ row.title }</strong>
													<small>{ row.typeLabel }</small>
												</td>
												<td>
													<strong>{ formatCurrency( row.revenue, currency ) }</strong>
													<small>
														{ sprintf(
															__( 'Checkout %1$s · bumps %2$s · offers %3$s', 'librefunnels' ),
															formatCurrency( row.checkoutRevenue, currency ),
															formatCurrency( row.bumpRevenue, currency ),
															formatCurrency( row.offerRevenue, currency )
														) }
													</small>
												</td>
												<td>
													<strong>{ formatPercent( row.offerAcceptRate || 0 ) }</strong>
													<small>{ sprintf( __( '%d view(s)', 'librefunnels' ), Number( row.offerImpressions || 0 ) ) }</small>
												</td>
												<td>
													<strong>{ sprintf( __( '%1$d / %2$d', 'librefunnels' ), Number( row.offerAccepts || 0 ), Number( row.offerRejects || 0 ) ) }</strong>
													<small>{ __( 'accepts / rejects', 'librefunnels' ) }</small>
												</td>
											</tr>
										) ) }
									</tbody>
								</table>
							</div>
						) : (
							<p className="lf-empty-small">{ __( 'Step-level rows will appear as soon as attributed events include step IDs.', 'librefunnels' ) }</p>
						) }
					</section>
				</div>
			) }
		</section>
	);
}

function AnalyticsMetric( { label, value, detail, trend = '' } ) {
	return (
		<div className="lf-analytics-card">
			<span>{ label }</span>
			<strong>{ value }</strong>
			<p>{ detail }</p>
			{ trend && <small className="lf-metric-trend">{ trend }</small> }
		</div>
	);
}

function SetupProgress( { setupGuide, compact = false } ) {
	if ( ! setupGuide?.tasks?.length ) {
		return null;
	}

	const progress = Math.round( ( setupGuide.completed / setupGuide.total ) * 100 );

	return (
		<div className="lf-setup-progress" aria-label={ __( 'Setup progress', 'librefunnels' ) }>
			<div className="lf-setup-progress__meter">
				<span>{ sprintf( __( '%1$d of %2$d ready', 'librefunnels' ), setupGuide.completed, setupGuide.total ) }</span>
				<span className="lf-setup-progress__bar" aria-hidden="true">
					<span style={ { width: `${ progress }%` } } />
				</span>
			</div>
			{ ! compact && (
				<ol className="lf-setup-list">
					{ setupGuide.tasks.map( ( task ) => (
						<li
							key={ task.id }
							className={ `lf-setup-item ${ task.done ? 'is-done' : '' } ${ task.id === setupGuide.nextTaskId ? 'is-next' : '' }` }
							aria-current={ task.id === setupGuide.nextTaskId ? 'step' : undefined }
						>
							<span className="lf-setup-item__mark" aria-hidden="true" />
							<span>
								<strong>{ task.label }</strong>
								<small>{ task.detail }</small>
							</span>
						</li>
					) ) }
				</ol>
			) }
		</div>
	);
}

function Canvas( { isLoading, graph, steps, selectedFunnel, selectedItem, connectionDraft, onSelect, onStartDrag, onStartConnection, onCreateFunnel, onCreateStep, onCreateStarterPath, onCreateStarterFunnel, isSaving } ) {
	const stageRef = useRef( null );
	const nodes = graph.nodes.map( ( node, index ) => ( {
		...node,
		position: normalizeNodePosition( node, index ),
	} ) );
	const nodeMap = new Map( nodes.map( ( node ) => [ node.id, node ] ) );
	const canvasWidth = Math.max( 960, ...nodes.map( ( node ) => node.position.x + canvasNodeWidth + 96 ), 960 );
	const canvasHeight = Math.max( 580, ...nodes.map( ( node ) => node.position.y + canvasNodeHeight + 96 ), 580 );

	if ( isLoading ) {
		return <div className="lf-canvas lf-canvas--empty">{ __( 'Loading your funnel workspace...', 'librefunnels' ) }</div>;
	}

	if ( ! selectedFunnel ) {
		return (
			<div className="lf-canvas lf-canvas--empty">
				<h2>{ __( 'Start with one focused checkout journey.', 'librefunnels' ) }</h2>
				<p>{ __( 'Use the starter funnel to get landing, checkout, and thank-you steps with draft pages already created, or start from a blank funnel if you prefer.', 'librefunnels' ) }</p>
				<div className="lf-empty-actions">
					<button className="lf-button lf-button--primary" type="button" onClick={ onCreateStarterFunnel } disabled={ isSaving }>
						{ __( 'Create starter funnel', 'librefunnels' ) }
					</button>
					<button className="lf-button" type="button" onClick={ onCreateFunnel } disabled={ isSaving }>
						{ __( 'Create blank funnel', 'librefunnels' ) }
					</button>
				</div>
			</div>
		);
	}

	if ( nodes.length === 0 ) {
		return (
			<div className="lf-canvas lf-canvas--empty">
				<h2>{ __( 'This funnel is ready for its first step.', 'librefunnels' ) }</h2>
				<p>{ __( 'Build a simple checkout path now, then create each page and edit it in your preferred builder.', 'librefunnels' ) }</p>
				<div className="lf-empty-actions">
					<button className="lf-button lf-button--primary" type="button" onClick={ onCreateStarterPath } disabled={ isSaving }>
						{ __( 'Build checkout path', 'librefunnels' ) }
					</button>
					<button className="lf-button" type="button" onClick={ () => onCreateStep( 'checkout' ) } disabled={ isSaving }>
						{ __( 'Add one step', 'librefunnels' ) }
					</button>
				</div>
			</div>
		);
	}

	return (
		<div className={ `lf-canvas ${ connectionDraft ? 'is-connecting' : '' }` }>
			<div
				ref={ stageRef }
				className="lf-canvas-stage"
				style={ { '--lf-canvas-width': `${ canvasWidth }px`, '--lf-canvas-height': `${ canvasHeight }px` } }
			>
				<div className="lf-canvas-connect-tip">
					<strong>{ __( 'Wire the shopper path', 'librefunnels' ) }</strong>
					<span>{ __( 'Drag from the right dot on one step to the left dot on the next step.', 'librefunnels' ) }</span>
				</div>
				<svg className="lf-edges" viewBox={ `0 0 ${ canvasWidth } ${ canvasHeight }` } role="img" aria-label={ __( 'Funnel route map', 'librefunnels' ) }>
					{ graph.edges.map( ( edge ) => {
						const source = nodeMap.get( edge.source );
						const target = nodeMap.get( edge.target );
						const warnings = getEdgeWarnings( edge, nodes );

						if ( ! source || ! target ) {
							return null;
						}

						const start = getNodeConnectionPoint( source, 'out' );
						const end = getNodeConnectionPoint( target, 'in' );
						const midX = start.x + ( end.x - start.x ) / 2;
						const routeClass = routeClassNames[ edge.route ] || 'continue';
						const sourceStep = getStepById( steps, source.stepId );
						const targetStep = getStepById( steps, target.stepId );
						const routeLabel = routes[ edge.route ] || edge.route;
						const edgeLabel = sprintf(
							__( 'Route %1$s from %2$s to %3$s', 'librefunnels' ),
							routeLabel,
							sourceStep ? getPostTitle( sourceStep, __( 'source step', 'librefunnels' ) ) : __( 'source step', 'librefunnels' ),
							targetStep ? getPostTitle( targetStep, __( 'target step', 'librefunnels' ) ) : __( 'target step', 'librefunnels' )
						);

						return (
							<g
								key={ edge.id }
								className={ `lf-edge lf-edge--${ routeClass } ${ selectedItem.type === 'edge' && selectedItem.id === edge.id ? 'is-selected' : '' } ${ warnings.length ? 'has-warning' : '' }` }
								role="button"
								tabIndex="0"
								aria-label={ edgeLabel }
								onClick={ () => onSelect( { type: 'edge', id: edge.id } ) }
								onKeyDown={ ( event ) => {
									if ( event.key === 'Enter' || event.key === ' ' ) {
										event.preventDefault();
										onSelect( { type: 'edge', id: edge.id } );
									}
								} }
							>
								<path d={ `M ${ start.x } ${ start.y } C ${ midX } ${ start.y }, ${ midX } ${ end.y }, ${ end.x } ${ end.y }` } />
								<text x={ midX } y={ ( start.y + end.y ) / 2 - 8 }>{ routeLabel }</text>
							</g>
						);
					} ) }
					{ connectionDraft && (
						<path
							className="lf-edge-draft"
							d={ `M ${ connectionDraft.source.x } ${ connectionDraft.source.y } C ${ connectionDraft.source.x + 130 } ${ connectionDraft.source.y }, ${ connectionDraft.pointer.x - 130 } ${ connectionDraft.pointer.y }, ${ connectionDraft.pointer.x } ${ connectionDraft.pointer.y }` }
						/>
					) }
				</svg>

				{ graph.edges
					.filter( ( edge ) => getEdgeWarnings( edge, nodes ).length > 0 )
					.map( ( edge, index ) => (
						<button
							key={ edge.id }
							className="lf-broken-edge"
							type="button"
							style={ { left: `${ 32 + index * 220 }px`, top: '24px' } }
							onClick={ () => onSelect( { type: 'edge', id: edge.id } ) }
						>
							{ __( 'Broken route', 'librefunnels' ) }: { edge.id }
						</button>
					) ) }

				{ nodes.map( ( node ) => {
					const step = getStepById( steps, node.stepId );
					const warnings = step ? getNodeWarnings( node, steps, selectedFunnel ) : [ __( 'Missing step', 'librefunnels' ) ];
					const type = step?.type || node.type || 'custom';
					const isStart = Number( selectedFunnel.startStepId || 0 ) === Number( node.stepId );
					const isConnectionSource = connectionDraft?.sourceNodeId === node.id;

					return (
						<div
							key={ node.id }
							className={ `lf-node ${ selectedItem.type === 'node' && selectedItem.id === node.id ? 'is-selected' : '' } ${ warnings.length ? 'has-warning' : '' } ${ isConnectionSource ? 'is-connection-source' : '' }` }
							role="button"
							tabIndex="0"
							style={ { left: `${ node.position.x }px`, top: `${ node.position.y }px` } }
							onClick={ () => onSelect( { type: 'node', id: node.id } ) }
							onKeyDown={ ( event ) => {
								if ( event.key === 'Enter' || event.key === ' ' ) {
									event.preventDefault();
									onSelect( { type: 'node', id: node.id } );
								}
							} }
							onPointerDown={ ( event ) => onStartDrag( event, node ) }
						>
							<button
								className="lf-node-handle lf-node-handle--in"
								type="button"
								data-lf-connect-target={ node.id }
								aria-label={ sprintf( __( 'Connect route to %s', 'librefunnels' ), step ? getPostTitle( step, __( 'this step', 'librefunnels' ) ) : __( 'this step', 'librefunnels' ) ) }
								onPointerDown={ ( event ) => event.stopPropagation() }
								onClick={ ( event ) => {
									event.stopPropagation();
									onSelect( { type: 'node', id: node.id } );
								} }
							/>
							<span className="lf-node__type">{ stepTypes[ type ] || type }</span>
							<strong>{ step ? getPostTitle( step, __( 'Untitled step', 'librefunnels' ) ) : __( 'Missing step', 'librefunnels' ) }</strong>
							<span className="lf-node__meta">
								{ getNodePageMeta( step, isStart ) }
								{ warnings.length > 0 ? ` · ${ __( 'Needs attention', 'librefunnels' ) }` : '' }
							</span>
							<button
								className="lf-node-handle lf-node-handle--out"
								type="button"
								aria-label={ sprintf( __( 'Drag to connect from %s', 'librefunnels' ), step ? getPostTitle( step, __( 'this step', 'librefunnels' ) ) : __( 'this step', 'librefunnels' ) ) }
								onPointerDown={ ( event ) => onStartConnection( event, node, stageRef.current ) }
								onClick={ ( event ) => event.stopPropagation() }
							/>
						</div>
					);
				} ) }
			</div>
		</div>
	);
}

function Inspector( props ) {
	const { selectedItem, selectedFunnel, graph, steps, funnelSteps } = props;

	if ( ! selectedFunnel ) {
		return (
			<aside className="lf-inspector">
				<p className="lf-label">{ __( 'Inspector', 'librefunnels' ) }</p>
				<h2>{ __( 'No funnel selected', 'librefunnels' ) }</h2>
				<p>{ __( 'Create or select a funnel to edit its steps and routes.', 'librefunnels' ) }</p>
			</aside>
		);
	}

	if ( selectedItem.type === 'node' ) {
		const node = graph.nodes.find( ( item ) => item.id === selectedItem.id );
		const step = node ? getStepById( steps, node.stepId ) : null;

		return <NodeInspector { ...props } node={ node } step={ step } warnings={ node ? getNodeWarnings( node, steps, selectedFunnel ) : [] } />;
	}

	if ( selectedItem.type === 'edge' ) {
		const edge = graph.edges.find( ( item ) => item.id === selectedItem.id );

		return <EdgeInspector { ...props } edge={ edge } />;
	}

	return <FunnelInspector { ...props } funnelSteps={ funnelSteps } />;
}

function FunnelInspector( { selectedFunnel, funnelSteps, graph, onSetStartStep, isSaving } ) {
	const startStepId = Number( selectedFunnel.startStepId || 0 );

	return (
		<aside className="lf-inspector">
			<p className="lf-label">{ __( 'Funnel settings', 'librefunnels' ) }</p>
			<h2>{ getPostTitle( selectedFunnel, __( 'Untitled funnel', 'librefunnels' ) ) }</h2>
			<label className="lf-field">
				<span>{ __( 'Start step', 'librefunnels' ) }</span>
				<select value={ startStepId } onChange={ ( event ) => onSetStartStep( event.target.value ) } disabled={ isSaving }>
					<option value="0">{ __( 'Choose a start step', 'librefunnels' ) }</option>
					{ funnelSteps.map( ( step ) => (
						<option key={ step.id } value={ step.id }>
							{ getPostTitle( step, __( 'Untitled step', 'librefunnels' ) ) }
						</option>
					) ) }
				</select>
			</label>
			<div className="lf-inspector__note">
				<strong>{ sprintf( __( '%d node(s)', 'librefunnels' ), graph.nodes.length ) }</strong>
				<span>{ sprintf( __( '%d route(s)', 'librefunnels' ), graph.edges.length ) }</span>
			</div>
			<Warnings warnings={ selectedFunnel.warnings || [] } />
		</aside>
	);
}

function NodeInspector( {
	node,
	step,
	selectedFunnel,
	pages,
	products,
	warnings,
	onUpdateStep,
	onDeleteStep,
	onSetStartStep,
	onSearchPages,
	onSearchProducts,
	onCreatePageForStep,
	isSaving,
} ) {
	const [ title, setTitle ] = useState( step ? getPostTitle( step, '' ) : '' );
	const [ stepType, setStepType ] = useState( step?.type || node?.type || 'custom' );
	const [ activeInspectorSection, setActiveInspectorSection ] = useState( 'details' );
	const hasCommerceControls = step?.type === 'checkout' || [ 'pre_checkout_offer', 'upsell', 'downsell', 'cross_sell' ].includes( step?.type );

	useEffect( () => {
		setTitle( step ? getPostTitle( step, '' ) : '' );
		setStepType( step?.type || node?.type || 'custom' );

		if ( ! step ) {
			setActiveInspectorSection( 'details' );
		} else if ( Number( step.pageId || 0 ) < 1 || ( step.pageStatus && step.pageStatus !== 'publish' ) ) {
			setActiveInspectorSection( 'page' );
		} else if ( step.type === 'checkout' || [ 'pre_checkout_offer', 'upsell', 'downsell', 'cross_sell' ].includes( step.type ) ) {
			setActiveInspectorSection( 'commerce' );
		} else {
			setActiveInspectorSection( 'details' );
		}
	}, [ step?.id, step?.pageId, step?.pageStatus, node?.id ] );

	if ( ! node || ! step ) {
		return (
			<aside className="lf-inspector">
				<p className="lf-label">{ __( 'Step inspector', 'librefunnels' ) }</p>
				<h2>{ __( 'Missing step', 'librefunnels' ) }</h2>
				<p>{ __( 'This canvas node points to a step that no longer exists.', 'librefunnels' ) }</p>
			</aside>
		);
	}

	return (
		<aside className="lf-inspector">
			<p className="lf-label">{ __( 'Step inspector', 'librefunnels' ) }</p>
			<h2>{ getPostTitle( step, __( 'Untitled step', 'librefunnels' ) ) }</h2>
			<Warnings warnings={ warnings } />

			<div className="lf-inspector-tabs" role="tablist" aria-label={ __( 'Step inspector sections', 'librefunnels' ) }>
				<InspectorTabButton section="details" activeSection={ activeInspectorSection } onSelect={ setActiveInspectorSection }>
					{ __( 'Details', 'librefunnels' ) }
				</InspectorTabButton>
				<InspectorTabButton section="page" activeSection={ activeInspectorSection } onSelect={ setActiveInspectorSection }>
					{ __( 'Page', 'librefunnels' ) }
				</InspectorTabButton>
				{ hasCommerceControls && (
					<InspectorTabButton section="commerce" activeSection={ activeInspectorSection } onSelect={ setActiveInspectorSection }>
						{ step.type === 'checkout' ? __( 'Products', 'librefunnels' ) : __( 'Offer', 'librefunnels' ) }
					</InspectorTabButton>
				) }
			</div>

			{ activeInspectorSection === 'details' && (
				<div className="lf-inspector-section">
					<label className="lf-field">
						<span>{ __( 'Step title', 'librefunnels' ) }</span>
						<input value={ title } onChange={ ( event ) => setTitle( event.target.value ) } />
					</label>

					<label className="lf-field">
						<span>{ __( 'Step type', 'librefunnels' ) }</span>
						<select value={ stepType } onChange={ ( event ) => setStepType( event.target.value ) }>
							{ Object.entries( stepTypes ).map( ( [ value, label ] ) => (
								<option key={ value } value={ value }>
									{ label }
								</option>
							) ) }
						</select>
					</label>

					<div className="lf-action-row">
						<button
							className="lf-button lf-button--primary"
							type="button"
							disabled={ isSaving }
							onClick={ () =>
								onUpdateStep( step, {
									title,
									type: stepType,
									page_id: Number( step.pageId || 0 ),
								} )
							}
						>
							{ __( 'Save step', 'librefunnels' ) }
						</button>
						<button className="lf-button" type="button" disabled={ isSaving } onClick={ () => onSetStartStep( step.id ) }>
							{ Number( selectedFunnel.startStepId ) === Number( step.id ) ? __( 'Start step', 'librefunnels' ) : __( 'Make start', 'librefunnels' ) }
						</button>
					</div>

					<button className="lf-button lf-button--danger" type="button" disabled={ isSaving } onClick={ () => onDeleteStep( step ) }>
						{ __( 'Archive step', 'librefunnels' ) }
					</button>
				</div>
			) }

			{ activeInspectorSection === 'page' && (
				<PagePicker
					step={ step }
					pages={ pages }
					onSearch={ onSearchPages }
					onAssign={ ( pageId ) => onUpdateStep( step, { page_id: Number( pageId ) } ) }
					onCreate={ ( pageTitle ) => onCreatePageForStep( step, pageTitle ) }
					isSaving={ isSaving }
				/>
			) }

			{ activeInspectorSection === 'commerce' && hasCommerceControls && (
				<CommercePanel step={ step } products={ products } onSearchProducts={ onSearchProducts } onUpdateStep={ onUpdateStep } isSaving={ isSaving } />
			) }
		</aside>
	);
}

function InspectorTabButton( { section, activeSection, onSelect, children } ) {
	return (
		<button
			className={ `lf-inspector-tab ${ activeSection === section ? 'is-active' : '' }` }
			type="button"
			role="tab"
			aria-selected={ activeSection === section }
			onClick={ () => onSelect( section ) }
		>
			{ children }
		</button>
	);
}

function CommercePanel( { step, products, onSearchProducts, onUpdateStep, isSaving } ) {
	if ( step.type === 'checkout' ) {
		return <CheckoutProductsPanel step={ step } products={ products } onSearchProducts={ onSearchProducts } onUpdateStep={ onUpdateStep } isSaving={ isSaving } />;
	}

	if ( [ 'pre_checkout_offer', 'upsell', 'downsell', 'cross_sell' ].includes( step.type ) ) {
		return <PrimaryOfferPanel step={ step } products={ products } onSearchProducts={ onSearchProducts } onUpdateStep={ onUpdateStep } isSaving={ isSaving } />;
	}

	return null;
}

function CheckoutProductsPanel( { step, products, onSearchProducts, onUpdateStep, isSaving } ) {
	const savedCheckoutProducts = Array.isArray( step.checkoutProducts ) ? step.checkoutProducts : [];
	const savedBumps = Array.isArray( step.orderBumps ) ? step.orderBumps : [];
	const [ checkoutProducts, setCheckoutProducts ] = useState( savedCheckoutProducts.length ? savedCheckoutProducts : [ createEmptyCheckoutProduct() ] );
	const [ bumps, setBumps ] = useState( savedBumps.length ? savedBumps : [ createEmptyOffer() ] );
	const checkoutDirty = ! checkoutProductsMatch( checkoutProducts, savedCheckoutProducts );
	const bumpDirty = ! offerListsMatch( bumps, savedBumps );

	useEffect( () => {
		const nextCheckoutProducts = Array.isArray( step.checkoutProducts ) ? step.checkoutProducts : [];
		const nextBumps = Array.isArray( step.orderBumps ) ? step.orderBumps : [];

		setCheckoutProducts( nextCheckoutProducts.length ? nextCheckoutProducts : [ createEmptyCheckoutProduct() ] );
		setBumps( nextBumps.length ? nextBumps : [ createEmptyOffer() ] );
	}, [ step.id ] );

	function updateCheckoutProduct( index, fields ) {
		setCheckoutProducts( ( current ) =>
			current.map( ( product, productIndex ) =>
				productIndex === index
					? {
							...product,
							...fields,
					  }
					: product
			)
		);
	}

	function removeCheckoutProduct( index ) {
		setCheckoutProducts( ( current ) => {
			const nextProducts = current.filter( ( product, productIndex ) => productIndex !== index );

			return nextProducts.length ? nextProducts : [ createEmptyCheckoutProduct() ];
		} );
	}

	function saveCheckoutProduct() {
		onUpdateStep( step, {
			checkout_products: checkoutProducts
				.map( normalizeCheckoutProductForCompare )
				.filter( ( product ) => product.product_id > 0 ),
		} );
	}

	function updateBump( index, fields ) {
		setBumps( ( current ) =>
			current.map( ( bump, bumpIndex ) =>
				bumpIndex === index
					? {
							...bump,
							...fields,
					  }
					: bump
			)
		);
	}

	function removeBump( index ) {
		setBumps( ( current ) => {
			const nextBumps = current.filter( ( bump, bumpIndex ) => bumpIndex !== index );

			return nextBumps.length ? nextBumps : [ createEmptyOffer() ];
		} );
	}

	function saveBump() {
		onUpdateStep( step, {
			order_bumps: bumps
				.map( ( bump ) => ( {
					...bump,
					quantity: Number( bump.quantity || 1 ),
					variation_id: Number( bump.variation_id || 0 ),
					variation: normalizeVariation( bump.variation || {} ),
				} ) )
				.filter( ( bump ) => Number( bump.product_id || 0 ) > 0 ),
		} );
	}

	return (
		<div className="lf-panellet">
			<div>
				<span className="lf-field-heading">
					{ __( 'Checkout products', 'librefunnels' ) }
					{ checkoutDirty && <em className="lf-dirty-badge">{ __( 'Unsaved', 'librefunnels' ) }</em> }
				</span>
				<p>{ __( 'Choose every product this checkout should prepare in the WooCommerce cart.', 'librefunnels' ) }</p>
			</div>

			<div className="lf-commerce-list lf-commerce-list--checkout-products">
				{ checkoutProducts.map( ( product, index ) => (
					<div className="lf-commerce-card" key={ `checkout-product-${ index }` }>
						<div className="lf-commerce-card__header">
							<strong>{ sprintf( __( 'Checkout product %d', 'librefunnels' ), index + 1 ) }</strong>
							<button className="lf-button" type="button" disabled={ isSaving } onClick={ () => removeCheckoutProduct( index ) }>
								{ __( 'Remove', 'librefunnels' ) }
							</button>
						</div>
						<CheckoutProductFields
							product={ product }
							products={ products }
							onSearchProducts={ onSearchProducts }
							onChange={ ( fields ) => updateCheckoutProduct( index, fields ) }
						/>
					</div>
				) ) }
			</div>

			<div className="lf-action-row">
				<button className="lf-button" type="button" disabled={ isSaving || ! checkoutDirty } onClick={ saveCheckoutProduct }>
					{ __( 'Save checkout products', 'librefunnels' ) }
				</button>
				<button className="lf-button" type="button" disabled={ isSaving } onClick={ () => setCheckoutProducts( [ ...checkoutProducts, createEmptyCheckoutProduct() ] ) }>
					{ __( 'Add product', 'librefunnels' ) }
				</button>
			</div>
			{ checkoutDirty && <p className="lf-unsaved-note">{ __( 'Save these checkout products before previewing the funnel.', 'librefunnels' ) }</p> }

			<div className="lf-rule-divider" />

			<div>
				<span className="lf-field-heading">
					{ __( 'Order bump', 'librefunnels' ) }
					{ bumpDirty && <em className="lf-dirty-badge">{ __( 'Unsaved', 'librefunnels' ) }</em> }
				</span>
				<p>{ __( 'Add focused checkout bumps that shoppers can accept before payment.', 'librefunnels' ) }</p>
			</div>

			<div className="lf-commerce-list lf-commerce-list--order-bumps">
				{ bumps.map( ( bump, index ) => (
					<div className="lf-commerce-card" key={ bump.id || `bump-${ index }` }>
						<div className="lf-commerce-card__header">
							<strong>{ sprintf( __( 'Order bump %d', 'librefunnels' ), index + 1 ) }</strong>
							<button className="lf-button" type="button" disabled={ isSaving } onClick={ () => removeBump( index ) }>
								{ __( 'Remove', 'librefunnels' ) }
							</button>
						</div>
						<OfferFields offer={ bump } products={ products } onSearchProducts={ onSearchProducts } onChange={ ( fields ) => updateBump( index, fields ) } />
					</div>
				) ) }
			</div>

			<div className="lf-action-row">
				<button className="lf-button" type="button" disabled={ isSaving || ! bumpDirty } onClick={ saveBump }>
					{ __( 'Save order bumps', 'librefunnels' ) }
				</button>
				<button className="lf-button" type="button" disabled={ isSaving } onClick={ () => setBumps( [ ...bumps, createEmptyOffer() ] ) }>
					{ __( 'Add bump', 'librefunnels' ) }
				</button>
			</div>
			{ bumpDirty && <p className="lf-unsaved-note">{ __( 'Save these bumps so they appear in checkout.', 'librefunnels' ) }</p> }
		</div>
	);
}

function PrimaryOfferPanel( { step, products, onSearchProducts, onUpdateStep, isSaving } ) {
	const savedOffer = step.offer?.product_id ? step.offer : createEmptyOffer();
	const [ offer, setOffer ] = useState( savedOffer );
	const offerDirty = ! offersMatch( offer, savedOffer );

	useEffect( () => {
		setOffer( step.offer?.product_id ? step.offer : createEmptyOffer() );
	}, [ step.id ] );

	function clearOffer() {
		const emptyOffer = createEmptyOffer();
		setOffer( emptyOffer );

		if ( savedOffer.product_id ) {
			onUpdateStep( step, { offer: {} } );
		}
	}

	return (
		<div className="lf-panellet">
			<div>
				<span className="lf-field-heading">
					{ __( 'Offer product', 'librefunnels' ) }
					{ offerDirty && <em className="lf-dirty-badge">{ __( 'Unsaved', 'librefunnels' ) }</em> }
				</span>
				<p>{ __( 'This step uses the universal accept-and-confirm flow, so it works with regular WooCommerce gateways.', 'librefunnels' ) }</p>
			</div>

			<OfferFields offer={ offer } products={ products } onSearchProducts={ onSearchProducts } onChange={ setOffer } />
			<div className="lf-action-row">
				<button className="lf-button" type="button" disabled={ isSaving || ! offerDirty || ! offer.product_id } onClick={ () => onUpdateStep( step, { offer } ) }>
					{ __( 'Save offer', 'librefunnels' ) }
				</button>
				<button className="lf-button" type="button" disabled={ isSaving || ( ! offer.product_id && ! savedOffer.product_id ) } onClick={ clearOffer }>
					{ __( 'Clear', 'librefunnels' ) }
				</button>
			</div>
		</div>
	);
}

function CheckoutProductFields( { product, products, onSearchProducts, onChange } ) {
	return (
		<div className="lf-offer-fields">
			<ProductPicker value={ Number( product.product_id || 0 ) } products={ products } onSearch={ onSearchProducts } onChange={ ( productId ) => onChange( { product_id: Number( productId ) } ) } />

			<div className="lf-inline-fields">
				<label className="lf-field">
					<span>{ __( 'Quantity', 'librefunnels' ) }</span>
					<input type="number" min="1" step="1" value={ Number( product.quantity || 1 ) } onChange={ ( event ) => onChange( { quantity: Math.max( 1, Number( event.target.value ) || 1 ) } ) } />
				</label>

				<label className="lf-field">
					<span>{ __( 'Variation ID', 'librefunnels' ) }</span>
					<input type="number" min="0" step="1" value={ Number( product.variation_id || 0 ) } onChange={ ( event ) => onChange( { variation_id: Math.max( 0, Number( event.target.value ) || 0 ) } ) } />
				</label>
			</div>

			<label className="lf-field">
				<span>{ __( 'Variation attributes', 'librefunnels' ) }</span>
				<textarea rows="3" placeholder={ __( 'attribute_pa_color=blue', 'librefunnels' ) } value={ variationToText( product.variation || {} ) } onChange={ ( event ) => onChange( { variation: textToVariation( event.target.value ) } ) } />
			</label>
			<p className="lf-helper-text">{ __( 'Use variation details only when the selected product is a variable product.', 'librefunnels' ) }</p>
		</div>
	);
}

function OfferFields( { offer, products, onSearchProducts, onChange } ) {
	return (
		<div className="lf-offer-fields">
			<ProductPicker value={ Number( offer.product_id || 0 ) } products={ products } onSearch={ onSearchProducts } onChange={ ( productId ) => onChange( { ...offer, product_id: Number( productId ) } ) } />

			<div className="lf-inline-fields">
				<label className="lf-field">
					<span>{ __( 'Quantity', 'librefunnels' ) }</span>
					<input type="number" min="1" step="1" value={ Number( offer.quantity || 1 ) } onChange={ ( event ) => onChange( { ...offer, quantity: Math.max( 1, Number( event.target.value ) || 1 ) } ) } />
				</label>

				<label className="lf-field">
					<span>{ __( 'Variation ID', 'librefunnels' ) }</span>
					<input type="number" min="0" step="1" value={ Number( offer.variation_id || 0 ) } onChange={ ( event ) => onChange( { ...offer, variation_id: Math.max( 0, Number( event.target.value ) || 0 ) } ) } />
				</label>
			</div>

			<label className="lf-field">
				<span>{ __( 'Variation attributes', 'librefunnels' ) }</span>
				<textarea rows="3" placeholder={ __( 'attribute_pa_color=blue', 'librefunnels' ) } value={ variationToText( offer.variation || {} ) } onChange={ ( event ) => onChange( { ...offer, variation: textToVariation( event.target.value ) } ) } />
			</label>

			<label className="lf-field">
				<span>{ __( 'Offer title', 'librefunnels' ) }</span>
				<input value={ offer.title || '' } onChange={ ( event ) => onChange( { ...offer, title: event.target.value } ) } />
			</label>

			<label className="lf-field">
				<span>{ __( 'Short description', 'librefunnels' ) }</span>
				<textarea rows="3" value={ offer.description || '' } onChange={ ( event ) => onChange( { ...offer, description: event.target.value } ) } />
			</label>

			<label className="lf-field">
				<span>{ __( 'Discount', 'librefunnels' ) }</span>
				<select value={ offer.discount_type || 'none' } onChange={ ( event ) => onChange( { ...offer, discount_type: event.target.value } ) }>
					<option value="none">{ __( 'No discount', 'librefunnels' ) }</option>
					<option value="percentage">{ __( 'Percentage', 'librefunnels' ) }</option>
					<option value="fixed">{ __( 'Fixed amount', 'librefunnels' ) }</option>
				</select>
			</label>

			{ offer.discount_type !== 'none' && (
				<label className="lf-field">
					<span>{ __( 'Discount amount', 'librefunnels' ) }</span>
					<input type="number" min="0" step="0.01" value={ Number( offer.discount_amount || 0 ) } onChange={ ( event ) => onChange( { ...offer, discount_amount: Number( event.target.value ) } ) } />
				</label>
			) }

			<label className="lf-toggle">
				<input type="checkbox" checked={ Boolean( offer.enabled ?? true ) } onChange={ ( event ) => onChange( { ...offer, enabled: event.target.checked } ) } />
				<span>{ __( 'Offer enabled', 'librefunnels' ) }</span>
			</label>
		</div>
	);
}

function ProductPicker( { value, products = [], onSearch, onChange, label, placeholder } ) {
	const [ search, setSearch ] = useState( '' );
	const searchTimer = useRef( null );
	const selectedProduct = getProductById( products, value );

	function handleSearch( nextSearch ) {
		setSearch( nextSearch );
		window.clearTimeout( searchTimer.current );
		searchTimer.current = window.setTimeout( () => {
			if ( onSearch ) {
				onSearch( nextSearch );
			}
		}, 250 );
	}

	return (
		<div className="lf-product-picker">
			<label className="lf-field">
				<span>{ label || __( 'Find product', 'librefunnels' ) }</span>
				<input value={ search } placeholder={ placeholder || __( 'Search products by name or SKU...', 'librefunnels' ) } onChange={ ( event ) => handleSearch( event.target.value ) } />
			</label>

			<select value={ Number( value || 0 ) } onChange={ ( event ) => onChange( Number( event.target.value ) ) }>
				<option value="0">{ __( 'Choose a product', 'librefunnels' ) }</option>
				{ products.map( ( product ) => (
					<option key={ product.id } value={ product.id }>
						{ product.name } { product.sku ? `(${ product.sku })` : '' }
					</option>
				) ) }
			</select>

			{ selectedProduct && (
				<div className="lf-product-chip">
					{ selectedProduct.imageUrl && <img src={ selectedProduct.imageUrl } alt="" />}
					<span>
						<strong>{ selectedProduct.name }</strong>
						<small>
							{ selectedProduct.priceHtml || selectedProduct.type }
							{ ! selectedProduct.purchasable ? ` · ${ __( 'Not purchasable', 'librefunnels' ) }` : '' }
						</small>
					</span>
				</div>
			) }

			{ products.length === 0 && <p className="lf-product-hint">{ __( 'No products found yet. Try another search, or create WooCommerce products first.', 'librefunnels' ) }</p> }
		</div>
	);
}

function PagePicker( { step, pages, onSearch, onAssign, onCreate, isSaving } ) {
	const [ search, setSearch ] = useState( '' );
	const [ newTitle, setNewTitle ] = useState( step?.title ? `${ step.title } page` : __( 'New funnel page', 'librefunnels' ) );
	const searchTimer = useRef( null );
	const selectedPage = pages.find( ( page ) => Number( page.id ) === Number( step.pageId || 0 ) );
	const pageEditUrl = step.pageEditUrl || selectedPage?.editUrl || '';
	const pageUrl = step.pageUrl || selectedPage?.url || '';
	const pageStatus = step.pageStatus || selectedPage?.status || '';

	useEffect( () => {
		setNewTitle( step?.title ? `${ step.title } page` : __( 'New funnel page', 'librefunnels' ) );
		setSearch( '' );
	}, [ step?.id ] );

	function handleSearch( value ) {
		setSearch( value );
		window.clearTimeout( searchTimer.current );
		searchTimer.current = window.setTimeout( () => onSearch( value ), 250 );
	}

	return (
		<div className="lf-panellet">
			<div>
				<span className="lf-field-heading">{ __( 'Assigned page', 'librefunnels' ) }</span>
				{ step.pageTitle ? (
					<div className="lf-page-summary">
						<strong>{ step.pageTitle }</strong>
						<span className={ `lf-page-status is-${ pageStatus || 'unknown' }` }>{ getPageStatusLabel( pageStatus ) }</span>
					</div>
				) : (
					<p>{ __( 'No page assigned yet.', 'librefunnels' ) }</p>
				) }
				<p className="lf-helper-text">
					{ __( 'LibreFunnels creates a normal WordPress page with the funnel block inside. Open it in the block editor, Elementor, Bricks, Divi, Beaver Builder, or the page builder your site uses.', 'librefunnels' ) }
				</p>
				{ step.pageTitle && pageStatus !== 'publish' && (
					<p className="lf-helper-text">
						{ __( 'Publish the page from your editor when the design is ready for shoppers.', 'librefunnels' ) }
					</p>
				) }
			</div>

			{ pageEditUrl && (
				<div className="lf-page-actions">
					<a className="lf-button lf-button--primary" href={ pageEditUrl }>
						{ __( 'Edit page design', 'librefunnels' ) }
					</a>
					{ pageUrl && (
						<a className="lf-button" href={ pageUrl } target="_blank" rel="noreferrer">
							{ pageStatus === 'publish' ? __( 'View page', 'librefunnels' ) : __( 'Preview page', 'librefunnels' ) }
						</a>
					) }
				</div>
			) }

			<label className="lf-field">
				<span>{ __( 'Find page', 'librefunnels' ) }</span>
				<input value={ search } placeholder={ __( 'Search pages...', 'librefunnels' ) } onChange={ ( event ) => handleSearch( event.target.value ) } />
			</label>

			<select value={ Number( step.pageId || 0 ) } onChange={ ( event ) => onAssign( event.target.value ) } disabled={ isSaving }>
				<option value="0">{ __( 'Choose a page', 'librefunnels' ) }</option>
				{ pages.map( ( page ) => (
					<option key={ page.id } value={ page.id }>
						{ page.title } ({ page.status })
					</option>
				) ) }
			</select>

			<label className="lf-field">
				<span>{ __( 'Create page', 'librefunnels' ) }</span>
				<input value={ newTitle } onChange={ ( event ) => setNewTitle( event.target.value ) } />
			</label>
			<button className="lf-button" type="button" disabled={ isSaving || ! newTitle.trim() } onClick={ () => onCreate( newTitle ) }>
				{ __( 'Create and assign', 'librefunnels' ) }
			</button>
		</div>
	);
}

function EdgeInspector( { edge, graph, steps, products, onSearchProducts, onSaveGraph, onSelect, isSaving } ) {
	const [ route, setRoute ] = useState( edge?.route || 'next' );
	const [ source, setSource ] = useState( edge?.source || '' );
	const [ target, setTarget ] = useState( edge?.target || '' );
	const [ rule, setRule ] = useState( edge?.rule?.type ? edge.rule : { type: 'always' } );
	const warnings = edge ? getEdgeWarnings( edge, graph.nodes ) : [];

	useEffect( () => {
		setRoute( edge?.route || 'next' );
		setSource( edge?.source || '' );
		setTarget( edge?.target || '' );
		setRule( edge?.rule?.type ? edge.rule : { type: 'always' } );
	}, [ edge?.id ] );

	if ( ! edge ) {
		return (
			<aside className="lf-inspector">
				<p className="lf-label">{ __( 'Route inspector', 'librefunnels' ) }</p>
				<h2>{ __( 'Missing route', 'librefunnels' ) }</h2>
			</aside>
		);
	}

	function getNodeLabel( node ) {
		const step = getStepById( steps, node.stepId );
		const title = step ? getPostTitle( step, __( 'Untitled step', 'librefunnels' ) ) : __( 'Missing step', 'librefunnels' );

		return `${ title } - ${ stepTypes[ step?.type || node.type ] || node.type }`;
	}

	async function saveEdge() {
		await onSaveGraph( {
			...graph,
			edges: graph.edges.map( ( item ) =>
				item.id === edge.id
					? {
							...item,
							source,
							target,
							route,
							rule: route === 'conditional' ? rule : {},
					  }
					: item
			),
		} );
		onSelect( { type: 'edge', id: edge.id } );
	}

	async function deleteEdge() {
		await onSaveGraph( {
			...graph,
			edges: graph.edges.filter( ( item ) => item.id !== edge.id ),
		} );
		onSelect( { type: 'funnel' } );
	}

	return (
		<aside className="lf-inspector">
			<p className="lf-label">{ __( 'Route inspector', 'librefunnels' ) }</p>
			<h2>{ routes[ edge.route ] || edge.route }</h2>
			<Warnings warnings={ warnings } />

			<label className="lf-field">
				<span>{ __( 'From step', 'librefunnels' ) }</span>
				<select value={ source } onChange={ ( event ) => setSource( event.target.value ) }>
					{ graph.nodes.map( ( node ) => (
						<option key={ node.id } value={ node.id }>
							{ getNodeLabel( node ) }
						</option>
					) ) }
				</select>
			</label>

			<label className="lf-field">
				<span>{ __( 'To step', 'librefunnels' ) }</span>
				<select value={ target } onChange={ ( event ) => setTarget( event.target.value ) }>
					{ graph.nodes.map( ( node ) => (
						<option key={ node.id } value={ node.id }>
							{ getNodeLabel( node ) }
						</option>
					) ) }
				</select>
			</label>

			<label className="lf-field">
				<span>{ __( 'Route label', 'librefunnels' ) }</span>
				<select value={ route } onChange={ ( event ) => setRoute( event.target.value ) }>
					{ Object.entries( routes ).map( ( [ value, label ] ) => (
						<option key={ value } value={ value }>
							{ label }
						</option>
					) ) }
				</select>
			</label>

			{ route === 'conditional' && <RuleBuilder rule={ rule } products={ products } onSearchProducts={ onSearchProducts } onChange={ setRule } /> }

			<div className="lf-action-row">
				<button className="lf-button lf-button--primary" type="button" disabled={ isSaving || ! source || ! target } onClick={ saveEdge }>
					{ __( 'Save route', 'librefunnels' ) }
				</button>
				<button className="lf-button lf-button--danger" type="button" disabled={ isSaving } onClick={ deleteEdge }>
					{ __( 'Delete route', 'librefunnels' ) }
				</button>
			</div>
		</aside>
	);
}

function RuleBuilder( { rule, products, onSearchProducts, onChange } ) {
	const type = rule?.type || 'always';
	const preview = getRulePreview( rule, products );

	return (
		<div className="lf-panellet">
			<div>
				<span className="lf-field-heading">{ __( 'Condition', 'librefunnels' ) }</span>
				<p>{ __( 'Choose when this route should be used. LibreFunnels will keep the fallback route available for everyone else.', 'librefunnels' ) }</p>
			</div>

			<label className="lf-field">
				<span>{ __( 'Rule', 'librefunnels' ) }</span>
				<select value={ type } onChange={ ( event ) => onChange( createRuleFromType( event.target.value, rule ) ) }>
					{ ruleGroups.map( ( group ) => (
						<optgroup key={ group.label } label={ group.label }>
							{ group.rules.map( ( value ) => (
								<option key={ value } value={ value }>
									{ ruleLabels[ value ] || value }
								</option>
							) ) }
						</optgroup>
					) ) }
				</select>
			</label>

			<div className="lf-rule-preview" aria-live="polite">
				<span>{ __( 'Preview', 'librefunnels' ) }</span>
				<strong>{ preview }</strong>
			</div>

			{ ( type === 'cart_contains_product' || type === 'order_contains_product' ) && (
				<ProductPicker value={ Number( rule.product_id || 0 ) } products={ products } onSearch={ onSearchProducts } onChange={ ( productId ) => onChange( { ...rule, product_id: Number( productId ) } ) } />
			) }

			{ ( type === 'cart_subtotal_gte' || type === 'cart_subtotal_lte' || type === 'order_total_gte' || type === 'order_total_lte' ) && (
				<label className="lf-field">
					<span>{ type.startsWith( 'order_' ) ? __( 'Order total', 'librefunnels' ) : __( 'Cart subtotal', 'librefunnels' ) }</span>
					<input type="number" min="0" step="0.01" value={ Number( rule.amount || 0 ) } onChange={ ( event ) => onChange( { ...rule, amount: Number( event.target.value ) } ) } />
				</label>
			) }
		</div>
	);
}

function Warnings( { warnings } ) {
	if ( ! warnings || warnings.length === 0 ) {
		return <p className="lf-good">{ __( 'No visible issues.', 'librefunnels' ) }</p>;
	}

	return (
		<div className="lf-warnings">
			{ warnings.map( ( warning ) => (
				<p key={ warning }>{ warning }</p>
			) ) }
		</div>
	);
}

const root = document.getElementById( settings.rootId || 'librefunnels-admin-app' );

if ( root ) {
	createRoot( root ).render( <App /> );
}
