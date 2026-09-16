<?php

namespace SCCommerce\MetaBoxes;

use SCCommerce\Contracts\Hookable;
use SCCommerce\PostTypes\ProductPostType;

/**
 * Price, SKU and pricing type for a product. The "Variable" option
 * and its variations repeater are provision for variable products,
 * not a full implementation — each row is just a label/price/SKU/image/
 * description, with no attribute system (e.g. Size x Colour) generating them
 * automatically. That's deliberately out of scope for v1 (see the
 * README's "Variable products" section); the meta shape
 * (ProductPostType::META_VARIATIONS, a flat array of rows) is chosen
 * so a future attribute-matrix UI can still write to the same field
 * without a data migration.
 */
final class ProductDetailsMetaBox implements Hookable
{
    private const NONCE_ACTION = 'scc_save_product_details';
    private const NONCE_NAME = 'scc_product_details_nonce';

    public function register(): void
    {
        \add_action('add_meta_boxes', [$this, 'addMetaBox']);
        \add_action('save_post_'.ProductPostType::POST_TYPE, [$this, 'save']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMetaBox(): void
    {
        \add_meta_box(
            'scc-product-details',
            'Product Details',
            [$this, 'render'],
            ProductPostType::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if (! \in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = \get_current_screen();

        if (! $screen || $screen->post_type !== ProductPostType::POST_TYPE) {
            return;
        }

        \wp_enqueue_style('scc-admin', SCC_URL.'assets/css/admin.css', [], SCC_VERSION);
        // wp_enqueue_media() registers wp.media — the core image picker
        // product-variations.js opens for each variation's "Choose image"
        // button, rather than this plugin building its own upload UI.
        \wp_enqueue_media();
        \wp_enqueue_script('scc-product-variations', SCC_URL.'assets/js/product-variations.js', [], SCC_VERSION, true);
    }

    public function render(\WP_Post $post): void
    {
        \wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $price = \get_post_meta($post->ID, ProductPostType::META_PRICE, true);
        $sku = \get_post_meta($post->ID, ProductPostType::META_SKU, true);
        $type = \get_post_meta($post->ID, ProductPostType::META_TYPE, true) ?: ProductPostType::TYPE_SIMPLE;
        $variations = \get_post_meta($post->ID, ProductPostType::META_VARIATIONS, true);
        $variations = \is_array($variations) && $variations !== [] ? $variations : [['label' => '', 'price' => '', 'sku' => '', 'image_id' => 0, 'description' => '']];
        ?>
        <p>
            <label for="scc_type"><strong>Product type</strong></label><br>
            <select name="scc_type" id="scc_type">
                <?php foreach (ProductPostType::TYPES as $value => $label) : ?>
                    <option value="<?php echo \esc_attr($value); ?>" <?php \selected($type, $value); ?>><?php echo \esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <div id="scc-simple-fields" style="<?php echo $type === ProductPostType::TYPE_VARIABLE ? 'display:none;' : ''; ?>">
            <p>
                <label for="scc_price"><strong>Price</strong></label><br>
                <input type="number" step="0.01" min="0" id="scc_price" name="scc_price" value="<?php echo \esc_attr($price); ?>">
            </p>
            <p>
                <label for="scc_sku"><strong>SKU</strong></label><br>
                <input type="text" id="scc_sku" name="scc_sku" value="<?php echo \esc_attr($sku); ?>">
            </p>
        </div>

        <div id="scc-variable-fields" style="<?php echo $type === ProductPostType::TYPE_SIMPLE ? 'display:none;' : ''; ?>">
            <p><strong>Variations</strong></p>
            <table class="widefat" id="scc-variations-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Label</th>
                        <th>Price</th>
                        <th>SKU</th>
                        <th>Description</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($variations as $index => $variation) : $imageId = (int) ($variation['image_id'] ?? 0); ?>
                        <tr class="scc-variation-row">
                            <td>
                                <div class="scc-variation-image">
                                    <img class="scc-variation-image-preview" style="<?php echo $imageId ? '' : 'display:none;'; ?>" src="<?php echo $imageId ? \esc_url((string) \wp_get_attachment_image_url($imageId, 'medium')) : ''; ?>" alt="">
                                    <input type="hidden" class="scc-variation-image-id" name="scc_variations[<?php echo (int) $index; ?>][image_id]" value="<?php echo \esc_attr($imageId ?: ''); ?>">
                                    <button type="button" class="button scc-variation-choose-image"><?php echo $imageId ? 'Change image' : 'Choose image'; ?></button>
                                    <button type="button" class="button scc-variation-remove-image" style="<?php echo $imageId ? '' : 'display:none;'; ?>">Remove image</button>
                                </div>
                            </td>
                            <td><input type="text" name="scc_variations[<?php echo (int) $index; ?>][label]" value="<?php echo \esc_attr($variation['label'] ?? ''); ?>" placeholder="e.g. Large / Blue"></td>
                            <td><input type="number" step="0.01" min="0" name="scc_variations[<?php echo (int) $index; ?>][price]" value="<?php echo \esc_attr($variation['price'] ?? ''); ?>"></td>
                            <td><input type="text" name="scc_variations[<?php echo (int) $index; ?>][sku]" value="<?php echo \esc_attr($variation['sku'] ?? ''); ?>"></td>
                            <td><textarea rows="2" name="scc_variations[<?php echo (int) $index; ?>][description]" placeholder="Shown on the product page when this variant is selected"><?php echo \esc_textarea($variation['description'] ?? ''); ?></textarea></td>
                            <td><button type="button" class="button scc-remove-variation">Remove</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button" id="scc-add-variation">Add variation</button></p>
        </div>
        <?php
    }

    public function save(int $postId): void
    {
        if (
            ! isset($_POST[self::NONCE_NAME])
            || ! \wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)
        ) {
            return;
        }

        if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! \current_user_can('edit_post', $postId)) {
            return;
        }

        $type = isset($_POST['scc_type']) && $_POST['scc_type'] === ProductPostType::TYPE_VARIABLE
            ? ProductPostType::TYPE_VARIABLE
            : ProductPostType::TYPE_SIMPLE;

        \update_post_meta($postId, ProductPostType::META_TYPE, $type);
        \update_post_meta($postId, ProductPostType::META_PRICE, isset($_POST['scc_price']) ? (float) $_POST['scc_price'] : 0);
        \update_post_meta($postId, ProductPostType::META_SKU, isset($_POST['scc_sku']) ? \sanitize_text_field(\wp_unslash($_POST['scc_sku'])) : '');

        $variations = [];

        if (isset($_POST['scc_variations']) && \is_array($_POST['scc_variations'])) {
            foreach ($_POST['scc_variations'] as $row) {
                $label = \sanitize_text_field(\wp_unslash($row['label'] ?? ''));

                if ($label === '') {
                    continue;
                }

                $variations[] = [
                    'label' => $label,
                    'price' => (float) ($row['price'] ?? 0),
                    'sku' => \sanitize_text_field(\wp_unslash($row['sku'] ?? '')),
                    'image_id' => isset($row['image_id']) && $row['image_id'] !== '' ? \absint($row['image_id']) : 0,
                    'description' => \sanitize_textarea_field(\wp_unslash($row['description'] ?? '')),
                ];
            }
        }

        \update_post_meta($postId, ProductPostType::META_VARIATIONS, $variations);
    }
}
