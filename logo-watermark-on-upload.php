<?php
/**
 * Plugin Name: Logo Watermark on Upload
 * Plugin URI:  
 * Description: Automatically adds a watermark to uploaded images using the site's logo.
 * Version:     1.1
 * Author:      Towfique Elahe
 * Author URI:  https://towfique-elahe.framer.website/
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: logo-watermark-on-upload
 * Requires at least: 4.9
 * Requires PHP: 5.6 or later
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the settings menu
 */
function lwu_register_settings_menu() {
    add_options_page(
        'Logo Watermark Settings',
        'Logo Watermark',
        'manage_options',
        'lwu-settings',
        'lwu_settings_page'
    );
}
add_action( 'admin_menu', 'lwu_register_settings_menu' );

/**
 * Register settings
 */
function lwu_register_settings() {
    register_setting( 'lwu_settings_group', 'lwu_settings' );
}
add_action( 'admin_init', 'lwu_register_settings' );

/**
 * Settings page content
 */
function lwu_settings_page() {
    // Get existing options
    $options = get_option( 'lwu_settings' );
    ?>

    <div class="wrap">
        <h1>Logo Watermark Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'lwu_settings_group' ); ?>
            <?php do_settings_sections( 'lwu_settings_group' ); ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Enable Watermark</th>
                    <td>
                        <input type="checkbox" name="lwu_settings[enabled]" value="1" <?php checked( isset( $options['enabled'] ) ? $options['enabled'] : 0, 1 ); ?> />
                        <label for="lwu_settings[enabled]">Enable watermarking on image upload</label>
                    </td>
                </tr>

                <?php if ( isset( $options['enabled'] ) && $options['enabled'] ) : ?>

                <tr valign="top">
                    <th scope="row">Watermark Size (Width in pixels)</th>
                    <td>
                        <input type="number" name="lwu_settings[size]" value="<?php echo isset( $options['size'] ) ? esc_attr( $options['size'] ) : '100'; ?>" min="10" max="1000" />
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Watermark Position</th>
                    <td>
                        <select name="lwu_settings[position]">
                            <?php
                            $positions = array(
                                'bottom-right' => 'Bottom Right',
                                'bottom-left'  => 'Bottom Left',
                                'top-right'    => 'Top Right',
                                'top-left'     => 'Top Left',
                                'center'       => 'Center',
                            );
                            $current_position = isset( $options['position'] ) ? $options['position'] : 'bottom-right';
                            foreach ( $positions as $value => $label ) {
                                echo '<option value="' . esc_attr( $value ) . '"' . selected( $current_position, $value, false ) . '>' . esc_html( $label ) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Watermark Opacity (0-100)</th>
                    <td>
                        <input type="number" name="lwu_settings[opacity]" value="<?php echo isset( $options['opacity'] ) ? esc_attr( $options['opacity'] ) : '100'; ?>" min="0" max="100" />
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Margin from Edge (pixels)</th>
                    <td>
                        <input type="number" name="lwu_settings[margin]" value="<?php echo isset( $options['margin'] ) ? esc_attr( $options['margin'] ) : '10'; ?>" min="0" max="1000" />
                    </td>
                </tr>

                <?php endif; ?>

            </table>

            <?php submit_button(); ?>
        </form>
    </div>

    <?php
}

/**
 * Add Watermark to Uploaded Images
 */
