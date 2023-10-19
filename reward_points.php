<?php
/**
* Plugin Name: Reward Points
* Plugin URI: https://www.linkedin.com/in/qasimzahoor/
* Description: Rewards points system to use with coupons.
* Version: 1.0
* Author: Qasim Zahoor
* Author URI: https://www.linkedin.com/in/qasimzahoor/
**/


function reward_points_box_html($post)
{
    $post_id = ($post->ID !== NULL) ? $post->ID : $post;
    ?>
    <div class="panel woocommerce_options_panel">
        <p class="form-field">
            <label for="ReqPoints">Min. Points Required</label>
            <span class="woocommerce-help-tip" data-tip="Min. Points required to apply this coupon code, 0 for none."></span>
            <?php $qz_req_points = get_post_meta($post_id, '_qz_req_points', true); ?>
            <input type="number" id="ReqPoints" name="qz_req_points" value="<?php echo ($qz_req_points > 0)? $qz_req_points : 0; ?>">
        </p>
        <p class="form-field">
            <label for="ShowInAccount">Show In User Account</label>
            <?php $qz_show_in_account = get_post_meta($post_id, '_qz_show_in_account', true); ?>
            <input type="checkbox" id="ShowInAccount" name="qz_show_in_account" value="1" <?php checked($qz_show_in_account, 1 ); ?>> 
            <span class="description">Coupon will be displayed in user account after they earn min. points required.</span> 
        </p>
    </div>
    <?php
}

function product_points_box_html($post)
{
    global $post;
    ?>
    <div class="panel woocommerce_options_panel">
        <p class="form-field">
            <label for="ProdPoints">Reward Points</label>
            <span class="woocommerce-help-tip" data-tip="Min. Points required to apply this coupon code, 0 for none."></span>
            <?php $qz_product_points = get_post_meta($post->ID, '_qz_product_points', true); ?>
            <input type="number" id="ProdPoints" name="qz_product_points" value="<?php echo ($qz_product_points > 0)? $qz_product_points : 0; ?>">
        </p>
    </div>
    <?php
}

function qz_add_custom_box()
{ 
    add_meta_box(
            'reward_points',           // Unique ID
            'Reward Points',  // Box title
            'reward_points_box_html',  // Content callback, must be of type callable
            'shop_coupon' 
        );
    add_meta_box(
        'product_points',           // Unique ID
        'Reward Points',  // Box title
        'product_points_box_html',  // Content callback, must be of type callable
        'product' 
    );
}
add_action('add_meta_boxes', 'qz_add_custom_box');
add_action('dokan_coupon_form_fields_end', 'reward_points_box_html');
add_action('dokan_product_edit_after_inventory', 'product_points_box_html');
 
function qz_save_point_settings($post_id)
    { 
        if (array_key_exists('qz_req_points', $_POST)) {
            update_post_meta(
                $post_id,
                '_qz_req_points',
                $_POST['qz_req_points']
            );
        }
        if (array_key_exists('qz_show_in_account', $_POST)) {
            update_post_meta(
                $post_id,
                '_qz_show_in_account',
                $_POST['qz_show_in_account']
            );
        }
        if (array_key_exists('qz_product_points', $_POST)) {
            update_post_meta(
                $post_id, 
                '_qz_product_points',
                $_POST['qz_product_points']
            );
        }
    }
add_action('save_post', 'qz_save_point_settings');
add_action('dokan_after_coupon_create', 'qz_save_point_settings');

