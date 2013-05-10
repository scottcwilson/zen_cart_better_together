<?php
/**
 * Better Together Discounts for Zen Cart.
 * An order_total module
 * By Scott Wilson (swguy)
 * http://www.thatsoftwareguy.com
 * Version 2.4 
 * URL: http://www.thatsoftwareguy.com/zencart_better_together.html
 *
 * @copyright Copyright 2006-2013, That Software Guy
 * @copyright Portions Copyright 2004-2006 Zen Cart Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

/**
 * Do a comparison which will ensure that the items are priced from highest to lowest.
 * @param $a - first item
 * @param $b - second item
 * @return int - 0 = same; 1 = a is lower; -1 = b  is lower.
 */
function bt_cmp($a, $b) {
   if ($a['final_price'] == $b['final_price'])
      return 0;
   if ($a['final_price'] < $b['final_price'])
      return 1;
   return -1;
}

// Make it pretty obvious that there's a problem
function bailout($str) {
   trigger_error($str);
   die($str);
}

/**
 * Better Together linkage types
 */
define('PROD_TO_PROD', '1');
define('PROD_TO_CAT', '2');
define('CAT_TO_CAT', '3');
define('CAT_TO_PROD', '4');

define('TWOFER_PROD', '11');
define('TWOFER_CAT', '12');

/**
 * Better Together discount class.  For discounts other than twofers.
 */
class bt_discount {
   var $ident1; // Product id or category
   var $ident2; // Product id or category
   var $type; // % or $ or X
   var $amt; // numerical amount
   var $flavor; // PROD_TO_PROD, PROD_TO_CAT, CAT_TO_CAT, CAT_TO_PROD
   var $isvalid;

   /**
    * Initialization function
    * @param $ident1 - first item in linkage
    * @param $ident2 - second  item in linkage
    * @param $type - percent or dollars off
    * @param $amt - amount - dollar or percentage amount to be deducted
    * @param $flavor - see defines above.  PROD_TO_PROD, etc.
    */
   function init($ident1, $ident2, $type, $amt, $flavor) {
      $this->isvalid = 0;
      if ($type != "$" && $type != "%" && $type != 'X') {
         bailout("Bad type " . $type);
      }
      if ($flavor != PROD_TO_PROD && $flavor != PROD_TO_CAT && $flavor != CAT_TO_PROD && $flavor != CAT_TO_CAT
      ) {
         bailout("Bad flavor " . $flavor);
      }
      $this->ident1 = $ident1; // Product id or category
      $this->ident2 = $ident2; // Product id or category
      $this->type = $type; // % or $ or X
      $this->amt = $amt; // numerical amount
      $this->flavor = $flavor; // PROD_TO_PROD, PROD_TO_CAT, CAT_TO_CAT, CAT_TO_PROD
      $this->isvalid = 1;
   }

   function getid() {
      return $this->ident1;
   }
}

/**
 * Better Together twofer discount class
 */
class bt_twofer {
   var $ident1; // Product or category id
   var $ident2; // Product or category id
   var $flavor; // Can only be TWOFER_PROD or TWOFER_CAT
   var $isvalid;

   function init($ident1, $flavor) {
      $this->isvalid = 0;
      if ($flavor != TWOFER_PROD && $flavor != TWOFER_CAT
      ) {
         bailout("Bad flavor " . $flavor);
      }
      $this->ident1 = $ident1; // Product id or category
      $this->ident2 = $ident1; // Product id or category
      $this->flavor = $flavor; // Is the twofer for a product or a category?
      $this->isvalid = 1;
   }

   function getid() {
      return $this->ident1;
   }
}

class ot_better_together {
   var $title, $output;

   function ot_better_together() {
      $this->code = 'ot_better_together';
      $this->title = MODULE_ORDER_TOTAL_BETTER_TOGETHER_TITLE;
      $this->description = MODULE_ORDER_TOTAL_BETTER_TOGETHER_DESCRIPTION;
      /** @noinspection PhpUndefinedConstantInspection */
      $this->sort_order = MODULE_ORDER_TOTAL_BETTER_TOGETHER_SORT_ORDER;
      /** @noinspection PhpUndefinedConstantInspection */
      $this->include_tax = MODULE_ORDER_TOTAL_BETTER_TOGETHER_INC_TAX;
      /** @noinspection PhpUndefinedConstantInspection */
      $this->calculate_tax = MODULE_ORDER_TOTAL_BETTER_TOGETHER_CALC_TAX;
      $this->credit_class = true;
      $this->output = array();
      $this->discountlist = array();
      $this->xselllist = array();
      $this->twoferlist = array();
      $this->nocontext = 0;
      $this->setup();
   }

