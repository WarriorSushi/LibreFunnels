import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { render, useEffect, useMemo, useState } from '@wordpress/element';
import './style.css';

const settings = window.libreFunnelsAdmin || {};

if ( settings.nonce && apiFetch.createNonceMiddleware ) {
	apiFetch.use( apiFetch.createNonceMiddleware( settings.nonce ) );
}

const metaKeys = settings.metaKeys || {};
const stepTypes = settings.stepTypes || {};
const routes = settings.routes || {};

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

function getPostTitle( post, fallback ) {
	if ( post?.title?.raw ) {
		return post.title.raw;
	}

	if ( post?.title?.rendered ) {
		return post.title.rendered.replace( /<[^>]+>/g, '' );
	}

	return fallback;
}

function getMeta( post, key, fallback = '' ) {
	return post?.meta && Object.prototype.hasOwnProperty.call( post.meta, key ) ? post.meta[ key ] : fallback;
}

function getGraph( funnel ) {
	const graph = getMeta( funnel, metaKeys.graph, emptyGraph );

	if ( ! graph || typeof graph !== 'object' ) {
		return emptyGraph;
	}

	return {
		version: 1,
		nodes: Array.isArray( graph.nodes ) ? graph.nodes : [],
		edges: Array.isArray( graph.edges ) ? graph.edges : [],
	};
}

