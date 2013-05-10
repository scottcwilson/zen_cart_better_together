<!-- bof Better Together Marketing -->
<?php 
  // Better Together Discount Marketing
  $value = "ot_better_together.php";
  include_once(zen_get_file_directory(DIR_WS_LANGUAGES . $_SESSION['language'] .
          '/modules/order_total/', $value, 'false'));
  include_once(DIR_WS_MODULES . "order_total/" . $value);
  $discount = new ot_better_together();
  if ($discount->check() > 0) { 
     $resp = $discount->get_discount_info($_GET['products_id'], $current_category_id, true); 
     $rresp = $discount->get_reverse_discount_info($_GET['products_id'], $current_category_id, true); 
     if ( (count($resp) > 0) || (count($rresp) > 0) ) {
        $marketing_data = array_merge($resp, $rresp); 
        echo '<div class="content" id="betterTogetherDiscountPolicy">';
        for ($i=0, $n=count($marketing_data); $i<$n; $i++) {
             echo '<hr />';
             echo '<div class="discountText">'; 
             echo $marketing_data[$i]['data'];
             echo '</div>'; 
             echo '<div class="discountImages">'; 
             echo  
                $marketing_data[$i]['first_href'] .
                $marketing_data[$i]['first_image'] . '</a>' . 
                '<span class="product_plus">+</span>' .
                $marketing_data[$i]['disc_href'] .
                $marketing_data[$i]['second_image'] . '</a>'; 
                if (isset($marketing_data[$i]['bbn_string'])) { 
                   echo '<span class="bbn_button">' . $marketing_data[$i]['bbn_string'] . '</span>';
                }
             echo '</div>'; 
         }
         echo '</div>';
         echo '<br class="clearBoth" />'; 
     }
  }
?>
<!-- eof Better Together Marketing -->