   /**
    * Determines if a buy both now should be permitted on this item.
    * Note that it only executes if the checkbbn_pi function exists; this function is
    * included in premium modules such as Buy Both Now, which allows a 2 item purchase
    * with one click from the product info page.
    * @param $ident1 - first item
    * @param $ident2 - second item
    * @param $bbn_string - String to be used in Buy Both Now upsell
    * @return bool - true if buy both now is permitted
    */
   function checkbbn($ident1, $ident2, &$bbn_string) {
      if (function_exists('checkbbn_pi')) {
         return checkbbn_pi($ident1, $ident2, $bbn_string);
      }
      return false;
   }

   /**
    * Similar to checkbbn but for Buy Now (allows a 2 item purchase from the shopping
    * cart page within the context of Checkout Candy.)
    * @param $ident - item to be purchased
    * @param $flavor - PROD_TO_PROD, etc.
    * @param $ident_id - is "ident" item 1 or 2?
    * @return bool
    */
   function bnok($ident, $flavor, $ident_id) {
      if (function_exists('bnok_pi')) {
         return bnok_pi($ident, $flavor, $ident_id);
      }
      return false;
   }

   /**
    * Set for invocations of internal functions by external modules such as Checkout Candy.
    */
   function setnocontext() {
      $this->nocontext = 1;
   }

   /**
    * Called from setup() - creates a 2 for 1 prod discount
    * @param $ident1 - product to be discounted
    */
   function add_twoforone_prod($ident1) {
      $d = new bt_twofer;
      $d->init($ident1, TWOFER_PROD);
      if ($d->isvalid == 1) {
         $this->twoferlist[] =& $d;
      }
   }

   /**
    * Called from setup() - creates a 2 for 1 cat discount
    * @param $ident1 - cat to be discounted
    */
   function add_twoforone_cat($ident1) {
      $d = new bt_twofer;
      $d->init($ident1, TWOFER_CAT);
      if ($d->isvalid == 1) {
         $this->twoferlist[] =& $d;
      }
   }

   /**
    * Called from setup() - creates a prod to prod discount
    * @param $ident1 - First product
    * @param $ident2 - Second product
    * @param $type - $, % or X
    * @param $amt - amount off (if type is $ or %)
    */
   function add_prod_to_prod($ident1, $ident2, $type, $amt) {
      $d = new bt_discount;
      $d->init($ident1, $ident2, $type, $amt, PROD_TO_PROD);
      if ($d->isvalid == 1) {
         if ($type == 'X') {
            $this->xselllist[] =& $d;
         }
         else {
            $this->discountlist[] =& $d;
         }
      }
   }

   /**
    * Called from setup() - creates a prod to cat discount
    * @param $ident1 - First product
    * @param $ident2 - Second category product
    * @param $type - $, % or X
    * @param $amt - amount off (if type is $ or %)
    */
   function add_prod_to_cat($ident1, $ident2, $type, $amt) {
      $d = new bt_discount;
      $d->init($ident1, $ident2, $type, $amt, PROD_TO_CAT);
      if ($d->isvalid == 1) {
         if ($type == 'X') {
            $this->xselllist[] =& $d;
         }
         else {
            $this->discountlist[] =& $d;
         }
      }
   }

   /**
    * Called from setup() - creates a cat to cat discount
    * @param $ident1 - First category
    * @param $ident2 - Second category
    * @param $type - $, % or X
    * @param $amt - amount off (if type is $ or %)
    */
   function add_cat_to_cat($ident1, $ident2, $type, $amt) {
      $d = new bt_discount;
      $d->init($ident1, $ident2, $type, $amt, CAT_TO_CAT);
      if ($d->isvalid == 1) {
         if ($type == 'X') {
            $this->xselllist[] =& $d;
         }
         else {
            $this->discountlist[] =& $d;
         }
      }
   }

   /**
    * Called from setup() - creates a cat to prod discount
    * @param $ident1 - First category
    * @param $ident2 - Second product
    * @param $type - $, % or X
    * @param $amt - amount off (if type is $ or %)
    */
   function add_cat_to_prod($ident1, $ident2, $type, $amt) {
      $d = new bt_discount;
      $d->init($ident1, $ident2, $type, $amt, CAT_TO_PROD);
      if ($d->isvalid == 1) {
         if ($type == 'X') {
            $this->xselllist[] =& $d;
         }
         else {
            $this->discountlist[] =& $d;
         }
      }
   }

   /**
    * Used by external functions to access $currencies and format a figure
    * @param $amount (eg 12.3400)
    * @return string (eg $12.34)
    */
   function print_amount($amount) {
      global $order, $currencies;
      return $currencies->format($amount, true, $order->info['currency'], $order->info['currency_value']);
   }