function updatePostMeta( post, meta ) {
	return {
		meta: {
			...( post?.meta || {} ),
			...meta,
		},
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

	if ( Number( getMeta( step, metaKeys.stepFunnelId, 0 ) ) !== Number( selectedFunnel.id ) ) {
		warnings.push( __( 'This step belongs to another funnel.', 'librefunnels' ) );
	}

	if ( Number( getMeta( step, metaKeys.stepPageId, 0 ) ) < 1 ) {
		warnings.push( __( 'Assign a page before sending shoppers here.', 'librefunnels' ) );
	}

	return warnings;
}

function getEdgeWarnings( edge, nodes ) {
	const warnings = [];

	if ( ! nodes.some( ( node ) => node.id === edge.source ) ) {
		warnings.push( __( 'Source node is missing.', 'librefunnels' ) );
	}

	if ( ! nodes.some( ( node ) => node.id === edge.target ) ) {
		warnings.push( __( 'Target node is missing.', 'librefunnels' ) );
	}

	if ( edge.route === 'conditional' && ! edge.rule?.type ) {
		warnings.push( __( 'Conditional routes need a rule.', 'librefunnels' ) );
	}

	return warnings;
}

function App() {
	const [ funnels, setFunnels ] = useState( [] );
	const [ steps, setSteps ] = useState( [] );
	const [ selectedFunnelId, setSelectedFunnelId ] = useState( 0 );
	const [ selectedItem, setSelectedItem ] = useState( { type: 'funnel' } );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState( '' );

	const selectedFunnel = funnels.find( ( funnel ) => Number( funnel.id ) === Number( selectedFunnelId ) );
	const graph = selectedFunnel ? getGraph( selectedFunnel ) : emptyGraph;
	const funnelSteps = useMemo(
		() => steps.filter( ( step ) => Number( getMeta( step, metaKeys.stepFunnelId, 0 ) ) === Number( selectedFunnelId ) ),
		[ steps, selectedFunnelId ]
	);

	useEffect( () => {
		loadWorkspace();
	}, [] );

	async function loadWorkspace( preferredFunnelId = selectedFunnelId ) {
		setIsLoading( true );
		setError( '' );

		try {
			const [ nextFunnels, nextSteps ] = await Promise.all( [
				apiFetch( { path: `${ settings.rest.funnels }?per_page=100&context=edit` } ),
				apiFetch( { path: `${ settings.rest.steps }?per_page=100&context=edit` } ),
			] );
			const safeFunnels = Array.isArray( nextFunnels ) ? nextFunnels : [];
			const safeSteps = Array.isArray( nextSteps ) ? nextSteps : [];

			setFunnels( safeFunnels );
			setSteps( safeSteps );

			if ( safeFunnels.length > 0 ) {
				const stillExists = safeFunnels.some( ( funnel ) => Number( funnel.id ) === Number( preferredFunnelId ) );
				const nextSelectedId = stillExists ? preferredFunnelId : safeFunnels[ 0 ].id;
				setSelectedFunnelId( nextSelectedId );
				setSelectedItem( { type: 'funnel' } );
			}
		} catch ( nextError ) {
			setError( nextError.message || __( 'LibreFunnels could not load the workspace.', 'librefunnels' ) );
		} finally {
			setIsLoading( false );
		}
	}

	async function createFunnel() {
		setIsSaving( true );
		setError( '' );

		try {
			const funnel = await apiFetch( {
				path: settings.rest.funnels,
				method: 'POST',
				data: {
					title: __( 'New checkout funnel', 'librefunnels' ),
					status: 'draft',
					meta: {
						[ metaKeys.graph ]: emptyGraph,
						[ metaKeys.startStepId ]: 0,
					},
				},
			} );

			await loadWorkspace( funnel.id );
		} catch ( nextError ) {
			setError( nextError.message || __( 'LibreFunnels could not create the funnel.', 'librefunnels' ) );
		} finally {
			setIsSaving( false );
		}
	}

	async function saveFunnelMeta( funnel, meta ) {
		const updated = await apiFetch( {
			path: `${ settings.rest.funnels }/${ funnel.id }`,
			method: 'POST',
			data: updatePostMeta( funnel, meta ),
		} );

		setFunnels( ( current ) => current.map( ( item ) => ( item.id === updated.id ? updated : item ) ) );
		return updated;
	}

	async function createStep( type = 'checkout' ) {
		if ( ! selectedFunnel ) {
			return;
		}

		setIsSaving( true );
		setError( '' );

		try {
			const order = funnelSteps.length + 1;
			const step = await apiFetch( {
				path: settings.rest.steps,
				method: 'POST',
				data: {
					title: stepTypes[ type ] || __( 'New step', 'librefunnels' ),
					status: 'draft',
					meta: {
						[ metaKeys.stepFunnelId ]: selectedFunnel.id,
						[ metaKeys.stepType ]: type,
						[ metaKeys.stepOrder ]: order,
						[ metaKeys.stepPageId ]: 0,
					},
				},
			} );
			const nextGraph = getGraph( selectedFunnel );
			const nextNode = {
				id: `node-${ step.id }`,
				stepId: step.id,
				type,
				position: {
					x: 120 + nextGraph.nodes.length * 260,
					y: 140 + ( nextGraph.nodes.length % 2 ) * 150,
				},
			};
			const nextMeta = {
				[ metaKeys.graph ]: {
					...nextGraph,
					nodes: [ ...nextGraph.nodes, nextNode ],
				},
			};

			if ( Number( getMeta( selectedFunnel, metaKeys.startStepId, 0 ) ) < 1 ) {
				nextMeta[ metaKeys.startStepId ] = step.id;
			}

			setSteps( ( current ) => [ ...current, step ] );
			await saveFunnelMeta( selectedFunnel, nextMeta );
			setSelectedItem( { type: 'node', id: nextNode.id } );
		} catch ( nextError ) {
			setError( nextError.message || __( 'LibreFunnels could not create the step.', 'librefunnels' ) );
		} finally {
			setIsSaving( false );
		}
	}

	async function updateStep( step, fields ) {
		setIsSaving( true );
		setError( '' );

		try {
			const updated = await apiFetch( {
				path: `${ settings.rest.steps }/${ step.id }`,
				method: 'POST',
				data: fields,
			} );

			setSteps( ( current ) => current.map( ( item ) => ( item.id === updated.id ? updated : item ) ) );
		} catch ( nextError ) {
			setError( nextError.message || __( 'LibreFunnels could not update the step.', 'librefunnels' ) );
		} finally {
			setIsSaving( false );
		}
	}

	async function updateGraph( nextGraph ) {
		if ( ! selectedFunnel ) {
			return;
		}

		setIsSaving( true );
		setError( '' );

		try {
			await saveFunnelMeta( selectedFunnel, {
				[ metaKeys.graph ]: nextGraph,
			} );
		} catch ( nextError ) {
			setError( nextError.message || __( 'LibreFunnels could not save the graph.', 'librefunnels' ) );
		} finally {
			setIsSaving( false );
		}
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

		await updateGraph( {
			...graph,
			edges: [ ...graph.edges, edge ],
		} );
		setSelectedItem( { type: 'edge', id: edge.id } );
	}

	function getValidationSummary() {
		if ( ! selectedFunnel ) {
			return [];
		}

		const warnings = [];
		const startStepId = Number( getMeta( selectedFunnel, metaKeys.startStepId, 0 ) );

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
				/>
			</main>

			<Inspector
				selectedItem={ selectedItem }
				selectedFunnel={ selectedFunnel }
				graph={ graph }
				steps={ steps }
				funnelSteps={ funnelSteps }
				onSelect={ setSelectedItem }
				onUpdateGraph={ updateGraph }
				onUpdateStep={ updateStep }
				onSaveFunnelMeta={ saveFunnelMeta }
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
								<span>{ funnel.status }</span>
							</button>
						) ) }
					</div>
				) }
			</div>
		</aside>
	);
}

