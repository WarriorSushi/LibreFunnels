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
	customer_logged_in: __( 'Customer is logged in', 'librefunnels' ),
};

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

function getStepById( steps, stepId ) {
	return steps.find( ( step ) => Number( step.id ) === Number( stepId ) );
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
	const hasValidStartStep = startStepId > 0 && funnelSteps.some( ( step ) => Number( step.id ) === startStepId );
	const hasCheckoutProducts = Boolean( checkoutStep?.checkoutProducts?.length );
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
			detail: hasCheckoutProducts
				? __( 'Checkout products are selected.', 'librefunnels' )
				: __( 'Choose at least one checkout product.', 'librefunnels' ),
			done: hasCheckoutProducts,
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
	if ( type === 'cart_contains_product' ) {
		return {
			type,
			product_id: Number( previous.product_id || 0 ),
		};
	}

	if ( type === 'cart_subtotal_gte' || type === 'cart_subtotal_lte' ) {
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
	const [ selectedFunnelId, setSelectedFunnelId ] = useState( 0 );
	const [ selectedItem, setSelectedItem ] = useState( { type: 'funnel' } );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ notice, setNotice ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ dragging, setDragging ] = useState( null );

	const selectedFunnel = funnels.find( ( funnel ) => Number( funnel.id ) === Number( selectedFunnelId ) );
	const graph = selectedFunnel ? getGraph( selectedFunnel ) : emptyGraph;
	const funnelSteps = useMemo(
		() => steps.filter( ( step ) => Number( step.funnelId ) === Number( selectedFunnelId ) ),
		[ steps, selectedFunnelId ]
	);

	useEffect( () => {
		loadWorkspace();
	}, [] );

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

	function applyWorkspace( payload, preferredFunnelId = selectedFunnelId ) {
		const workspace = payload?.workspace || payload || {};
		const nextFunnels = Array.isArray( workspace.funnels ) ? workspace.funnels : [];
		const nextSteps = Array.isArray( workspace.steps ) ? workspace.steps : [];
		const nextPages = Array.isArray( workspace.pages ) ? workspace.pages : pages;
		const nextProducts = Array.isArray( workspace.products ) ? workspace.products : products;
		const candidateId = Number( workspace.selectedFunnelId || preferredFunnelId );

		setFunnels( nextFunnels );
		setSteps( nextSteps );
		setPages( nextPages );
		setProducts( nextProducts );

		if ( nextFunnels.length > 0 ) {
			const stillExists = nextFunnels.some( ( funnel ) => Number( funnel.id ) === Number( candidateId ) );
			setSelectedFunnelId( stillExists ? candidateId : nextFunnels[ 0 ].id );
		} else {
			setSelectedFunnelId( 0 );
		}
	}

	async function loadWorkspace( preferredFunnelId = selectedFunnelId ) {
		setIsLoading( true );
		setError( '' );

		try {
			const workspace = await apiFetch( { path: canvasPath } );
			applyWorkspace( workspace, preferredFunnelId );
			setSelectedItem( { type: 'funnel' } );
		} catch ( nextError ) {
			setError( nextError.message || __( 'LibreFunnels could not load the workspace.', 'librefunnels' ) );
		} finally {
			setIsLoading( false );
		}
	}

	async function runSave( action, successMessage ) {
		setIsSaving( true );
		setError( '' );
		setNotice( __( 'Saving...', 'librefunnels' ) );

		try {
			const payload = await action();
			applyWorkspace( payload );
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

		if ( payload?.selectedFunnelId ) {
			setSelectedFunnelId( payload.selectedFunnelId );
			setSelectedItem( { type: 'funnel' } );
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

	const validationSummary = getValidationSummary();
	const setupGuide = getSetupGuide( selectedFunnel, graph, funnelSteps, validationSummary );

	return (
		<div className="wrap librefunnels-canvas-app">
			<Sidebar
				funnels={ funnels }
				selectedFunnelId={ selectedFunnelId }
				onSelect={ ( id ) => {
					setSelectedFunnelId( id );
					setSelectedItem( { type: 'funnel' } );
				} }
				onCreate={ createFunnel }
				isSaving={ isSaving }
			/>

			<main className="lf-canvas-shell" aria-busy={ isLoading || isSaving }>
				<Header
					selectedFunnel={ selectedFunnel }
					warnings={ validationSummary }
					setupGuide={ setupGuide }
					isSaving={ isSaving }
					notice={ notice }
					onCreateStep={ createStep }
					onCreateEdge={ createEdge }
				/>

				{ error && <div className="lf-alert">{ error }</div> }

				<Canvas
					isLoading={ isLoading }
					graph={ graph }
					steps={ steps }
					selectedFunnel={ selectedFunnel }
					selectedItem={ selectedItem }
					onSelect={ setSelectedItem }
					onStartDrag={ startNodeDrag }
					onCreateFunnel={ createFunnel }
					onCreateStep={ createStep }
					onCreateStarterPath={ createStarterPath }
					isSaving={ isSaving }
				/>
			</main>

			<Inspector
				selectedItem={ selectedItem }
				selectedFunnel={ selectedFunnel }
				graph={ graph }
				steps={ steps }
				pages={ pages }
				products={ products }
				funnelSteps={ funnelSteps }
				onSelect={ setSelectedItem }
				onSaveGraph={ saveGraph }
				onUpdateStep={ updateStep }
				onDeleteStep={ deleteStep }
				onSetStartStep={ setStartStep }
				onSearchPages={ searchPages }
				onSearchProducts={ searchProducts }
				onCreatePageForStep={ createPageForStep }
				isSaving={ isSaving }
			/>
		</div>
	);
}

function Sidebar( { funnels, selectedFunnelId, onSelect, onCreate, isSaving } ) {
	return (
		<aside className="lf-sidebar">
			<div className="lf-brand">
				<span className="lf-brand__mark">LF</span>
				<div>
					<p>{ __( 'LibreFunnels', 'librefunnels' ) }</p>
					<strong>{ __( 'Canvas Builder', 'librefunnels' ) }</strong>
				</div>
			</div>

			<button className="lf-button lf-button--primary" type="button" onClick={ onCreate } disabled={ isSaving }>
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

function Header( { selectedFunnel, warnings, setupGuide, isSaving, notice, onCreateStep, onCreateEdge } ) {
	const healthText =
		warnings.length > 0
			? sprintf( __( '%d issue(s)', 'librefunnels' ), warnings.length )
			: setupGuide?.status === 'ready'
				? __( 'Ready', 'librefunnels' )
				: __( 'In progress', 'librefunnels' );

	return (
		<header className="lf-canvas-header">
			<div className="lf-canvas-header__title">
				<p className="lf-label">{ __( 'Visual funnel map', 'librefunnels' ) }</p>
				<h1>{ selectedFunnel ? getPostTitle( selectedFunnel, __( 'Untitled funnel', 'librefunnels' ) ) : __( 'Create your first funnel', 'librefunnels' ) }</h1>
				{ setupGuide && (
					<p className="lf-next-step">
						<strong>{ setupGuide.label }</strong>
						<span>{ setupGuide.message }</span>
					</p>
				) }
				<SetupProgress setupGuide={ setupGuide } />
			</div>

			<div className="lf-header-actions">
				{ notice && <span className="lf-save-state">{ notice }</span> }
				<span className={ `lf-health ${ warnings.length > 0 ? 'has-warnings' : 'is-clear' }` }>
					{ healthText }
				</span>
				<div className="lf-add-step-menu">
					<button className="lf-button" type="button" onClick={ () => onCreateStep( 'checkout' ) } disabled={ ! selectedFunnel || isSaving }>
						{ __( 'Add checkout', 'librefunnels' ) }
					</button>
					<button className="lf-button" type="button" onClick={ () => onCreateStep( 'upsell' ) } disabled={ ! selectedFunnel || isSaving }>
						{ __( 'Add offer', 'librefunnels' ) }
					</button>
				</div>
				<button className="lf-button" type="button" onClick={ onCreateEdge } disabled={ ! selectedFunnel || isSaving }>
					{ __( 'Connect route', 'librefunnels' ) }
				</button>
			</div>
		</header>
	);
}

function SetupProgress( { setupGuide } ) {
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
		</div>
	);
}

function Canvas( { isLoading, graph, steps, selectedFunnel, selectedItem, onSelect, onStartDrag, onCreateFunnel, onCreateStep, onCreateStarterPath, isSaving } ) {
	const nodes = graph.nodes.map( ( node, index ) => ( {
		...node,
		position: normalizeNodePosition( node, index ),
	} ) );
	const nodeMap = new Map( nodes.map( ( node ) => [ node.id, node ] ) );
	const canvasWidth = Math.max( 960, ...nodes.map( ( node ) => node.position.x + 250 ), 960 );
	const canvasHeight = Math.max( 580, ...nodes.map( ( node ) => node.position.y + 160 ), 580 );

	if ( isLoading ) {
		return <div className="lf-canvas lf-canvas--empty">{ __( 'Loading your funnel workspace...', 'librefunnels' ) }</div>;
	}

	if ( ! selectedFunnel ) {
		return (
			<div className="lf-canvas lf-canvas--empty">
				<h2>{ __( 'Start with one focused checkout journey.', 'librefunnels' ) }</h2>
				<p>{ __( 'Create a funnel, add steps, then connect the route shoppers should follow.', 'librefunnels' ) }</p>
				<button className="lf-button lf-button--primary" type="button" onClick={ onCreateFunnel } disabled={ isSaving }>
					{ __( 'Create funnel', 'librefunnels' ) }
				</button>
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
		<div className="lf-canvas" style={ { '--lf-canvas-width': `${ canvasWidth }px`, '--lf-canvas-height': `${ canvasHeight }px` } }>
			<svg className="lf-edges" viewBox={ `0 0 ${ canvasWidth } ${ canvasHeight }` } role="img" aria-label={ __( 'Funnel route map', 'librefunnels' ) }>
				{ graph.edges.map( ( edge ) => {
					const source = nodeMap.get( edge.source );
					const target = nodeMap.get( edge.target );
					const warnings = getEdgeWarnings( edge, nodes );

					if ( ! source || ! target ) {
						return null;
					}

					const startX = source.position.x + 220;
					const startY = source.position.y + 48;
					const endX = target.position.x;
					const endY = target.position.y + 48;
					const midX = startX + ( endX - startX ) / 2;
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
							<path d={ `M ${ startX } ${ startY } C ${ midX } ${ startY }, ${ midX } ${ endY }, ${ endX } ${ endY }` } />
							<text x={ midX } y={ ( startY + endY ) / 2 - 8 }>{ routeLabel }</text>
						</g>
					);
				} ) }
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

				return (
					<button
						key={ node.id }
						className={ `lf-node ${ selectedItem.type === 'node' && selectedItem.id === node.id ? 'is-selected' : '' } ${ warnings.length ? 'has-warning' : '' }` }
						type="button"
						style={ { left: `${ node.position.x }px`, top: `${ node.position.y }px` } }
						onClick={ () => onSelect( { type: 'node', id: node.id } ) }
						onPointerDown={ ( event ) => onStartDrag( event, node ) }
					>
						<span className="lf-node__type">{ stepTypes[ type ] || type }</span>
						<strong>{ step ? getPostTitle( step, __( 'Untitled step', 'librefunnels' ) ) : __( 'Missing step', 'librefunnels' ) }</strong>
						<span className="lf-node__meta">
							{ getNodePageMeta( step, isStart ) }
							{ warnings.length > 0 ? ` · ${ __( 'Needs attention', 'librefunnels' ) }` : '' }
						</span>
					</button>
				);
			} ) }
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

	useEffect( () => {
		setTitle( step ? getPostTitle( step, '' ) : '' );
		setStepType( step?.type || node?.type || 'custom' );
	}, [ step?.id, node?.id ] );

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

			<PagePicker
				step={ step }
				pages={ pages }
				onSearch={ onSearchPages }
				onAssign={ ( pageId ) => onUpdateStep( step, { page_id: Number( pageId ) } ) }
				onCreate={ ( pageTitle ) => onCreatePageForStep( step, pageTitle ) }
				isSaving={ isSaving }
			/>

			<CommercePanel step={ step } products={ products } onSearchProducts={ onSearchProducts } onUpdateStep={ onUpdateStep } isSaving={ isSaving } />

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
		</aside>
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

function ProductPicker( { value, products, onSearch, onChange } ) {
	const [ search, setSearch ] = useState( '' );
	const searchTimer = useRef( null );
	const selectedProduct = getProductById( products, value );

	function handleSearch( nextSearch ) {
		setSearch( nextSearch );
		window.clearTimeout( searchTimer.current );
		searchTimer.current = window.setTimeout( () => onSearch( nextSearch ), 250 );
	}

	return (
		<div className="lf-product-picker">
			<label className="lf-field">
				<span>{ __( 'Find product', 'librefunnels' ) }</span>
				<input value={ search } placeholder={ __( 'Search products by name or SKU...', 'librefunnels' ) } onChange={ ( event ) => handleSearch( event.target.value ) } />
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

	return (
		<div className="lf-panellet">
			<div>
				<span className="lf-field-heading">{ __( 'Condition', 'librefunnels' ) }</span>
				<p>{ __( 'Choose when this route should be used. LibreFunnels will keep the fallback route available for everyone else.', 'librefunnels' ) }</p>
			</div>

			<label className="lf-field">
				<span>{ __( 'Rule', 'librefunnels' ) }</span>
				<select value={ type } onChange={ ( event ) => onChange( createRuleFromType( event.target.value, rule ) ) }>
					{ Object.entries( ruleLabels ).map( ( [ value, label ] ) => (
						<option key={ value } value={ value }>
							{ label }
						</option>
					) ) }
				</select>
			</label>

			{ type === 'cart_contains_product' && (
				<ProductPicker value={ Number( rule.product_id || 0 ) } products={ products } onSearch={ onSearchProducts } onChange={ ( productId ) => onChange( { ...rule, product_id: Number( productId ) } ) } />
			) }

			{ ( type === 'cart_subtotal_gte' || type === 'cart_subtotal_lte' ) && (
				<label className="lf-field">
					<span>{ __( 'Cart subtotal', 'librefunnels' ) }</span>
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