   /**
    * Computes the discount for this item.  Modifies remaining items to disallow double dipping.
    * @param $discount_item
    * @param $all_items
    * @return float|int - discounted amount.
    */
   function get_discount($discount_item, &$all_items) {
      for ($dis = 0, $n = count($this->discountlist); $dis < $n; $dis++) {
         $li = $this->discountlist[$dis];

         // Based on type, check ident1
         if (($li->flavor == PROD_TO_PROD) || ($li->flavor == PROD_TO_CAT)
         ) {
            if ($li->ident1 != $discount_item['id']) {
               continue;
            }
         }
         else { // CAT_TO_CAT, CAT_TO_PROD
            if ($li->ident1 != $discount_item['category']) {
               continue;
            }
         }

         for ($i = sizeof($all_items) - 1; $i >= 0; $i--) {
            if ($all_items[$i]['quantity'] == 0)
               continue;
            $match = 0;
            if (($li->flavor == PROD_TO_PROD) || ($li->flavor == CAT_TO_PROD)
            ) {
               if ($all_items[$i]['id'] == $li->ident2) {
                  $match = 1;
               }
            }
            else { // CAT_TO_CAT, PROD_TO_CAT
               if ($all_items[$i]['category'] == $li->ident2) {
                  $match = 1;
               }
            }

            if ($match == 1) {
               $all_items[$i]['quantity'] -= 1;
               if ($li->type == "$") {
                  $discount = $li->amt;
               }
               else { // %
                  $discount = $all_items[$i]['final_price'] * $li->amt / 100;
               }
               return $discount;
            }
         }
      }

      return 0;
   }

   /**
    * Determines if the item is eligible for a twofer discount.
    * @param $discount_item
    * @return bool
    */
   function is_twofer($discount_item) {
      for ($dis = 0, $n = count($this->twoferlist); $dis < $n; $dis++) {
         $li = $this->twoferlist[$dis];

         // Based on type, check ident1
         if (($li->flavor == TWOFER_PROD) && ($li->ident1 == $discount_item['id'])
         ) {
            return true;
         }
         else if (($li->flavor == TWOFER_CAT) && ($li->ident1 == $discount_item['category'])
         ) {
            return true;
         }
      }
      return false;
   }

   /**
    * Required by order total modules post 1.3
    * @return array breakdowns of order total
    */
   function get_order_total() {
      global $order;
      $order_total_tax = $order->info['tax'];
      $order_total = $order->info['total'];
      if ($this->include_tax != 'true')
         $order_total -= $order->info['tax'];
      $orderTotalFull = $order_total;
      $order_total = array('totalFull' => $orderTotalFull, 'total' => $order_total, 'tax' => $order_total_tax);

      return $order_total;
   }

   /**
    * Runs the order total module.
    */
   function process() {
      global $order, $currencies;

      $od_amount = $this->calculate_deductions();
      if ($od_amount['total'] > 0) {
         reset($order->info['tax_groups']);
         $taxGroups = array_keys($order->info['tax_groups']);
         foreach ($taxGroups as $key) {
            if ($od_amount[$key]) {
               $order->info['tax_groups'][$key] -= $od_amount[$key];
               if ($this->calculate_tax != 'VAT') {
                  $order->info['total'] -= $od_amount[$key];
               }
            }
         }
         $order->info['total'] = $order->info['total'] - $od_amount['total'];
         $this->output[] = array('title' => $this->title . ':', 'text' => '-' . $currencies->format($od_amount['total'], true, $order->info['currency'], $order->info['currency_value']), 'value' => $od_amount['total']);
      }
   }

   /**
    * Determines the discount to be given.
    * @return array deduction and associated taxes.
    */
   function calculate_deductions() {
      global $order;
      $od_amount = array();
      $od_amount['tax'] = 0;

      $products = $_SESSION['cart']->get_products();
      reset($products);
      usort($products, "bt_cmp");
      $discountable_products = array();
      // Build discount list
      for ($i = 0, $n = sizeof($products); $i < $n; $i++) {
         $discountable_products[$i] = $products[$i];
      }

      // Now compute discounts
      $discount = 0;
      for ($i = 0, $n = sizeof($discountable_products); $i < $n; $i++) {
         // Is it a twofer?
         if ($this->is_twofer($discountable_products[$i])) {
            $npairs = (int)($discountable_products[$i]['quantity'] / 2);
            $discountable_products[$i]['quantity'] -= ($npairs * 2);
            $item_discountable = $npairs * $discountable_products[$i]['final_price'];
            if ($this->include_tax == 'true') {
               $discount += $this->gross_up($item_discountable);
            }
            else {
               $discount += $item_discountable;
            }
         }

         // Otherwise, do regular bt processing
         while ($discountable_products[$i]['quantity'] > 0) {
            $discountable_products[$i]['quantity'] -= 1;
            $item_discountable = $this->get_discount($discountable_products[$i], $discountable_products);
            if ($item_discountable == 0) {
               $discountable_products[$i]['quantity'] += 1;
               break;
            }
            else {
               if ($this->include_tax == 'true') {
                  $discount += $this->gross_up($item_discountable);
               }
               else {
                  $discount += $item_discountable;
               }
            }
         }
      }

      $od_amount['total'] = round($discount, 2);
      $taxable_amount = $od_amount['total'];
      switch ($this->calculate_tax) {
         case 'Standard':
            reset($order->info['tax_groups']);
            $taxGroups = array_keys($order->info['tax_groups']);
            foreach ($taxGroups as $key) {
               $tax_rate = zen_get_tax_rate_from_desc($key);
               if ($tax_rate > 0) {
                  $od_amount[$key] = $tod_amount = round((($taxable_amount * $tax_rate)) / 100, 2);
                  $od_amount['tax'] += $tod_amount;
               }
            }
            break;

         case 'VAT': // Really "vat" style for embedded tax
            reset($order->info['tax_groups']);
            $taxGroups = array_keys($order->info['tax_groups']);
            foreach ($taxGroups as $key) {
               $tax_rate = zen_get_tax_rate_from_desc($key);
               if ($tax_rate > 0) {
                  $od_amount[$key] = $tod_amount = $this->gross_down($taxable_amount);
                  $od_amount['tax'] += $tod_amount;
               }
            }
            break;
      }

      return $od_amount;
   }

