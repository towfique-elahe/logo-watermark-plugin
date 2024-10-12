<?php
/**
 * Plugin Name: Logo Watermark on Upload
 * Plugin URI:  
 * Description: Automatically adds a watermark to uploaded images using the site's logo. The plugin provides options to enable watermarking, set the watermark size, choose the position, adjust the opacity, and define the margin from the edge.
 * Version:     1.2
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

    // Apply the watermark with opacity
    $opacity = isset( $options['opacity'] ) ? intval( $options['opacity'] ) : 100;
    lwu_image_copy_merge_alpha( $image, $watermark, $dest_x, $dest_y, 0, 0, $watermark_width, $watermark_height, $opacity );

    // Save the watermarked image
    switch ( strtolower( $image_ext ) ) {
        case 'jpeg':
        case 'jpg':
            imagejpeg( $image, $image_path );
            break;
        case 'png':
            imagepng( $image, $image_path );
            break;
    }

    // Free memory
    imagedestroy( $image );
    imagedestroy( $watermark );

    return $file;
}
add_filter( 'wp_handle_upload', 'lwu_add_watermark_to_image' );

/**
 * Merge two images with alpha transparency support and opacity control
 */
function lwu_image_copy_merge_alpha( $dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $opacity ) {
    // Turn alpha blending on for destination image
    imagealphablending( $dst_im, true );

    // Create a new transparent image to hold the watermark with adjusted opacity
    $tmp_img = imagecreatetruecolor( $src_w, $src_h );

    // Enable alpha blending and preserve transparency for the temporary image
    imagealphablending( $tmp_img, false );
    imagesavealpha( $tmp_img, true );

    // Fill the temporary image with transparent color
    $transparent = imagecolorallocatealpha( $tmp_img, 0, 0, 0, 127 );
    imagefill( $tmp_img, 0, 0, $transparent );

    // Copy the source image into the temporary image
    imagecopy( $tmp_img, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h );

    // Apply opacity to the temporary image
    for ( $x = 0; $x < $src_w; $x++ ) {
        for ( $y = 0; $y < $src_h; $y++ ) {
            $pixel_color = imagecolorat( $tmp_img, $x, $y );
            $alpha = ( $pixel_color >> 24 ) & 0x7F; // 0-127 scale for alpha
            $new_alpha = $alpha + ( 127 - $alpha ) * ( 1 - $opacity / 100 );
            $new_color = imagecolorallocatealpha(
                $tmp_img,
                ( $pixel_color >> 16 ) & 0xFF,
                ( $pixel_color >> 8 ) & 0xFF,
                $pixel_color & 0xFF,
                $new_alpha
            );
            imagesetpixel( $tmp_img, $x, $y, $new_color );
        }
    }

    // Merge the watermark with the destination image
    imagecopy( $dst_im, $tmp_img, $dst_x, $dst_y, 0, 0, $src_w, $src_h );

    // Free memory
    imagedestroy( $tmp_img );
}