function lwu_add_watermark_to_image( $file ) {
    // Get settings
    $options = get_option( 'lwu_settings' );

    // Check if watermarking is enabled
    if ( ! isset( $options['enabled'] ) || ! $options['enabled'] ) {
        return $file;
    }

    // Check if the uploaded file is an image
    $image_types = array( 'image/jpeg', 'image/png' );
    if ( ! in_array( $file['type'], $image_types ) ) {
        return $file;
    }

    // Load the image
    $image_path = $file['file'];
    $image_ext  = pathinfo( $image_path, PATHINFO_EXTENSION );

    // Get the site logo URL
    $custom_logo_id = get_theme_mod( 'custom_logo' );
    if ( ! $custom_logo_id ) {
        return $file; // No logo set, exit function
    }
    $logo_url = wp_get_attachment_image_src( $custom_logo_id, 'full' )[0];
    if ( ! $logo_url ) {
        return $file; // Could not get logo URL
    }

    // Load the watermark image from the logo URL
    $watermark = imagecreatefromstring( file_get_contents( $logo_url ) );
    if ( ! $watermark ) {
        return $file; // Failed to load watermark image
    }

    // Create image from file
    switch ( strtolower( $image_ext ) ) {
        case 'jpeg':
        case 'jpg':
            $image = imagecreatefromjpeg( $image_path );
            break;
        case 'png':
            $image = imagecreatefrompng( $image_path );
            break;
        default:
            return $file;
    }
    if ( ! $image ) {
        return $file; // Failed to create image from file
    }

    // Get dimensions of the main image and watermark
    $image_width       = imagesx( $image );
    $image_height      = imagesy( $image );
    $watermark_width   = imagesx( $watermark );
    $watermark_height  = imagesy( $watermark );

    // Watermark size from settings
    $new_watermark_width = isset( $options['size'] ) ? intval( $options['size'] ) : 100;
    $new_watermark_width = max( 10, min( $new_watermark_width, $image_width ) ); // Ensure it's within bounds
    $new_watermark_height = ( $new_watermark_width / $watermark_width ) * $watermark_height;

    // Resize the watermark
    $resized_watermark = imagecreatetruecolor( $new_watermark_width, $new_watermark_height );

    // Handle transparency for PNG
    imagealphablending( $resized_watermark, false );
    imagesavealpha( $resized_watermark, true );
    $transparent = imagecolorallocatealpha( $resized_watermark, 0, 0, 0, 127 );
    imagefill( $resized_watermark, 0, 0, $transparent );

    imagecopyresampled( $resized_watermark, $watermark, 0, 0, 0, 0, $new_watermark_width, $new_watermark_height, $watermark_width, $watermark_height );
    imagedestroy( $watermark ); // Free the memory of the old watermark
    $watermark        = $resized_watermark;
    $watermark_width  = $new_watermark_width;
    $watermark_height = $new_watermark_height;

    // Set opacity
    if ( isset( $options['opacity'] ) ) {
        $opacity = intval( $options['opacity'] );
        $opacity = max( 0, min( $opacity, 100 ) ); // Ensure it's between 0 and 100
        if ( $opacity < 100 ) {
            // Apply opacity to the watermark
            lwu_image_copy_merge_alpha( $image, $watermark, $dest_x, $dest_y, 0, 0, $watermark_width, $watermark_height, $opacity );
        }
    }

    // Calculate position for the watermark
    $margin = isset( $options['margin'] ) ? intval( $options['margin'] ) : 10;

    switch ( isset( $options['position'] ) ? $options['position'] : 'bottom-right' ) {
        case 'bottom-right':
            $dest_x = $image_width - $watermark_width - $margin;
            $dest_y = $image_height - $watermark_height - $margin;
            break;
        case 'bottom-left':
            $dest_x = $margin;
            $dest_y = $image_height - $watermark_height - $margin;
            break;
        case 'top-right':
            $dest_x = $image_width - $watermark_width - $margin;
            $dest_y = $margin;
            break;
        case 'top-left':
            $dest_x = $margin;
            $dest_y = $margin;
            break;
        case 'center':
            $dest_x = ( $image_width - $watermark_width ) / 2;
            $dest_y = ( $image_height - $watermark_height ) / 2;
            break;
        default:
            // Default to bottom-right
            $dest_x = $image_width - $watermark_width - $margin;
            $dest_y = $image_height - $watermark_height - $margin;
            break;
    }

    // Merge the watermark with the main image
    imagecopy( $image, $watermark, $dest_x, $dest_y, 0, 0, $watermark_width, $watermark_height );

    // Save the watermarked image
    switch ( strtolower( $image_ext ) ) {
        case 'jpeg':
        case 'jpg':
            imagejpeg( $image, $image_path );
            break;
        case 'png':
            imagealphablending( $image, false );
            imagesavealpha( $image, true );
            imagepng( $image, $image_path );
            break;
    }

    // Free up memory
    imagedestroy( $image );
    imagedestroy( $watermark );

    return $file;
}
add_filter( 'wp_handle_upload', 'lwu_add_watermark_to_image', 10, 1 );

/**
 * Function to merge images with alpha transparency and opacity
 */
function lwu_image_copy_merge_alpha( $dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $opacity ) {
    // Get image width and height
    $w = imagesx( $src_im );
    $h = imagesy( $src_im );

    // Turn alpha blending off
    imagealphablending( $src_im, false );

    // Find the most opaque pixel in the image (the one with the smallest alpha value)
    $min_alpha = 127;
    for ( $x = 0; $x < $w; $x++ ) {
        for ( $y = 0; $y < $h; $y++ ) {
            $alpha = ( imagecolorat( $src_im, $x, $y ) >> 24 ) & 0xFF;
            if ( $alpha < $min_alpha ) {
                $min_alpha = $alpha;
            }
        }
    }

    // Loop through image pixels and modify alpha for each
    for ( $x = 0; $x < $w; $x++ ) {
        for ( $y = 0; $y < $h; $y++ ) {
            // Get current alpha value (0-127)
            $color_xy = imagecolorat( $src_im, $x, $y );
            $alpha    = ( $color_xy >> 24 ) & 0xFF;

            // Calculate new alpha
            $alpha = $alpha + ( 127 - $min_alpha ) * ( 100 - $opacity ) / 100;

            // Get the color index with new alpha
            $new_color = imagecolorallocatealpha(
                $src_im,
                ( $color_xy >> 16 ) & 0xFF,
                ( $color_xy >> 8 ) & 0xFF,
                $color_xy & 0xFF,
                $alpha
            );

            // Set pixel with the new color + opacity
            imagesetpixel( $src_im, $x, $y, $new_color );
        }
    }

    // Copy it
    imagecopy( $dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h );
}