   /**
    * Reduces a grossed up price by the tax amount.
    * @param $figure - original price
    * @return mixed - net price
    */
   function gross_down($figure) {
      global $order;
      $gross_down_amt = 0;
      reset($order->info['tax_groups']);
      $taxGroups = array_keys($order->info['tax_groups']);
      foreach ($taxGroups as $key) {
         $tax_rate = zen_get_tax_rate_from_desc($key);
         if ($tax_rate > 0) {
            $amt = $figure / (1 + ($tax_rate / 100));
            $gross_down_amt += round($amt, 2);
         }
      }
      return $figure - $gross_down_amt;
   }

   /**
    * Increases a net price by the tax amount
    * @param $net - original price
    * @return float|int - grossed up price
    */
   function gross_up($net) {
      global $order;
      $gross_up_amt = 0;
      reset($order->info['tax_groups']);
      $taxGroups = array_keys($order->info['tax_groups']);
      foreach ($taxGroups as $key) {
         $tax_rate = zen_get_tax_rate_from_desc($key);
         if ($tax_rate > 0) {
            $gross_up_amt += round((($net * $tax_rate)) / 100, 2);
         }
      }
      return $gross_up_amt + $net;
   }

   /**
    * Required for order total modules
    * @param $order_total
    * @return mixed
    */
   function pre_confirmation_check(
      /** @noinspection PhpUnusedParameterInspection */
      $order_total) {
      $od_amount = $this->calculate_deductions();
      return $od_amount['total'] + $od_amount['tax'];
   }

   /**
    * Required for order total modules
    */
   function credit_selection() {
      return;
   }

   /**
    * Required for order total modules
    */
   function collect_posts() {
   }

   /**
    * Required for order total modules
    */
   function update_credit_account($i) {
   }

   /**
    * Required for order total modules
    */
   function apply_credit() {
   }

   /**
    * Determines whether module is enabled
    * @return bool - true if on, false otherwise
    */
   function check() {
      global $db;
      if (!isset($this->_check)) {
         $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_ORDER_TOTAL_BETTER_TOGETHER_STATUS'");
         $this->_check = $check_query->RecordCount();
      }

      return $this->_check;
   }

   /**
    * Gets list of database configuration items
    * @return array list of keys for module
    */
   function keys() {
      return array('MODULE_ORDER_TOTAL_BETTER_TOGETHER_STATUS', 'MODULE_ORDER_TOTAL_BETTER_TOGETHER_SORT_ORDER', 'MODULE_ORDER_TOTAL_BETTER_TOGETHER_INC_TAX', 'MODULE_ORDER_TOTAL_BETTER_TOGETHER_CALC_TAX');
   }

   /**
    * Inserts config items in the database in their default state.
    */
   function install() {
      global $db;
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('&copy; That Software Guy<br /><div><a href=\"http://www.thatsoftwareguy.com/zencart_better_together.html\" target=\"_blank\">Help</a> - View the Documentation</div><br />This module is installed', 'MODULE_ORDER_TOTAL_BETTER_TOGETHER_STATUS', 'true', '', '6', '1','zen_cfg_select_option(array(\'true\'), ', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'MODULE_ORDER_TOTAL_BETTER_TOGETHER_SORT_ORDER', '292', 'Sort order of display.', '6', '2', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function ,date_added) values ('Include Tax', 'MODULE_ORDER_TOTAL_BETTER_TOGETHER_INC_TAX', 'false', 'Include Tax in calculation.', '6', '3','zen_cfg_select_option(array(\'true\', \'false\'), ', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function ,date_added) values ('Re-calculate Tax', 'MODULE_ORDER_TOTAL_BETTER_TOGETHER_CALC_TAX', 'Standard', 'Re-Calculate Tax', '6', '4','zen_cfg_select_option(array(\'None\', \'Standard\', \'VAT\', \'Credit Note\'), ', now())");
   }