function Header( { selectedFunnel, warnings, isSaving, onCreateStep, onCreateEdge } ) {
	return (
		<header className="lf-canvas-header">
			<div>
				<p className="lf-label">{ __( 'Visual funnel map', 'librefunnels' ) }</p>
				<h1>{ selectedFunnel ? getPostTitle( selectedFunnel, __( 'Untitled funnel', 'librefunnels' ) ) : __( 'Create your first funnel', 'librefunnels' ) }</h1>
			</div>

			<div className="lf-header-actions">
				<span className={ `lf-health ${ warnings.length > 0 ? 'has-warnings' : 'is-clear' }` }>
					{ warnings.length > 0
						? sprintf( __( '%d issue(s)', 'librefunnels' ), warnings.length )
						: __( 'Ready', 'librefunnels' ) }
				</span>
				<button className="lf-button" type="button" onClick={ () => onCreateStep() } disabled={ ! selectedFunnel || isSaving }>
					{ __( 'Add step', 'librefunnels' ) }
				</button>
				<button className="lf-button" type="button" onClick={ onCreateEdge } disabled={ ! selectedFunnel || isSaving }>
					{ __( 'Connect route', 'librefunnels' ) }
				</button>
			</div>
		</header>
	);
}

function Canvas( { isLoading, graph, steps, selectedFunnel, selectedItem, onSelect } ) {
	const nodes = graph.nodes.map( ( node, index ) => ( {
		...node,
		position: normalizeNodePosition( node, index ),
	} ) );
	const nodeMap = new Map( nodes.map( ( node ) => [ node.id, node ] ) );
	const canvasWidth = Math.max( 880, ...nodes.map( ( node ) => node.position.x + 230 ), 880 );
	const canvasHeight = Math.max( 540, ...nodes.map( ( node ) => node.position.y + 150 ), 540 );

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
				<p>{ __( 'Add a checkout, offer, or thank-you step. LibreFunnels will keep broken routes visible as you build.', 'librefunnels' ) }</p>
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
				const type = getMeta( step, metaKeys.stepType, node.type || 'custom' );
				const isStart = Number( getMeta( selectedFunnel, metaKeys.startStepId, 0 ) ) === Number( node.stepId );

				return (
					<button
						key={ node.id }
						className={ `lf-node ${ selectedItem.type === 'node' && selectedItem.id === node.id ? 'is-selected' : '' } ${ warnings.length ? 'has-warning' : '' }` }
						type="button"
						style={ { left: `${ node.position.x }px`, top: `${ node.position.y }px` } }
						onClick={ () => onSelect( { type: 'node', id: node.id } ) }
					>
						<span className="lf-node__type">{ stepTypes[ type ] || type }</span>
						<strong>{ step ? getPostTitle( step, __( 'Untitled step', 'librefunnels' ) ) : __( 'Missing step', 'librefunnels' ) }</strong>
						<span className="lf-node__meta">
							{ isStart ? __( 'Start step', 'librefunnels' ) : routes.next }
							{ warnings.length > 0 ? ` · ${ __( 'Needs attention', 'librefunnels' ) }` : '' }
						</span>
					</button>
				);
			} ) }
		</div>
	);
}

function Inspector( { selectedItem, selectedFunnel, graph, steps, funnelSteps, onSelect, onUpdateGraph, onUpdateStep, onSaveFunnelMeta, isSaving } ) {
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

		return (
			<NodeInspector
				node={ node }
				step={ step }
				selectedFunnel={ selectedFunnel }
				warnings={ node ? getNodeWarnings( node, steps, selectedFunnel ) : [] }
				onUpdateStep={ onUpdateStep }
				onSaveFunnelMeta={ onSaveFunnelMeta }
				isSaving={ isSaving }
			/>
		);
	}

	if ( selectedItem.type === 'edge' ) {
		const edge = graph.edges.find( ( item ) => item.id === selectedItem.id );

		return (
			<EdgeInspector
				edge={ edge }
				graph={ graph }
				onUpdateGraph={ onUpdateGraph }
				onSelect={ onSelect }
				isSaving={ isSaving }
			/>
		);
	}

	return (
		<FunnelInspector
			selectedFunnel={ selectedFunnel }
			funnelSteps={ funnelSteps }
			graph={ graph }
			onSaveFunnelMeta={ onSaveFunnelMeta }
			isSaving={ isSaving }
		/>
	);
}

function FunnelInspector( { selectedFunnel, funnelSteps, graph, onSaveFunnelMeta, isSaving } ) {
	const startStepId = Number( getMeta( selectedFunnel, metaKeys.startStepId, 0 ) );

	return (
		<aside className="lf-inspector">
			<p className="lf-label">{ __( 'Funnel settings', 'librefunnels' ) }</p>
			<h2>{ getPostTitle( selectedFunnel, __( 'Untitled funnel', 'librefunnels' ) ) }</h2>
			<label className="lf-field">
				<span>{ __( 'Start step', 'librefunnels' ) }</span>
				<select
					value={ startStepId }
					onChange={ ( event ) => onSaveFunnelMeta( selectedFunnel, { [ metaKeys.startStepId ]: Number( event.target.value ) } ) }
					disabled={ isSaving }
				>
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
		</aside>
	);
}

