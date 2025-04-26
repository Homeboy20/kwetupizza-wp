<?php
// Location: /wp-content/plugins/kwetu-pizza-plugin/admin/menu-management.php

// Render the Menu Management interface
function kwetupizza_render_menu_management() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_products';

    // Fetch menu items from the database
    $menu_items = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id ASC");

    echo "<h1>Menu Management</h1>";
    echo "<button id='add-new-menu-item' class='button button-primary'>Add New Menu Item</button><br><br>";

    // Display menu items in a grid layout
    echo "<div class='menu-items-grid'>";
    if ($menu_items) {
        foreach ($menu_items as $item) {
            echo "<div class='menu-item-card'>
                    <div class='menu-item-image'>";
            if (!empty($item->image_url)) {
                echo "<img src='" . esc_url($item->image_url) . "' alt='" . esc_attr($item->product_name) . "'>";
            } else {
                echo "<span>No Image</span>";
            }
            echo "</div>
                    <div class='menu-item-details'>
                        <h3>" . esc_html($item->product_name) . "</h3>
                        <p>" . esc_html($item->description) . "</p>
                        <p>Price: " . esc_html(number_format($item->price, 2)) . " " . esc_html($item->currency) . "</p>
                        <p>Category: " . esc_html($item->category) . "</p>
                        <button class='edit-menu-item button' data-id='" . esc_attr($item->id) . "' data-name='" . esc_attr($item->product_name) . "' data-description='" . esc_attr($item->description) . "' data-price='" . esc_attr($item->price) . "' data-currency='" . esc_attr($item->currency) . "' data-category='" . esc_attr($item->category) . "' data-image-url='" . esc_attr($item->image_url) . "'>Edit</button>
                        <button class='delete-menu-item button button-danger' data-id='" . esc_attr($item->id) . "'>Delete</button>
                    </div>
                  </div>";
        }
    } else {
        echo "<p>No menu items found</p>";
    }
    echo "</div>";

    // Add Modal HTML for Add/Edit
    echo '<div id="menu-item-modal" class="menu-item-modal" style="display:none;">
            <div class="menu-item-modal-content">
                <span class="close-modal">&times;</span>
                <h2 id="modal-title">Add New Menu Item</h2>
                <form id="menu-item-form">
                    <input type="hidden" id="menu-item-id" name="menu_item_id">
                    <label for="product_name">Product Name:</label><br>
                    <input type="text" id="product_name" name="product_name" required><br><br>

                    <label for="description">Description:</label><br>
                    <textarea id="description" name="description"></textarea><br><br>

                    <label for="price">Price:</label><br>
                    <input type="number" step="0.01" id="price" name="price" required><br><br>

                    <label for="currency">Currency:</label><br>
                    <input type="text" id="currency" name="currency" value="TZS" readonly><br><br>

                    <label for="category">Category:</label><br>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <option value="Pizza">Pizza</option>
                        <option value="Drinks">Drinks</option>
                        <option value="Dessert">Dessert</option>
                    </select><br><br>

                    <!-- Image Upload Field -->
                    <label for="menu_item_image">Image:</label><br>
                    <div class="image-upload-wrapper">
                        <img id="menu_item_image_preview" src="" alt="" style="max-width: 100%; height: auto; display: none; margin-bottom: 10px;">
                        <button type="button" class="button button-secondary" id="upload_image_button">Upload Image</button>
                        <button type="button" class="button button-secondary" id="remove_image_button" style="display:none;">Remove Image</button>
                        <input type="hidden" id="menu_item_image_url" name="image_url" value="">
                    </div><br>

                    <button type="submit" class="button button-primary">Save Menu Item</button>
                </form>
            </div>
        </div>';

    // Styles for the grid layout
    echo '<style>
            .menu-items-grid {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
            }
            .menu-item-card {
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 15px;
                width: calc(33.333% - 20px);
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }
            .menu-item-image img {
                width: 100%;
                height: auto;
                border-radius: 8px;
            }
            .menu-item-details {
                margin-top: 10px;
            }
            .menu-item-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: none;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            }
            .menu-item-modal-content {
                background: white;
                padding: 20px;
                border-radius: 5px;
                width: 500px;
                position: relative;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            }
            .close-modal {
                position: absolute;
                top: 10px;
                right: 15px;
                font-size: 20px;
                cursor: pointer;
            }
        </style>';

    // JavaScript for handling modal functionality
    echo '<script>
        jQuery(document).ready(function($) {
            var image_frame;

            // Function to open the media uploader
            $("#upload_image_button").on("click", function(event) {
                event.preventDefault();

                // If the frame already exists, reopen it
                if (image_frame) {
                    image_frame.open();
                    return;
                }

                // Create the media frame
                image_frame = wp.media({
                    title: "Select or Upload Image",
                    button: {
                        text: "Use this image"
                    },
                    library: {
                        type: "image"
                    },
                    multiple: false
                });

                // When an image is selected, run a callback
                image_frame.on("select", function() {
                    var attachment = image_frame.state().get("selection").first().toJSON();
                    $("#menu_item_image_preview").attr("src", attachment.url).show();
                    $("#menu_item_image_url").val(attachment.url);
                    $("#remove_image_button").show();
                });

                // Open the media frame
                image_frame.open();
            });

            // Function to remove the image
            $("#remove_image_button").on("click", function(event) {
                event.preventDefault();
                $("#menu_item_image_preview").attr("src", "").hide();
                $("#menu_item_image_url").val("");
                $(this).hide();
            });

            // Open Add New Modal
            $("#add-new-menu-item").on("click", function() {
                $("#menu-item-id").val("");
                $("#product_name").val("");
                $("#description").val("");
                $("#price").val("");
                $("#currency").val("TZS"); // Default currency
                $("#category").val(""); // Reset category
                resetImageUpload();
                $("#modal-title").text("Add New Menu Item");
                $("#menu-item-modal").fadeIn(); // Show the modal
            });

            // Edit item functionality
            $(".edit-menu-item").on("click", function() {
                var id = $(this).data("id");
                var name = $(this).data("name");
                var description = $(this).data("description");
                var price = $(this).data("price");
                var currency = $(this).data("currency");
                var category = $(this).data("category");
                var image_url = $(this).data("image-url");

                $("#menu-item-id").val(id);
                $("#product_name").val(name);
                $("#description").val(description);
                $("#price").val(price);
                $("#currency").val(currency);
                $("#category").val(category);
                if (image_url) {
                    $("#menu_item_image_preview").attr("src", image_url).show();
                    $("#menu_item_image_url").val(image_url);
                    $("#remove_image_button").show();
                } else {
                    resetImageUpload();
                }

                $("#modal-title").text("Edit Menu Item");
                $("#menu-item-modal").fadeIn(); // Show the modal
            });

            // Close Modal
            $(".close-modal").on("click", function() {
                $("#menu-item-modal").fadeOut(); // Hide the modal
            });

            // Handle form submit
$("#menu-item-form").on("submit", function(e) {
    e.preventDefault();
    var formData = $(this).serialize();

    $.post(ajaxurl, {
        action: "kwetupizza_save_menu_item",
        data: formData
    })
    .done(function(response) {
        if (response.success) {
            alert(response.message);
            location.reload();
        } else {
            alert("Error: " + response.data.message);
        }
    })
    .fail(function(jqXHR, textStatus, errorThrown) {
        alert("An error occurred: " + errorThrown);
    });
});


            function resetImageUpload() {
                $("#menu_item_image_preview").attr("src", "").hide();
                $("#menu_item_image_url").val("");
                $("#remove_image_button").hide();
            }
        });
    </script>';
}


// Function to enqueue the media uploader
function kwetupizza_enqueue_media_uploader() {
    if (is_admin()) {
        wp_enqueue_media();
        wp_enqueue_script('jquery');
    }
}
add_action('admin_enqueue_scripts', 'kwetupizza_enqueue_media_uploader');



?>