   /**
    * Removes configuration items from the database.
    */
   function remove() {
      global $db;
      $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
   }

   /**
    * For the product info page - shows cross selling information
    * @param $id
    * @param $cat
    * @return array
    */
   function get_xsell_info($id, $cat) {
      $response_arr = array();

      for ($dis = 0, $n = count($this->xselllist); $dis < $n; $dis++) {
         $li = $this->xselllist[$dis];
         $match = 0;
         $second_image = '';
         $disc_href = '';
         $name = '';
         if (($li->flavor == PROD_TO_PROD) && ($li->ident1 == $id)
         ) {
            $match = 1;
            $disc_href = '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident2) . '">';
            $second_image = zen_get_products_image($li->ident2);
            $name = zen_get_products_name($li->ident2, $_SESSION['languages_id']);
         }
         else if (($li->flavor == PROD_TO_CAT) && ($li->ident1 == $id)
         ) {
            $match = 1;
            $disc_href = '<a href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident2) . '">';
            /** @noinspection PhpUndefinedConstantInspection */
            $second_image = zen_image(DIR_WS_IMAGES . zen_get_categories_image($li->ident2), zen_get_category_name($li->ident2, $_SESSION['languages_id']), SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT);
            $name = zen_get_category_name($li->ident2, $_SESSION['languages_id']);
         }
         else if (($li->flavor == CAT_TO_CAT) && ($li->ident1 == $cat)
         ) {
            $match = 1;
            $disc_href = '<a href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident2) . '">';
            /** @noinspection PhpUndefinedConstantInspection */
            $second_image = zen_image(DIR_WS_IMAGES . zen_get_categories_image($li->ident2), zen_get_category_name($li->ident2, $_SESSION['languages_id']), SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT);
            $name = zen_get_category_name($li->ident2, $_SESSION['languages_id']);
         }
         else if (($li->flavor == CAT_TO_PROD) && ($li->ident1 == $cat)
         ) {
            $match = 1;
            $disc_href = '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident2) . '">';
            $second_image = zen_get_products_image($li->ident2);
            $name = zen_get_products_name($li->ident2, $_SESSION['languages_id']);
         }

         if ($match == 1) {
            $info['image'] = $second_image;
            $info['link'] = $disc_href;
            $info['ident'] = $li->ident2;
            $info['name'] = $name;
            $response_arr[] = $info;
         }
      }
      return $response_arr;
   }

