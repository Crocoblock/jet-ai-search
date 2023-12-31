const {
	Card,
	CardHeader,
	CardBody,
	CardFooter,
	__experimentalHeading: Heading,
	Button,
	TextControl,
	ToggleControl,
	SelectControl,
	RangeControl,
	FlexBlock,
	Flex
} = wp.components;

const {
	render,
	Component,
	Fragment
} = wp.element;

class App extends Component {

	constructor( props ) {

		super( props );
		this.state = { fetch_post_type: 'post', done: 0, total: 0, ...this.props.data };

	}

	ajaxURL() {
		return window.ajaxurl + '?action=jet_ai_search_dispatch&nonce=' + window.JetAISearchData.nonce;
	}

	saveOptions() {
		this.setState( { saving: true } );
		wp.apiFetch( {
			method: 'POST',
			url: this.ajaxURL(),
			data: {
				dispatch: 'settings.dispatch_update',
				settings: {
					api_key: this.state.api_key,
					mode: this.state.mode,
					strictness: this.state.strictness,
					limit: this.state.limit,
					auto_fetch: this.state.auto_fetch,
				},
			}
		} ).then( ( response ) => {

			this.setState( { saving: false } );

			if ( ! response.success ) {
				alert( response.data );
			}

		} ).catch( ( error ) => {
			this.setState( { saving: false } );
			alert( error );
		} );
	}

	clearData() {
		wp.apiFetch( {
			method: 'POST',
			url: this.ajaxURL(),
			data: {
				dispatch: 'data.dispatch_clear',
				nonce: window.JetAISearchData.nonce,
			}
		} ).then( ( response ) => {
			
			if ( ! response.success ) {
				alert( response.data );
				return;
			}

			window.location.reload();
		} );
	}

	fetchChunk( chunk ) {
		wp.apiFetch( {
			method: 'POST',
			url: this.ajaxURL(),
			data: {
				dispatch: 'data.dispatch_fetch',
				nonce: window.JetAISearchData.nonce,
				post_type: this.state.fetch_post_type,
				api_key: this.state.api_key,
				chunk: chunk,
			}
		} ).then( ( response ) => {

			if ( ! response.success ) {
				this.setState( { fetching: false } );
				alert( response.data );
				return;
			}

			this.setState( { 
				done: response.data.done,
				total: response.data.total 
			} );

			if ( response.data.has_next ) {
				this.fetchChunk( chunk + 1 );
			} else {
				this.setState( { fetching: false } );
				this.setState( { 
					done: 0,
					total: 0 
				} );
			}
		} ).catch( ( error ) => {
			this.setState( { fetching: false } );
		} );
	}

	fetchContent() {
		this.setState( { fetching: true } );
		this.fetchChunk( 1 );
	}

	fetchButtonLabel() {
		if ( this.state.fetching ) {
			return 'Fetching...';
		} else {
			return 'Fetch';
		}
	}

	saveButtonLabel() {
		if ( this.state.saving ) {
			return 'Updating...';
		} else {
			return 'Update Settings';
		}
	}

	isAutoFetchEnabled( postType ) {
		return this.state.auto_fetch.length && this.state.auto_fetch.includes( postType );
	}

	swithAutoFetch( postType ) {
		
		postType = postType || false;
		const newAutoFetch = [ ...this.state.auto_fetch ];
		const postTypes = [];

		if ( postType ) {
			postTypes.push( postType );
		} else {
			for ( var i = 0; i < this.props.postTypes.length; i++ ) {
				postTypes.push( this.props.postTypes[ i ].value );
			}
		}

		for( var i = 0, length1 = postTypes.length; i < length1; i++ ) {
			if ( this.isAutoFetchEnabled( postTypes[i] ) ) {
				newAutoFetch.splice( newAutoFetch.indexOf( postTypes[i] ), 1 );
			} else {
				newAutoFetch.push( postTypes[i] );
			}
		}

		this.setState( { auto_fetch: newAutoFetch } );

	}

	modeDesc() {
		switch ( this.state.mode ) {
			case 'all':
				return 'All queries with `s` parameter will work in AI mode';
			case 'by_request':
				return 'Query will work in AI mode only if `is_ai` parameter presented in Request. You need to add this paramter on your side.';
			case 'none':
				return 'Only AJAX search by JetSearch will work in AI mode (if used on this website)';
		}
	}

