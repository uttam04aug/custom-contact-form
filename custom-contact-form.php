<?php
/*
Plugin Name: Custom Contact Form with DB
Description: Saves form data to DB, AJAX validation, and Thank You Popup.
Version: 1.0
Author: uttam04aug@gmail.com
*/

if (!defined('ABSPATH')) exit;

// 1. Create Database Table on Activation
register_activation_hook(__FILE__, 'ccf_create_table');
function ccf_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_contact_messages';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        name text NOT NULL,
        email varchar(100) NOT NULL,
        subject text NOT NULL,
        message text NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// 2. Shortcode for Form [my_contact_form]
add_shortcode('my_contact_form', 'ccf_display_form');
function ccf_display_form() {
    ob_start(); ?>
    <form id="contactForm" style="position: relative;">
        <div class="input-group">
            <input type="text" id="name" placeholder="Your Name">
            <span class="error-msg"></span>
        </div>
        <div class="input-group">
            <input type="email" id="email" placeholder="Your Email">
            <span class="error-msg"></span>
        </div>
        <div class="input-group">
            <input type="text" id="subject" placeholder="Subject">
            <span class="error-msg"></span>
        </div>
        <div class="input-group">
            <textarea id="message" placeholder="Your Message"></textarea>
            <span class="error-msg"></span>
        </div>
        <button type="submit" class="btn" style="color:#fff;">Send Message</button>
    </form>

    <div id="thankYouPopup" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; padding:20px 70px; border:1px solid #ccc; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.7); z-index:9999; text-align:center;">
        <h3>Thank You!</h3>
        <p>Your message has been sent successfully.</p>
        <button onclick="document.getElementById('thankYouPopup').style.display='none'" class="btn" style="color:#fff;">Close</button>
    </div>

    <style>
        .input-group { margin-bottom:0px; }
        .error-msg { color: red; font-size: 12px; display: block; margin-top: -22px;
  margin-bottom: 16px; }
        input, textarea { width: 100%; padding: 8px; margin-bottom: 0px; }
    </style>

    <script>
    document.getElementById('contactForm').addEventListener('submit', function(e) {
        e.preventDefault();
        let isValid = true;
        const fields = ['name', 'email', 'subject', 'message'];
        
        fields.forEach(f => {
            const input = document.getElementById(f);
            const error = input.nextElementSibling;
            if (!input.value.trim()) {
                error.innerText = "This field is required";
                isValid = false;
            } else {
                error.innerText = "";
            }
        });

        if (isValid) {
            const formData = new FormData();
            formData.append('action', 'ccf_save_form');
            formData.append('name', document.getElementById('name').value);
            formData.append('email', document.getElementById('email').value);
            formData.append('subject', document.getElementById('subject').value);
            formData.append('message', document.getElementById('message').value);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(data => {
                document.getElementById('thankYouPopup').style.display = 'block';
                document.getElementById('contactForm').reset();
            });
        }
    });
    </script>
    <?php
    return ob_get_clean();
}

// 3. Handle AJAX Data Saving
add_action('wp_ajax_ccf_save_form', 'ccf_save_form_callback');
add_action('wp_ajax_nopriv_ccf_save_form', 'ccf_save_form_callback');

function ccf_save_form_callback() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_contact_messages';
    
    $wpdb->insert($table_name, array(
        'time' => current_time('mysql'),
        'name' => sanitize_text_field($_POST['name']),
        'email' => sanitize_email($_POST['email']),
        'subject' => sanitize_text_field($_POST['subject']),
        'message' => sanitize_textarea_field($_POST['message']),
    ));
    wp_die();
}

// 4. Admin Menu to view data
add_action('admin_menu', 'ccf_admin_menu');
function ccf_admin_menu() {
    add_menu_page('Form Messages', 'Form Messages', 'manage_options', 'form-messages', 'ccf_admin_page', 'dashicons-email');
}

function ccf_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_contact_messages';

    // --- DELETE LOGIC ---
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $wpdb->delete($table_name, array('id' => $id));
        echo '<div class="updated"><p>Message Deleted!</p></div>';
    }

    // --- PAGINATION LOGIC ---
    $limit = 10; // Per page entries
    $pagenum = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($pagenum - 1) * $limit;

    $total = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
    $num_of_pages = ceil($total / $limit);

    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT $offset, $limit");

    echo '<div class="wrap"><h1>Contact Messages</h1>';
    echo '<table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:15%">Name</th>
                    <th style="width:20%">Email</th>
                    <th style="width:15%">Subject</th>
                    <th>Message</th>
                    <th style="width:15%">Date</th>
                    <th style="width:10%">Action</th>
                </tr>
            </thead>
            <tbody>';

    if ($results) {
        foreach ($results as $row) {
            $delete_url = admin_url('admin.php?page=form-messages&action=delete&id=' . $row->id);
            echo "<tr>
                    <td>" . esc_html($row->name) . "</td>
                    <td>" . esc_html($row->email) . "</td>
                    <td>" . esc_html($row->subject) . "</td>
                    <td>" . nl2br(esc_html($row->message)) . "</td>
                    <td>{$row->time}</td>
                    <td><a href='{$delete_url}' class='button button-link-delete' onclick='return confirm(\"Are you sure?\")'>Delete</a></td>
                  </tr>";
        }
    } else {
        echo '<tr><td colspan="6">No messages found.</td></tr>';
    }

    echo '</tbody></table>';

    // --- PAGINATION LINKS ---
    if ($num_of_pages > 1) {
        $page_links = paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'total' => $num_of_pages,
            'current' => $pagenum
        ));

        if ($page_links) {
            echo '<div class="tablenav"><div class="tablenav-pages" style="margin: 10px 0;">' . $page_links . '</div></div>';
        }
    }
    echo '</div>';
}