   /**
    * For the product information page - show discounts associated with this product
    * @param $id
    * @param $cat
    * @param bool $usearray - newer versions of the marketing text use arrays.
    * @return array
    */
   function get_discount_info($id, $cat, $usearray = false) {
      global $order, $currencies;
      $response_arr = array();

      for ($dis = 0, $n = count($this->twoferlist); $dis < $n; $dis++) {
         $li = $this->twoferlist[$dis];
         $match = 0;
         $bbn = false;
         $bbn_string = '';
         $disc_string = $first_image = $second_image = $first_href = $disc_href = '';
         if (($li->flavor == TWOFER_PROD) && ($li->ident1 == $id)
         ) {
            $match = 1;
            if ($this->nocontext == 0) {
               $disc_string = TWOFER_PROMO_STRING;
               // Can we buy both now?
               if ($this->nocontext == 0) {
                  if ($this->checkbbn($li->ident1, $li->ident1, $bbn_string)) {
                     $bbn = true;
                  }
               }
            }
            else {
               $disc_link = '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident1) . '">' . zen_get_products_name($li->ident1, $_SESSION['languages_id']) . '</a>';
               $disc_string = QUALIFY;
               $disc_string .= GET_THIS;
               $disc_string .= SECOND;
               $disc_string .= $disc_link;
               $disc_string .= FREE;
            }

            $first_href = '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident1) . '">';
            $first_image = zen_get_products_image($li->ident1);
            $disc_href = '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident1) . '">';
            $second_image = zen_get_products_image($li->ident1);
         }
         else if (($li->flavor == TWOFER_CAT) && ($li->ident1 == $cat)
         ) {
            $match = 1;
            if ($this->nocontext == 0) {
               $disc_string = TWOFER_CAT_PROMO_STRING;
            }
            else {
               $disc_link = '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $id) . '">' . zen_get_products_name($id, $_SESSION['languages_id']) . '</a>';
               $disc_string = QUALIFY;
               $disc_string .= GET_THIS;
               $disc_string .= SECOND;
               $disc_string .= $disc_link;
               $disc_string .= FREE;
            }

            $first_href = '<a href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident1) . '">';
            /** @noinspection PhpUndefinedConstantInspection */
            $first_image = zen_image(DIR_WS_IMAGES . zen_get_categories_image($li->ident1), zen_get_category_name($li->ident1, $_SESSION['languages_id']), SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT);
            $disc_href = '<a href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident1) . '">';
            /** @noinspection PhpUndefinedConstantInspection */
            $second_image = zen_image(DIR_WS_IMAGES . zen_get_categories_image($li->ident1), zen_get_category_name($li->ident1, $_SESSION['languages_id']), SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT);
         }
         if ($match == 1) {
            if ($usearray) {
               $info['data'] = $disc_string;

               $info['first_image'] = $first_image;
               $info['second_image'] = $second_image;
               $info['first_href'] = $first_href;
               $info['ident1'] = $li->ident1;
               $info['ident2'] = $li->ident2;
               $info['disc_href'] = $disc_href;
               $info['ident1_bnok'] = $this->bnok($li->ident1, $li->flavor, 1);
               $info['ident2_bnok'] = $this->bnok($li->ident1, $li->flavor, 1);
               if ($bbn) {
                  $info['bbn_string'] = $bbn_string;
               }
               $response_arr[] = $info;
            }
            else {
               $response_arr[] = $disc_string;
            }
         }
      }

      for ($dis = 0, $n = count($this->discountlist); $dis < $n; $dis++) {
         $li = $this->discountlist[$dis];
         $match = 0;
         $bbn = false;
         $disc_link = '';
         $first_href = '';
         $first_image = '';
         $disc_href = '';
         $second_image = '';
         $bbn_string = '';
         if (($li->flavor == PROD_TO_PROD) && ($li->ident1 == $id)
         ) {
            $match = 1;
            // Can we buy both now?
            if ($this->nocontext == 0) {
               if ($this->checkbbn($li->ident1, $li->ident2, $bbn_string)) {
                  $bbn = true;
               }
            }
            $disc_link = '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident2) . '">' . zen_get_products_name($li->ident2, $_SESSION['languages_id']) . '</a>';
            $first_href = '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident1) . '">';
            $first_image = zen_get_products_image($li->ident1);
            $disc_href = '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident2) . '">';
            $second_image = zen_get_products_image($li->ident2);

         }
         else if (($li->flavor == PROD_TO_CAT) && ($li->ident1 == $id)
         ) {
            $match = 1;
            $disc_link = '<a href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident2) . '">' . zen_get_category_name($li->ident2, $_SESSION['languages_id']) . '</a>';
            $first_href = '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident1) . '">';
            $first_image = zen_get_products_image($li->ident1);
            $disc_href = '<a href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident2) . '">';
            /** @noinspection PhpUndefinedConstantInspection */
            $second_image = zen_image(DIR_WS_IMAGES . zen_get_categories_image($li->ident2), zen_get_category_name($li->ident2, $_SESSION['languages_id']), SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT);
         }
         else if (($li->flavor == CAT_TO_CAT) && ($li->ident1 == $cat)
         ) {
            $match = 1;
            $disc_link = '<a href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident2) . '">' . zen_get_category_name($li->ident2, $_SESSION['languages_id']) . '</a>';
            $first_href = '<a href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident1) . '">';
            /** @noinspection PhpUndefinedConstantInspection */
            $first_image = zen_image(DIR_WS_IMAGES . zen_get_categories_image($li->ident1), zen_get_category_name($li->ident1, $_SESSION['languages_id']), SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT);
            $disc_href = '<a href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident2) . '">';
            /** @noinspection PhpUndefinedConstantInspection */
            $second_image = zen_image(DIR_WS_IMAGES . zen_get_categories_image($li->ident2), zen_get_category_name($li->ident2, $_SESSION['languages_id']), SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT);
         }
         else if (($li->flavor == CAT_TO_PROD) && ($li->ident1 == $cat)
         ) {
            $match = 1;
            $disc_link = '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident2) . '">' . zen_get_products_name($li->ident2, $_SESSION['languages_id']) . '</a>';
            $first_href = '<a href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident1) . '">';
            /** @noinspection PhpUndefinedConstantInspection */
            $first_image = zen_image(DIR_WS_IMAGES . zen_get_categories_image($li->ident1), zen_get_category_name($li->ident1, $_SESSION['languages_id']), SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT);
            $disc_href = '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident2) . '">';
            $second_image = zen_get_products_image($li->ident2);
         }

         if ($match == 1) {
            if ($this->nocontext == 0)
               $disc_string = BUY_THIS_ITEM;
            else
               $disc_string = QUALIFY;
            if (($li->flavor == PROD_TO_PROD) || ($li->flavor == CAT_TO_PROD)
            ) {
               $disc_string .= GET_THIS;
            }
            else {
               $disc_string .= GET_ANY;
            }
            if (($li->flavor == PROD_TO_PROD) && ($li->ident1 == $li->ident2) && ($this->nocontext == 0)
            ) {
               $disc_string .= SECOND_ONE;
            }
            else {
               $disc_string .= $disc_link;
            }
            $disc_string .= " ";
            if ($li->type == "%") {
               if ($li->amt != 100) {
                  $str_amt = $li->amt . "%";
                  $off_string = sprintf(OFF_STRING_PCT, $str_amt);
               }
               else {
                  $off_string = FREE_STRING;
               }
               $disc_string .= $off_string;
            }
            else {
               $curr_string = $currencies->format($li->amt, true, $order->info['currency'], $order->info['currency_value']);
               $off_string = sprintf(OFF_STRING_CURR, $curr_string);
               $disc_string .= $off_string;
            }
            if ($usearray) {
               $info['data'] = $disc_string;

               $info['first_image'] = $first_image;
               $info['second_image'] = $second_image;
               $info['first_href'] = $first_href;
               $info['disc_href'] = $disc_href;
               $info['ident1'] = $li->ident1;
               $info['ident2'] = $li->ident2;
               $info['flavor'] = $li->flavor;
               $info['ident1_bnok'] = $this->bnok($li->ident1, $li->flavor, 1);
               $info['ident2_bnok'] = $this->bnok($li->ident2, $li->flavor, 2);
               if ($bbn) {
                  $info['bbn_string'] = $bbn_string;
               }
               $response_arr[] = $info;
            }
            else {
               $response_arr[] = $disc_string;
            }
         }
      }
      return $response_arr;
   }

