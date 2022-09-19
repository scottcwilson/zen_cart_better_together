<?php 
// Better Together Cross Sells by That Software Guy www.thatsoftwareguy.com
// Requires Better Together 2.3 or greater.
// Copyright 2010, That Software Guy, Inc.
  $row = 0;
  $col = 0;
  $max_xsells_per_row = 3;
  $list_box_contents = array();
  $show_xsells = false; 
  $value = "ot_better_together.php";
  include_once(zen_get_file_directory(DIR_WS_LANGUAGES . $_SESSION['language'] .
          '/modules/order_total/', $value, 'false'));
  include_once(DIR_WS_MODULES . "order_total/" . $value);
  $discount = new ot_better_together();
  if ($discount->check() > 0) { 
     $marketing_data = $discount->get_xsell_info($_GET['products_id'], $current_category_id); 
     $num_marketing = count($marketing_data);
     if ($num_marketing > 0) {
        $show_xsells = true; 
        if ($num_marketing < $max_xsells_per_row) {
          $col_width = floor(100/$num_marketing);
        } else {
          $col_width = floor(100/$max_xsells_per_row);
        }
        for ($i=0, $n=count($marketing_data); $i<$n; $i++) {
            $list_box_contents[$row][$col] = array('params' => 'class="centerBoxContentsBTXSell"' . ' ' . 'style="width:' . $col_width . '%;"',
      'text' => $marketing_data[$i]['link'] . $marketing_data[$i]['image'] . "</a>" . '<br />' . $marketing_data[$i]['link'] . $marketing_data[$i]['name'] . '</a>'); 
             $col ++;
             if ($col > ($max_xsells_per_row - 1)) {
               $col = 0;
               $row ++;
             }

         }
     }
  }
?>
<?php
if ($show_xsells) { 
?>
<div class="centerBoxWrapper" id="BTXSell">
<?php
    $title = '<h2 class="centerBoxHeading">' . TEXT_BETTER_TOGETHER_XSELLS . '</h2>';
  require($template->get_template_dir('tpl_columnar_display.php',DIR_WS_TEMPLATE, $current_page_base,'common'). '/tpl_columnar_display.php');
?>
</div>
<?php
}
?>
