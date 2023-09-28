<?php
/*
Plugin Name: Woo CSV Product Uploader
Description: Uploads CSV file and adds WooCommerce variations to a single product.
Version: 1.4.0
Author: Aeros Salaga
Author URI: aerossalaga.com
*/

// Add the menu item for the plugin
function csv_product_uploader_menu() {
    add_menu_page(
        'CSV Product Uploader',
        'Product Uploader',
        'manage_options',
        'csv-product-uploader',
        'csv_product_uploader_page',
        'dashicons-upload'
    );
}
add_action('admin_menu', 'csv_product_uploader_menu');

// Display the plugin page
function csv_product_uploader_page() {
    ?>
    <div class="wrap">
        <h1>CSV Product Uploader</h1>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="csv_file" />
            <input type="text" name="product_id" placeholder="Product ID" />
            <input type="submit" name="csv_product_uploader_btn" value="Upload CSV" />
        </form>
    </div>
    <?php
}

// Process the uploaded CSV file
function process_uploaded_csv() {
    if (isset($_POST['csv_product_uploader_btn'])) {
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $csv_path = $_FILES['csv_file']['tmp_name'];
            $product_id = intval($_POST['product_id']);

            // Check if the product exists
            $product = wc_get_product($product_id);

            // Read the CSV data
            $csv_data = array_map('str_getcsv', file($csv_path));
            $headers = array_shift($csv_data);

            // Get column indexes
            $sku_index = array_search('sku', array_map('strtolower', $headers));
            $description_index = array_search('description', array_map('strtolower', $headers));
            $price_index = array_search('price', array_map('strtolower', $headers));
            $metal_index = array_search('metal', array_map('strtolower', $headers));
            $stone_shape_index = array_search('stone shape', array_map('strtolower', $headers));
            $stone_size_index = array_search('stone size', array_map('strtolower', $headers));
            $dimensions_index = array_search('dimensions', array_map('strtolower', $headers));
            $ring_size_index = array_search('ring size', array_map('strtolower', $headers));
            $image_1_index = array_search('image 1', array_map('strtolower', $headers));
            $image_2_index = array_search('image 2', array_map('strtolower', $headers));
            $image_3_index = array_search('image 3', array_map('strtolower', $headers));
            $image_4_index = array_search('image 4', array_map('strtolower', $headers));
            $image_5_index = array_search('image 5', array_map('strtolower', $headers));

            if ($sku_index !== false && $description_index !== false && $price_index !== false) {
                // Iterate through the CSV data and add variations to the specified product
                if ($product) {
                    foreach ($csv_data as $row) {
                        $sku = $row[$sku_index];
                        $description = $row[$description_index];
                        $price = $row[$price_index];
                        $metal = $row[$metal_index];
                        $stone_shape = $row[$stone_shape_index];
                        $stone_size = $row[$stone_size_index];
                        $dimensions = $row[$dimensions_index];
                        $ring_size = $row[$ring_size_index];
                        $image_1 = $row[$image_1_index];
                        $image_2 = $row[$image_2_index];
                        $image_3 = $row[$image_3_index];
                        $image_4 = $row[$image_4_index];
                        $image_5 = $row[$image_5_index];                   

                    
                        // Create a new variation
                        $variation_data = array(
                            'post_title' => $product->get_name() . ' ' . $sku,
                            'post_content' => $description,
                            'post_status' => 'publish',
                            'post_parent' => $product_id,
                            'post_type' => 'product_variation'
                        );

                        $variation_id = wp_insert_post($variation_data);
                        $variation_product = wc_get_product($variation_id);

                        $variation_product->set_regular_price($price);

                       // Set attributes
                        $attributes = array(
                            'metal' => $metal,
                            'stone-shape' => $stone_shape,
                            'stone-size' => $stone_size,
                            'dimensions' => $dimensions,
                            'ring-size' => $ring_size,
                        );

                        $variation_product_attributes = array();
                        foreach ($attributes as $attribute_name => $attribute_value) {
                            $attribute_term = get_term_by('name', $attribute_value, 'pa_' . $attribute_name);
                            if ($attribute_term) {
                                $variation_product_attributes['attribute_pa_' . $attribute_name] = $attribute_term->slug;
                            }
                        }

                        $variation_product->set_props(array('attributes' => $variation_product_attributes));

                        // Set variation SKU
                        $variation_product->set_sku($sku);

                        // Process variation images
                        $image_urls = array($image_1, $image_2, $image_3, $image_4, $image_5);
                        $image_ids = array();
                        foreach ($image_urls as $image_url) {
                            if (!empty($image_url)) {
                                //manually set image directory where the images was uploaded
                                $image_path = site_url() . '/wp-content/uploads/2023/08/' . $image_url;
                                $image_id = attachment_url_to_postid($image_path);
                                if ($image_id) {
                                    $image_ids[] = $image_id;
                                } else {
                                    //manually set image directory where the images was uploaded
                                    $image_path = site_url() . '/wp-content/uploads/2023/08/' . $image_url;
                                    $image_id = attachment_url_to_postid($image_path);

                                    if ($image_id) {
                                        $image_ids[] = $image_id;
                                    }
                                }
                            }
                        }

                        // Set variation images
                        if (!empty($image_ids)) {
                            $variation_product->set_image_id($image_ids[0]); // Set the first image as the main variation image

                            $variation_image_ids = array_slice($image_ids, 1);
                            $variation_gallery_attachments = implode(',', $variation_image_ids);
                            update_post_meta($variation_id, '_wc_additional_variation_images', $variation_gallery_attachments);
                        }

                        // Set stock quantity
                        $variation_product->set_manage_stock(true);
                        $variation_product->set_stock_quantity(1);

                        // Set variation product name and description
                        add_post_meta($variation_id, '_vaspfw_variation_name', $product->get_name());
                        $variation_product->set_description($description);

                        $variation_product->save();

                        echo '<div class="notice notice-success"><p>Variation with SKU "' . $sku . '" added successfully!</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error"><p>Invalid product ID provided!</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>Invalid CSV file format! Make sure the required columns are present: sku, description, price, metal, stone shape, stone size, image 1, image 2, and image 3.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>No file uploaded!</p></div>';
        }
    }
}
add_action('admin_notices', 'process_uploaded_csv');