function qz_user_dashboard_points_display()
{ 
    $user_id =get_current_user_id();
    $total_points = get_user_meta( $user_id, '_qz_user_points', true);
    $msg = '<p>You have '.$total_points.' points in your account!</p>';
    echo $msg;

    $args = array(
        'post_type'        => 'shop_coupon',
        'order'            => 'ASC',
        'orderby'          => 'meta_value',
        'posts_per_page'   => -1,
        'meta_query' => array(
          array(
            'key' => '_qz_req_points',
            'value' => $total_points,
            'compare' => '<=',
            'type' => 'NUMERIC'
          )
          ,
         array(
            'key' => '_qz_show_in_account',
            'value' => 1,
            'compare' => '=',
            'type' => 'NUMERIC'
          )
        )
    );

    $coupons  = get_posts( $args );
    if(!count($coupons) > 0) return;
    echo '<div class="dokan-coupon-content"><h3>Copon Codes</h3>
        <table class="dokan-table">
        <thead>
            <tr>
                <th>Copon Code</th>
                <th>Required Points</th>
                <th>Store</th>
            </tr>
        </thead>
        <tbody>
    ';
    foreach($coupons as $coupon){ 
        //print_r($coupon);
        $points_required = get_post_meta($coupon->ID, '_qz_req_points', true);
        $shop = get_user_meta( $coupon->post_author, 'dokan_store_name', true);
        $vendor = dokan()->vendor->get( $coupon->post_author );
        $shop_link = '<a href="'.$vendor->get_shop_url().'">'.$shop.'</a>';

        $discount_type = get_post_meta($coupon->ID, 'discount_type', true);
        $discount_ammount = get_post_meta($coupon->ID, 'coupon_amount', true);

        echo '<tr>  
            <td class="coupon-code" data-title="Code"><div class="code"><a><span>'.$coupon->post_name.'</span</a></div></td> 
            <td>'.$points_required.'pt</td> 
            <td>'.(user_can( $coupon->post_author, 'manage_options' )? 'All Stores' : $shop_link).'</td> 
        </tr>';

    }
    echo  '</tbody></table></div>';
}
add_action('woocommerce_account_dashboard', 'qz_user_dashboard_points_display');

/*Front End*/
function qz_product_points_display()
{ 
    global $post;
    $qz_product_points = get_post_meta($post->ID, '_qz_product_points', true);
    $msg = '<p>You will get '.$qz_product_points.' points!</p>';
    echo $msg;
}
add_action('woocommerce_before_add_to_cart_form', 'qz_product_points_display');

//cart
function qz_display_points_cart( $item_data, $cart_item ) {
    // if ( empty( $cart_item['iconic-engraving'] ) ) {
    //     return $item_data;
    // }
    $qz_product_points = get_post_meta($cart_item['product_id'], '_qz_product_points', true);
    $item_data[] = array(
        'key'     => __( 'Points', 'qz_product_points' ),
        'value'   => wc_clean($qz_product_points*$cart_item['quantity']),
        'display' => '',
    );
    return $item_data;
}
add_filter( 'woocommerce_get_item_data', 'qz_display_points_cart', 10, 2 );
//cart total
function qz_total_points_display()
{ 
    global $woocommerce;
    $items = $woocommerce->cart->get_cart();
    $total_points = 0;
    foreach($items as $item){ 
        $qz_product_points = get_post_meta($item['product_id'], '_qz_product_points', true);
        $total_points+=($qz_product_points*$item['quantity']);

    }
    $msg = '<tr class="points-total">
    <th>Total Points</th>
    <td data-title="Points"><strong>'.$total_points.'</strong> </td>
    </tr>';
    echo $msg;
}
add_action('woocommerce_cart_totals_before_order_total', 'qz_total_points_display', 10, 0 );

//order add custom cart item data woocommerce
function qz_add_points_to_order_items( $item, $cart_item_key, $values, $order ) {
    $qz_product_points = get_post_meta($item['product_id'], '_qz_product_points', true);
    $total_points+=($qz_product_points*$item['quantity']);
    $item->add_meta_data( __( 'Points', 'qz_product_points' ), $qz_product_points ); 
}
add_action( 'woocommerce_checkout_create_order_line_item', 'qz_add_points_to_order_items', 10, 4 );

//placed order
function qz_user_order_placed_points( $order_id ) 
{ 
    $user_id =get_current_user_id();
    $order = new WC_Order($order_id); 
    $total_points = get_user_meta( $user_id, '_qz_user_points', true);
    $total_points = $total_points > 0 ? $total_points : 0;
    foreach ( $order->get_items() as $item_id => $item ) {
        $product = $item->get_product();
        $product_id = $product->get_id();
        $qz_product_points = get_post_meta($product_id, '_qz_product_points', true);
        $total_points+=($qz_product_points*$item['quantity']);
    }
    if($user_id) update_user_meta( $user_id, '_qz_user_points', $total_points);
} 
add_action('woocommerce_thankyou', 'qz_user_order_placed_points', 10, 1);

