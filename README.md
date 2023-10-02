# AI Search experiment

Experimental plugin for AI-driven search by Crocoblock. This plugin is literally AI search for your website. Allows parse you website content by Open AI and than search over parsed content also using Open AI possibilities. Returns a lot more better results than default WordPress search. One more advantage over deafult search - spelling insensetivity.

## Quick Start

__Step 1__

Install and activate plugin (as usual WP plugin)

__Step 2__

Go to *Settings/AI Search*

<img width="1291" alt="image" src="https://github.com/Crocoblock/jet-ai-search/assets/4987981/e2d7fd28-9d2c-45f7-9cf9-c6b8d320e78d">

Set up your Open AI API key and adjust other settings like you need (but better t test other settings later, when you set up all data)

__Step 3__

Fetch some starting content, because plugin will work only with own fetched fragments, not content directly.

__Step 4__

Enable required post types for auto-fetch. Auto fetch is required to plugin automatically update fragmnets when you create/update/delete posts of selected post type.

## Advanced Usage

### Allow to search by custom field values

Next code snippet allow you to add value from meta field to parsed content fragments. Using this you can run AI search by selected custom field values, not only posts content/excerpt.

```php
add_filter( 'jet-ai-search/post-fragments', function( $fragments, $post, $parser ) {

	// Optional part - making sure we work with posts of need type
	if ( 'product' !== $post->post_type ) {
		return $fragments;
	}

	// Getting field value
	$field_name = 'description-for-the-search';
	$custom_description = get_post_meta( $post->ID, $field_name, true );

	// If value is empty - nothing to do here
	if ( ! $custom_description ) {
		return $fragments;
	}

	// Reset previously parsed stack and store new fragment
	$parser->reset_results();

	$parser->set_stack_defaults( [
		'post_id'    => $post->ID,
		'post_url'   => $post->guid,
		'post_title' => $post->post_title,
		'source'     => $post->post_type,
	] );

	$title    = $parser->prepare_heading( $post->post_title );
	$fragment = $parser->prepare_fragment( $custom_description );

	$parser->stack_result( [
		'fragment' => $title . $fragment
	], true );

	// Merge stored fragment with all previous fragmnets
	$fragments = array_merge( $fragments, $parser->get_result() );

	return $fragments;
	
}, 10, 3 );
```
After adding this code, you need to re-fetch content of required post type to make sure new fragments stored in DB. 