   /**
    * Used on product info page to show what discounts would be available if you
    * purchased this product.
    * @param $id
    * @param $cat
    * @param bool $usearray
    * @return array
    */
   function get_reverse_discount_info($id, $cat, $usearray = false) {
      global $order, $currencies;
      $response_arr = array();

      for ($dis = 0, $n = count($this->discountlist); $dis < $n; $dis++) {
         $li = $this->discountlist[$dis];
         $match = 0;
         $bbn = false;
         $disc_link = '';
         $first_href = '';
         $first_image = '';
         $disc_href = '';
         $second_image = '';
         $bbn_string = '';
         if ($li->ident2 == $li->ident1) {
            continue;
         }
         $this_string = REV_GET_DISC;
         if (($li->flavor == PROD_TO_PROD) && ($li->ident2 == $id)
         ) {
            $match = 1;
            $disc_link = '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident1) . '">' . zen_get_products_name($li->ident1, $_SESSION['languages_id']) . '</a>';
            $first_href = '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident1) . '">';
            $first_image = zen_get_products_image($li->ident1);
            $disc_href = '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident2) . '">';
            $second_image = zen_get_products_image($li->ident2);
            if ($this->nocontext == 1) {
               $this_string = GET_YOUR_PROD . zen_get_products_name($li->ident2, $_SESSION['languages_id']);
            }

            // Can we buy both now?
            if ($this->nocontext == 0) {
               if ($this->checkbbn($li->ident1, $li->ident2, $bbn_string)) {
                  $bbn = true;
               }
            }
         }
         else if (($li->flavor == PROD_TO_CAT) && ($li->ident2 == $cat)
         ) {
            $match = 1;
            $disc_link = '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident1) . '">' . zen_get_products_name($li->ident1, $_SESSION['languages_id']) . '</a>';
            $first_href = '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident1) . '">';
            $first_image = zen_get_products_image($li->ident1);
            $disc_href = '<a href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident2) . '">';
            /** @noinspection PhpUndefinedConstantInspection */
            $second_image = zen_image(DIR_WS_IMAGES . zen_get_categories_image($li->ident2), zen_get_category_name($li->ident2, $_SESSION['languages_id']), SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT);
            if ($this->nocontext == 1) {
               $this_string = GET_YOUR_CAT . zen_get_category_name($li->ident2, $_SESSION['languages_id']);
            }
         }
         else if (($li->flavor == CAT_TO_CAT) && ($li->ident2 == $cat)
         ) {
            $match = 1;
            $disc_link = '<a href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident1) . '">' . zen_get_category_name($li->ident1, $_SESSION['languages_id']) . '</a>';
            $first_href = '<a href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident1) . '">';
            /** @noinspection PhpUndefinedConstantInspection */
            $first_image = zen_image(DIR_WS_IMAGES . zen_get_categories_image($li->ident1), zen_get_category_name($li->ident1, $_SESSION['languages_id']), SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT);
            $disc_href = '<a href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident2) . '">';
            /** @noinspection PhpUndefinedConstantInspection */
            $second_image = zen_image(DIR_WS_IMAGES . zen_get_categories_image($li->ident2), zen_get_category_name($li->ident2, $_SESSION['languages_id']), SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT);
            if ($this->nocontext == 1) {
               $this_string = GET_YOUR_CAT . zen_get_category_name($li->ident2, $_SESSION['languages_id']);
            }
         }
         else if (($li->flavor == CAT_TO_PROD) && ($li->ident2 == $id)
         ) {
            $match = 1;
            $disc_link = '<a href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident1) . '">' . zen_get_category_name($li->ident1, $_SESSION['languages_id']) . '</a>';
            $first_href = '<a href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . $li->ident1) . '">';
            /** @noinspection PhpUndefinedConstantInspection */
            $first_image = zen_image(DIR_WS_IMAGES . zen_get_categories_image($li->ident1), zen_get_category_name($li->ident1, $_SESSION['languages_id']), SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT);
            $disc_href = '<a href="' . zen_href_link(FILENAME_PRODUCT_INFO, 'products_id=' . $li->ident2) . '">';
            $second_image = zen_get_products_image($li->ident2);
            if ($this->nocontext == 1) {
               $this_string = GET_YOUR_PROD . zen_get_products_name($li->ident2, $_SESSION['languages_id']);
            }
         }
         if ($match == 1) {
            if (($li->flavor == PROD_TO_PROD) || ($li->flavor == PROD_TO_CAT)
            ) {
               $disc_string = REV_GET_THIS;
            }
            else { // CAT_TO_CAT, CAT_TO_PROD
               $disc_string = REV_GET_ANY;
            }
            $disc_string .= $disc_link;
            $disc_string .= $this_string;
            if ($li->type == "%") {
               if ($li->amt != 100) {
                  $str_amt = $li->amt . "%";
                  $off_string = sprintf(OFF_STRING_PCT, $str_amt);
               }
               else {
                  $off_string = FREE_STRING;
               }
               $disc_string .= $off_string;
            }
            else {
               $curr_string = $currencies->format($li->amt, true, $order->info['currency'], $order->info['currency_value']);
               $off_string = sprintf(OFF_STRING_CURR, $curr_string);
               $disc_string .= $off_string;
            }
            if ($usearray) {
               $info['data'] = $disc_string;
               $info['first_image'] = $first_image;
               $info['second_image'] = $second_image;
               $info['first_href'] = $first_href;
               $info['disc_href'] = $disc_href;
               $info['ident1'] = $li->ident1;
               $info['ident2'] = $li->ident2;
               $info['flavor'] = $li->flavor;
               $info['ident1_bnok'] = $this->bnok($li->ident1, $li->flavor, 1);
               $info['ident2_bnok'] = $this->bnok($li->ident2, $li->flavor, 2);
               if ($bbn) {
                  $info['bbn_string'] = $bbn_string;
               }
               $response_arr[] = $info;
            }
            else {
               $response_arr[] = $disc_string;
            }
         }
      }
      return $response_arr;
   }