//Add to order
add_filter( 'woocommerce_get_order_item_totals', 'insert_custom_line_order_item_totals', 10, 3 );
function insert_custom_line_order_item_totals( $total_rows, $order, $tax_display ){
    $total_points = 0;
    foreach ( $order->get_items() as $item_id => $item ) {
        $product = $item->get_product();
        $product_id = $product->get_id();
        $qz_product_points = get_post_meta($product_id, '_qz_product_points', true);
        $total_points+=($qz_product_points*$item['quantity']);
    }
    $total_rows['recurr_not'] = array(
        'label' => __( 'Total Reward Points :', 'woocommerce' ),
        'value' => $total_points 
    );
   
    return $total_rows;
}

add_action('woocommerce_admin_order_totals_after_total', 'custom_admin_order_totals_after_tax', 10, 1 );
function custom_admin_order_totals_after_tax( $order_id ) {
    
    $label = __( 'Total Reward Points', 'woocommerce' );
    $order = new WC_Order($order_id); 
    $total_points = 0;
    foreach ( $order->get_items() as $item_id => $item ) {
        $product = $item->get_product();
        $product_id = $product->get_id();
        $qz_product_points = get_post_meta($product_id, '_qz_product_points', true);
        $total_points+=($qz_product_points*$item['quantity']);
    }
    ?>
        <tr>
            <td class="label"><?php echo $label; ?>:</td>
            <td width="1%"></td>
            <td class="custom-total"><?php echo $total_points; ?></td>
        </tr>
    <?php
}

function reward_points_after_order_total() {
    global $woocommerce;
    $items = $woocommerce->cart->get_cart();
    $total_points = 0;
    foreach($items as $item){ 
        $qz_product_points = get_post_meta($item['product_id'], '_qz_product_points', true);
        $total_points+=($qz_product_points*$item['quantity']);
    }
    $label = __( 'Total Reward Points', 'woocommerce' );
    $value = $total_points;
    ?>
        <tr>
            <th><?php echo $label; ?>:</th>
            <td><strong><?php echo $value; ?></strong></td>
        </tr>
    <?php
}
add_action('woocommerce_review_order_after_order_total','reward_points_after_order_total');


function filter_woocommerce_coupon_is_valid( $true, $instance ) { 
    $user_id =get_current_user_id();
    if(!$user_id) return $true;
    
    $qz_req_points = get_post_meta($instance->id, '_qz_req_points', true);
    $total_points = get_user_meta($user_id, '_qz_user_points', true);

    if($qz_req_points > 0 AND $total_points < $qz_req_points){
         $true = false;
         wc_add_notice( __( 'The coupon "'.$instance->code.'" can’t be used.', 'woocoommerce' ), 'error' );
    }
    return $true; 
}; 
            
// add the filter 
add_filter( 'woocommerce_coupon_is_valid', 'filter_woocommerce_coupon_is_valid', 10, 2 ); 

function cut_points_on_checkout($null = null,$order) {
    global $woocommerce;
    $user_id =get_current_user_id();
    if(!$user_id) return;
    $coupons  = $woocommerce->cart->get_applied_coupons();
    foreach($coupons as $coupon){
        $coupon = get_page_by_title($coupon, $output = OBJECT, $post_type = 'shop_coupon' );
        $qz_req_points = get_post_meta($coupon->ID, '_qz_req_points', true);
        $total_points = get_user_meta($user_id, '_qz_user_points', true);
        $total_points = ($total_points - $qz_req_points);
        // print_r($total_points); exit;
        update_user_meta( $user_id, '_qz_user_points', $total_points);
    }
    return; 
}
add_filter( 'woocommerce_create_order', 'cut_points_on_checkout', 10, 2);