function NodeInspector( { node, step, selectedFunnel, warnings, onUpdateStep, onSaveFunnelMeta, isSaving } ) {
	const [ title, setTitle ] = useState( step ? getPostTitle( step, '' ) : '' );
	const [ stepType, setStepType ] = useState( step ? getMeta( step, metaKeys.stepType, node?.type || 'custom' ) : 'custom' );
	const [ pageId, setPageId ] = useState( step ? Number( getMeta( step, metaKeys.stepPageId, 0 ) ) : 0 );

	useEffect( () => {
		setTitle( step ? getPostTitle( step, '' ) : '' );
		setStepType( step ? getMeta( step, metaKeys.stepType, node?.type || 'custom' ) : 'custom' );
		setPageId( step ? Number( getMeta( step, metaKeys.stepPageId, 0 ) ) : 0 );
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

			<label className="lf-field">
				<span>{ __( 'Assigned page ID', 'librefunnels' ) }</span>
				<input type="number" min="0" value={ pageId } onChange={ ( event ) => setPageId( Number( event.target.value ) ) } />
			</label>

			<div className="lf-action-row">
				<button
					className="lf-button lf-button--primary"
					type="button"
					disabled={ isSaving }
					onClick={ () =>
						onUpdateStep( step, {
							title,
							meta: {
								...( step.meta || {} ),
								[ metaKeys.stepType ]: stepType,
								[ metaKeys.stepPageId ]: pageId,
							},
						} )
					}
				>
					{ __( 'Save step', 'librefunnels' ) }
				</button>
				<button
					className="lf-button"
					type="button"
					disabled={ isSaving }
					onClick={ () => onSaveFunnelMeta( selectedFunnel, { [ metaKeys.startStepId ]: step.id } ) }
				>
					{ __( 'Make start', 'librefunnels' ) }
				</button>
			</div>
		</aside>
	);
}

function EdgeInspector( { edge, graph, onUpdateGraph, onSelect, isSaving } ) {
	const [ route, setRoute ] = useState( edge?.route || 'next' );
	const [ ruleText, setRuleText ] = useState( JSON.stringify( edge?.rule || {}, null, 2 ) );
	const [ ruleError, setRuleError ] = useState( '' );
	const warnings = edge ? getEdgeWarnings( edge, graph.nodes ) : [];

	useEffect( () => {
		setRoute( edge?.route || 'next' );
		setRuleText( JSON.stringify( edge?.rule || {}, null, 2 ) );
		setRuleError( '' );
	}, [ edge?.id ] );

	if ( ! edge ) {
		return (
			<aside className="lf-inspector">
				<p className="lf-label">{ __( 'Route inspector', 'librefunnels' ) }</p>
				<h2>{ __( 'Missing route', 'librefunnels' ) }</h2>
			</aside>
		);
	}

	async function saveEdge() {
		let parsedRule = {};

		if ( route === 'conditional' && ruleText.trim() !== '' ) {
			try {
				parsedRule = JSON.parse( ruleText );
			} catch ( parseError ) {
				setRuleError( __( 'The conditional rule is not valid JSON yet.', 'librefunnels' ) );
				return;
			}
		}

		setRuleError( '' );
		await onUpdateGraph( {
			...graph,
			edges: graph.edges.map( ( item ) =>
				item.id === edge.id
					? {
							...item,
							route,
							rule: parsedRule,
					  }
					: item
			),
		} );
		onSelect( { type: 'edge', id: edge.id } );
	}

	return (
		<aside className="lf-inspector">
			<p className="lf-label">{ __( 'Route inspector', 'librefunnels' ) }</p>
			<h2>{ routes[ edge.route ] || edge.route }</h2>
			<Warnings warnings={ warnings } />

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

			{ route === 'conditional' && (
				<label className="lf-field">
					<span>{ __( 'Rule JSON', 'librefunnels' ) }</span>
					<textarea rows="8" value={ ruleText } onChange={ ( event ) => setRuleText( event.target.value ) } />
				</label>
			) }

			{ ruleError && <p className="lf-field-error">{ ruleError }</p> }

			<button className="lf-button lf-button--primary" type="button" disabled={ isSaving } onClick={ saveEdge }>
				{ __( 'Save route', 'librefunnels' ) }
			</button>
		</aside>
	);
}

function Warnings( { warnings } ) {
	if ( warnings.length === 0 ) {
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
