<?php
/**
 * Plugin Name:       neAGENCIJA Image Copyright
 * Description:       Adds an Avtor field to images in the Media Library and block editor, and outputs it on the frontend as div.image-author next to each img inside figure.
 * Version:           1.1.0
 * Author:            neAGENCIJA
 * Text Domain:       nea-image-copyright
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * GitHub Plugin URI: https://github.com/gregamer/nea-image-copyright
 * Primary Branch:    main
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NEA_IMAGE_COPYRIGHT_VERSION', '1.1.0' );
define( 'NEA_IMAGE_COPYRIGHT_FILE', __FILE__ );
define( 'NEA_IMAGE_COPYRIGHT_META_KEY', '_nea_image_author' );
define( 'NEA_IMAGE_COPYRIGHT_OPTION_PREFIX', 'nea_image_copyright_prefix' );
define( 'NEA_IMAGE_COPYRIGHT_OPTION_CSS', 'nea_image_copyright_css' );

/**
 * Default CSS for .image-author (property declarations only).
 *
 * @return string
 */
function nea_image_copyright_default_css() {
	return "position: absolute;\nleft: 10px;\ntop: 10px;\nfont-size: 12px;";
}

/**
 * Get settings with defaults.
 *
 * @return array{prefix: string, css: string}
 */
function nea_image_copyright_get_settings() {
	$prefix = get_option( NEA_IMAGE_COPYRIGHT_OPTION_PREFIX, '' );
	$css    = get_option( NEA_IMAGE_COPYRIGHT_OPTION_CSS, null );

	if ( null === $css ) {
		$css = nea_image_copyright_default_css();
	}

	return array(
		'prefix' => is_string( $prefix ) ? $prefix : '',
		'css'    => is_string( $css ) ? $css : '',
	);
}

/**
 * Register settings.
 */