   /**
    * Configuration is done in this function (unless Better Together Admin is used - see
    * http://www.thatsoftwareguy.com/zencart_better_together_admin.html
    */
   function setup() {
      // Using Better Together Admin?  Uncomment this out
      /*
               if (!IS_ADMIN_FLAG) {
                  require(DIR_WS_MODULES . 'better_together_admin.php');
               }
      */

      // Add all linkages here
      // Some examples are provided:

      /*
               $this->add_prod_to_prod(3, 83, 'X', 0);

               $this->add_prod_to_prod(3, 3, "%", 100);
               $this->add_prod_to_prod(3, 83, "%", 100);
               $this->add_prod_to_prod(3, 27, "%", 100);
               $this->add_cat_to_prod(4, 3, "%", 100);
               $this->add_cat_to_prod(4, 83, "%", 100);
               // Buy product 83, get product 53 at 50% off
               $this->add_prod_to_prod(83, 53, "%", 50);

               // Buy product 83, get one free
               $this->add_prod_to_prod(83, 83, "%", 100);

               // Buy product 83, get an item from category 14 free
               $this->add_prod_to_cat(83, 14, "%", 100);

               // Buy an item from category 21, get an item from category 14 free
               $this->add_cat_to_cat(21, 14, "%", 100);

               // Buy item 12, get a second one free.
               $this->add_twoforone_prod(12);

               // Buy any item from category 10, get a second identical one free
               $this->add_twoforone_cat(10);

               // $this->add_twoforone_prod(17);
               $this->add_prod_to_prod(26, 27, "%", 100);
               $this->add_prod_to_prod(83, 15, "%", 50);
               $this->add_prod_to_prod(83, 20, "%", 25);
               $this->add_cat_to_cat(14, 14, "%", 100);
               $this->add_prod_to_prod(3, 25, "%", 50);
      */
   }

}