	render() {

		return ( <div
			className="jet-ai-search"
			style={ { padding: "20px 20px 20px 0" } }
		>
			<Card 
				style={ { margin: "0 0 20px" } }
			>
				<CardHeader>
					<Heading level={ 3 }>Settings</Heading>
				</CardHeader>
				<CardBody>
					<TextControl
						label="Open AI API key"
						value={ this.state.api_key }
						help="Find details at Open AI documentation - https://openai.com/product#made-for-developers"
						onChange={ ( value ) => {
							this.setState( { api_key: value } );
						} }
					/>
					<SelectControl
						label="Working Mode"
						value={ this.state.mode }
						help={ this.modeDesc() }
						onChange={ ( value ) => {
							this.setState( { mode: value } );
						} }
						options={[
							{
								label: 'All',
								value: 'all'
							},
							{
								label: 'By request',
								value: 'by_request'
							},
							{
								label: 'None',
								value: 'none'
							}
						]}
					/>
					<RangeControl
						label="Strictness"
						help="0 - is most 'strict' results, 1 - most 'gentle'. For lower values there are more chances AI search will returns empty results."
						max={ 1 }
						min={ 0 }
						step={ 0.01 }
						value={ this.state.strictness }
						onChange={ ( value ) => {
							this.setState( { strictness: value } );
						} }
					/>
					<TextControl
						label="Results Limit"
						help="Max number of results AI search will return. This option is not related to `posts per page` option of the query where AI search will be applied."
						max={ 100 }
						min={ 1 }
						type="number"
						step={ 1 }
						value={ this.state.limit }
						onChange={ ( value ) => {
							this.setState( { limit: parseInt( value, 10 ) } );
						} }
					/>
				</CardBody>
				<CardFooter>
					<Button
						variant="primary"
						onClick={ () => { this.saveOptions() } }
						disabled={ this.state.saving }
					>{ this.saveButtonLabel() }</Button>
				</CardFooter>
			</Card>
			<Flex
				gap="20px"
				align="stretch"
			>
				<FlexBlock>
					<Card>
						<CardHeader>
							<Heading level={ 3 }>Fetched Content Statistics</Heading>
						</CardHeader>
						<CardBody>
							{ ! this.props.stats.length && <div>
								You not fetched any content yet.
							</div> }
							{ this.props.stats.length && <ul style={ { margin: 0 } }>
								{ this.props.stats.map( ( item ) => {
									return <li style={ { padding: '3px 0' } }>
										{ item.label } - { item.fetched }/{ item.total }
									</li> 
								} ) }
							</ul> }
						</CardBody>
						<CardFooter>
							<Button
								variant="secondary"
								isDestructive={ true }
								onClick={ () => {
									if ( confirm( 'Are you sure?' ) ) {
										this.clearData();
									}
								} }
							>Clear Fetched Content</Button>
						</CardFooter>
					</Card>
				</FlexBlock>
				<FlexBlock>
					<Card>
						<CardHeader>
							<Heading level={ 3 }>Auto-Fetch</Heading>
						</CardHeader>
						<CardBody>
							{ this.props.postTypes.map( ( postType ) => {
								return <ToggleControl
									checked={ this.isAutoFetchEnabled( postType.value ) }
									label={ postType.label }
									onChange={ () => this.swithAutoFetch( postType.value ) }
								/>
							} ) }
						</CardBody>
						<CardFooter>
							<Button
								variant="primary"
								onClick={ () => { this.saveOptions() } }
								disabled={ this.state.saving }
							>{ this.saveButtonLabel() }</Button>
						</CardFooter>
					</Card>
				</FlexBlock>
				<FlexBlock>
					<Card>
						<CardHeader>
							<Heading level={ 3 }>Fetch Content</Heading>
						</CardHeader>
						<CardBody>
							{ ! this.state.api_key && <div>
								Please set your Open AI API Key to fetch the content.
							</div> }
							{ this.state.api_key && <div>
								<SelectControl
									label="Select Post Type to fetch data from"
									options={ this.props.postTypes }
									value={ this.state.fetch_post_type }
									onChange={ ( value ) => {
										this.setState( { fetch_post_type: value } );
									} }
									help="Already fetched posts will be rewritten with new"
								/>
							</div> }
						</CardBody>
						<CardFooter>
							<Button
								variant="primary"
								disabled={ ! this.state.api_key || this.state.fetching }
								onClick={ () => { this.fetchContent(); } }
							>{ this.fetchButtonLabel() }</Button>
							{ this.state.fetching && <div>
								{ this.state.done } / { this.state.total }
							</div> }
						</CardFooter>
					</Card>
				</FlexBlock>
			</Flex>
		</div> );
	}

}

const controlEl = document.getElementById( 'jet_ai_search_app' );

if ( controlEl ) {
	render(
		<App
			data={ window.JetAISearchData.settings }
			stats={ window.JetAISearchData.stats }
			postTypes={ window.JetAISearchData.post_types }
		/>,
		controlEl
	);
}