function nea_image_copyright_register_settings() {
	register_setting(
		'nea_image_copyright_settings',
		NEA_IMAGE_COPYRIGHT_OPTION_PREFIX,
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	register_setting(
		'nea_image_copyright_settings',
		NEA_IMAGE_COPYRIGHT_OPTION_CSS,
		array(
			'type'              => 'string',
			'sanitize_callback' => 'nea_image_copyright_sanitize_css',
			'default'           => nea_image_copyright_default_css(),
		)
	);
}
add_action( 'admin_init', 'nea_image_copyright_register_settings' );

/**
 * Sanitize custom CSS (strip tags, keep CSS text).
 *
 * @param mixed $value Raw value.
 * @return string
 */
function nea_image_copyright_sanitize_css( $value ) {
	if ( ! is_string( $value ) ) {
		return nea_image_copyright_default_css();
	}

	$value = wp_strip_all_tags( $value );
	$value = str_replace( array( '<?', '?>' ), '', $value );

	return $value;
}

/**
 * Add settings page under Settings.
 */
function nea_image_copyright_add_settings_page() {
	add_options_page(
		'Image Copyright',
		'Image Copyright',
		'manage_options',
		'nea-image-copyright',
		'nea_image_copyright_render_settings_page'
	);
}
add_action( 'admin_menu', 'nea_image_copyright_add_settings_page' );

/**
 * Render settings page.
 */
function nea_image_copyright_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = nea_image_copyright_get_settings();
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php settings_fields( 'nea_image_copyright_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="nea_image_copyright_prefix">Prefix vrednosti</label>
					</th>
					<td>
						<input
							type="text"
							class="regular-text"
							id="nea_image_copyright_prefix"
							name="<?php echo esc_attr( NEA_IMAGE_COPYRIGHT_OPTION_PREFIX ); ?>"
							value="<?php echo esc_attr( $settings['prefix'] ); ?>"
							placeholder="Avtor: "
						/>
						<p class="description">Besedilo pred avtorjem na frontendu (npr. <code>Avtor: </code>).</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="nea_image_copyright_css">CSS za .image-author</label>
					</th>
					<td>
						<textarea
							class="large-text code"
							id="nea_image_copyright_css"
							name="<?php echo esc_attr( NEA_IMAGE_COPYRIGHT_OPTION_CSS ); ?>"
							rows="8"
							cols="50"
						><?php echo esc_textarea( $settings['css'] ); ?></textarea>
						<p class="description">CSS lastnosti znotraj <code>.image-author { … }</code>. Figure samodejno dobi <code>position: relative</code>.</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Settings link on Plugins screen.
 *
 * @param array $links Plugin action links.
 * @return array
 */
function nea_image_copyright_plugin_action_links( $links ) {
	$url = admin_url( 'options-general.php?page=nea-image-copyright' );
	array_unshift(
		$links,
		'<a href="' . esc_url( $url ) . '">Nastavitve</a>'
	);
	return $links;
}
add_filter(
	'plugin_action_links_' . plugin_basename( NEA_IMAGE_COPYRIGHT_FILE ),
	'nea_image_copyright_plugin_action_links'
);

/**
 * Output frontend CSS for .image-author.
 */
function nea_image_copyright_enqueue_frontend_styles() {
	$settings = nea_image_copyright_get_settings();
	$css      = trim( $settings['css'] );

	$rules = "figure:has(.image-author){position:relative;}";
	if ( '' !== $css ) {
		$rules .= '.image-author{' . $css . '}';
	}

	wp_register_style( 'nea-image-copyright', false, array(), NEA_IMAGE_COPYRIGHT_VERSION );
	wp_enqueue_style( 'nea-image-copyright' );
	wp_add_inline_style( 'nea-image-copyright', $rules );
}
add_action( 'wp_enqueue_scripts', 'nea_image_copyright_enqueue_frontend_styles' );

/**
 * Register attachment meta for the author field.
 */
function nea_image_copyright_register_meta() {
	register_post_meta(
		'attachment',
		NEA_IMAGE_COPYRIGHT_META_KEY,
		array(
			'type'              => 'string',
			'description'       => 'Image author / copyright credit',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'init', 'nea_image_copyright_register_meta' );

/**
 * Add Avtor field to attachment edit form, directly before Alternative Text.
 *
 * @param array   $form_fields Existing form fields.
 * @param WP_Post $post        Attachment post.
 * @return array
 */
function nea_image_copyright_attachment_fields_to_edit( $form_fields, $post ) {
	if ( ! wp_attachment_is_image( $post->ID ) ) {
		return $form_fields;
	}

	$value = get_post_meta( $post->ID, NEA_IMAGE_COPYRIGHT_META_KEY, true );

	$author_field = array(
		'label' => 'Avtor',
		'input' => 'text',
		'value' => is_string( $value ) ? $value : '',
		'helps' => '',
	);

	$reordered = array();
	$inserted  = false;

	foreach ( $form_fields as $key => $field ) {
		if ( 'image_alt' === $key && ! $inserted ) {
			$reordered['nea_image_author'] = $author_field;
			$inserted                      = true;
		}
		$reordered[ $key ] = $field;
	}

	if ( ! $inserted ) {
		$reordered['nea_image_author'] = $author_field;
	}

	return $reordered;
}
add_filter( 'attachment_fields_to_edit', 'nea_image_copyright_attachment_fields_to_edit', 10, 2 );

/**
 * Save Avtor field from attachment edit form / media modal.
 *
 * @param array $post       Attachment post data.
 * @param array $attachment Attachment fields from request.
 * @return array
 */
function nea_image_copyright_attachment_fields_to_save( $post, $attachment ) {
	if ( ! isset( $attachment['nea_image_author'] ) ) {
		return $post;
	}

	$author = sanitize_text_field( $attachment['nea_image_author'] );
	update_post_meta( $post['ID'], NEA_IMAGE_COPYRIGHT_META_KEY, $author );

	return $post;
}
add_filter( 'attachment_fields_to_save', 'nea_image_copyright_attachment_fields_to_save', 10, 2 );

/**
 * Enqueue Media Library modal script.
 */
function nea_image_copyright_enqueue_media() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	// Skip dedicated attachment edit screen — field is already in the form above alt.
	if ( $screen && 'attachment' === $screen->id && 'post' === $screen->base ) {
		return;
	}

	wp_enqueue_script(
		'nea-image-copyright-media',
		plugins_url( 'assets/js/media.js', NEA_IMAGE_COPYRIGHT_FILE ),
		array( 'jquery', 'media-views' ),
		NEA_IMAGE_COPYRIGHT_VERSION,
		true
	);
}
add_action( 'wp_enqueue_media', 'nea_image_copyright_enqueue_media' );

/**
 * Enqueue block editor script.
 */
function nea_image_copyright_enqueue_editor() {
	wp_enqueue_script(
		'nea-image-copyright-editor',
		plugins_url( 'assets/js/editor.js', NEA_IMAGE_COPYRIGHT_FILE ),
		array(
			'wp-hooks',
			'wp-compose',
			'wp-element',
			'wp-components',
			'wp-block-editor',
			'wp-data',
			'wp-core-data',
			'wp-i18n',
		),
		NEA_IMAGE_COPYRIGHT_VERSION,
		true
	);

	wp_localize_script(
		'nea-image-copyright-editor',
		'neaImageCopyright',
		array(
			'metaKey' => NEA_IMAGE_COPYRIGHT_META_KEY,
			'label'   => 'Avtor',
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'nea_image_copyright_enqueue_editor' );

/**
 * Get sanitized author string for an attachment ID.
 *
 * @param int $attachment_id Attachment ID.
 * @return string
 */
function nea_image_copyright_get_author( $attachment_id ) {
	$author = get_post_meta( (int) $attachment_id, NEA_IMAGE_COPYRIGHT_META_KEY, true );
	if ( ! is_string( $author ) ) {
		return '';
	}
	return trim( $author );
}

/**
 * Format author for frontend display (prefix + value).
 *
 * @param string $author Author meta value.
 * @return string
 */
function nea_image_copyright_format_author_display( $author ) {
	$author = trim( (string) $author );
	if ( '' === $author ) {
		return '';
	}

	$settings = nea_image_copyright_get_settings();
	$prefix   = $settings['prefix'];

	return $prefix . $author;
}

/**
 * Insert div.image-author as a sibling of img.wp-image-{id} inside each figure.
 *
 * @param string $html HTML content.
 * @return string
 */
function nea_image_copyright_inject_authors( $html ) {
	if ( '' === $html || false === stripos( $html, '<figure' ) || false === stripos( $html, 'wp-image-' ) ) {
		return $html;
	}

	if ( ! class_exists( 'DOMDocument' ) ) {
		return $html;
	}

	$previous = libxml_use_internal_errors( true );
	$dom      = new DOMDocument();

	$wrapped = '<?xml encoding="utf-8" ?><div id="nea-image-copyright-root">' . $html . '</div>';
	$loaded  = $dom->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	if ( ! $loaded ) {
		return $html;
	}

	$xpath = new DOMXPath( $dom );

	// Only innermost figures that contain a Media Library image (handles galleries).
	$figures = $xpath->query(
		'//figure[.//img[contains(@class, "wp-image-")]][not(.//figure[.//img[contains(@class, "wp-image-")]])]'
	);

	if ( ! $figures || 0 === $figures->length ) {
		return $html;
	}

	$changed = false;

	foreach ( $figures as $figure ) {
		$existing = $xpath->query(
			'.//*[contains(concat(" ", normalize-space(@class), " "), " image-author ")]',
			$figure
		);
		if ( $existing && $existing->length > 0 ) {
			continue;
		}

		$images = $xpath->query( './/img[contains(@class, "wp-image-")]', $figure );
		if ( ! $images || 0 === $images->length ) {
			continue;
		}

		$img = $images->item( 0 );
		if ( ! $img instanceof DOMElement ) {
			continue;
		}

		$class = $img->getAttribute( 'class' );
		if ( ! preg_match( '/(?:^|\s)wp-image-(\d+)(?:\s|$)/', $class, $matches ) ) {
			continue;
		}

		$author = nea_image_copyright_get_author( (int) $matches[1] );
		if ( '' === $author ) {
			continue;
		}

		$display = nea_image_copyright_format_author_display( $author );

		$div = $dom->createElement( 'div' );
		$div->setAttribute( 'class', 'image-author' );
		$div->appendChild( $dom->createTextNode( $display ) );

		$parent = $img->parentNode;
		if ( ! $parent ) {
			continue;
		}

		if ( $img->nextSibling ) {
			$parent->insertBefore( $div, $img->nextSibling );
		} else {
			$parent->appendChild( $div );
		}

		$changed = true;
	}

	if ( ! $changed ) {
		return $html;
	}

	$roots = $xpath->query( '//*[@id="nea-image-copyright-root"]' );
	$root  = ( $roots && $roots->length ) ? $roots->item( 0 ) : null;
	if ( ! $root ) {
		return $html;
	}

	$output = '';
	foreach ( $root->childNodes as $child ) {
		$output .= $dom->saveHTML( $child );
	}

	return $output;
}

/**
 * Inject authors into rendered blocks.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block.
 * @return string
 */
function nea_image_copyright_render_block( $block_content, $block ) {
	unset( $block );
	return nea_image_copyright_inject_authors( $block_content );
}
add_filter( 'render_block', 'nea_image_copyright_render_block', 10, 2 );

/**
 * Inject authors into classic content figures.
 *
 * @param string $content Post content.
 * @return string
 */
function nea_image_copyright_the_content( $content ) {
	return nea_image_copyright_inject_authors( $content );
}
add_filter( 'the_content', 'nea_image_copyright_the_content', 12 );
