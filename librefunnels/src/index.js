import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { render, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import './style.css';

const settings = window.libreFunnelsAdmin || {};

if ( settings.nonce && apiFetch.createNonceMiddleware ) {
	apiFetch.use( apiFetch.createNonceMiddleware( settings.nonce ) );
}

const stepTypes = settings.stepTypes || {};
const routes = settings.routes || {};
const canvasPath = settings.rest?.canvas || '/librefunnels/v1/canvas';
const pagesPath = settings.rest?.pages || '/librefunnels/v1/canvas/pages';

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

function App() {
	const [ funnels, setFunnels ] = useState( [] );
	const [ steps, setSteps ] = useState( [] );
	const [ pages, setPages ] = useState( [] );
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
		const candidateId = Number( workspace.selectedFunnelId || preferredFunnelId );

		setFunnels( nextFunnels );
		setSteps( nextSteps );
		setPages( nextPages );

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

	async function createPageForStep( step, title ) {
		const payload = await runSave(
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

		const page = payload?.page;

		if ( page && ! pages.some( ( item ) => Number( item.id ) === Number( page.id ) ) ) {
			setPages( ( current ) => [ page, ...current ] );
		}
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
					warnings={ getValidationSummary() }
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
				/>
			</main>

			<Inspector
				selectedItem={ selectedItem }
				selectedFunnel={ selectedFunnel }
				graph={ graph }
				steps={ steps }
				pages={ pages }
				funnelSteps={ funnelSteps }
				onSelect={ setSelectedItem }
				onSaveGraph={ saveGraph }
				onUpdateStep={ updateStep }
				onDeleteStep={ deleteStep }
				onSetStartStep={ setStartStep }
				onSearchPages={ searchPages }
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

function Header( { selectedFunnel, warnings, isSaving, notice, onCreateStep, onCreateEdge } ) {
	return (
		<header className="lf-canvas-header">
			<div>
				<p className="lf-label">{ __( 'Visual funnel map', 'librefunnels' ) }</p>
				<h1>{ selectedFunnel ? getPostTitle( selectedFunnel, __( 'Untitled funnel', 'librefunnels' ) ) : __( 'Create your first funnel', 'librefunnels' ) }</h1>
			</div>

			<div className="lf-header-actions">
				{ notice && <span className="lf-save-state">{ notice }</span> }
				<span className={ `lf-health ${ warnings.length > 0 ? 'has-warnings' : 'is-clear' }` }>
					{ warnings.length > 0
						? sprintf( __( '%d issue(s)', 'librefunnels' ), warnings.length )
						: __( 'Ready', 'librefunnels' ) }
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

function Canvas( { isLoading, graph, steps, selectedFunnel, selectedItem, onSelect, onStartDrag } ) {
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
			</div>
		);
	}

	if ( nodes.length === 0 ) {
		return (
			<div className="lf-canvas lf-canvas--empty">
				<h2>{ __( 'This funnel is ready for its first step.', 'librefunnels' ) }</h2>
				<p>{ __( 'Add a checkout, offer, or thank-you step. Broken routes stay visible while you build.', 'librefunnels' ) }</p>
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

					return (
						<g
							key={ edge.id }
							className={ `lf-edge lf-edge--${ routeClass } ${ selectedItem.type === 'edge' && selectedItem.id === edge.id ? 'is-selected' : '' } ${ warnings.length ? 'has-warning' : '' }` }
							onClick={ () => onSelect( { type: 'edge', id: edge.id } ) }
						>
							<path d={ `M ${ startX } ${ startY } C ${ midX } ${ startY }, ${ midX } ${ endY }, ${ endX } ${ endY }` } />
							<text x={ midX } y={ ( startY + endY ) / 2 - 8 }>{ routes[ edge.route ] || edge.route }</text>
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
							{ isStart ? __( 'Start step', 'librefunnels' ) : step?.pageTitle || __( 'No page assigned', 'librefunnels' ) }
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
	warnings,
	onUpdateStep,
	onDeleteStep,
	onSetStartStep,
	onSearchPages,
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

function PagePicker( { step, pages, onSearch, onAssign, onCreate, isSaving } ) {
	const [ search, setSearch ] = useState( '' );
	const [ newTitle, setNewTitle ] = useState( step?.title ? `${ step.title } page` : __( 'New funnel page', 'librefunnels' ) );
	const searchTimer = useRef( null );

	function handleSearch( value ) {
		setSearch( value );
		window.clearTimeout( searchTimer.current );
		searchTimer.current = window.setTimeout( () => onSearch( value ), 250 );
	}

	return (
		<div className="lf-panellet">
			<div>
				<span className="lf-field-heading">{ __( 'Assigned page', 'librefunnels' ) }</span>
				<p>{ step.pageTitle || __( 'No page assigned yet.', 'librefunnels' ) }</p>
			</div>

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

function EdgeInspector( { edge, graph, steps, onSaveGraph, onSelect, isSaving } ) {
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

			{ route === 'conditional' && <RuleBuilder rule={ rule } onChange={ setRule } /> }

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

function RuleBuilder( { rule, onChange } ) {
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
				<label className="lf-field">
					<span>{ __( 'Product ID', 'librefunnels' ) }</span>
					<input type="number" min="1" value={ Number( rule.product_id || 0 ) } onChange={ ( event ) => onChange( { ...rule, product_id: Number( event.target.value ) } ) } />
				</label>
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
	render( <App />, root );
}
