Better Together Discount Module for Zen Cart 1.3
Version 2.5
--------------------------------------------------
Released under the GNU General Public License
See license.txt file.
--------------------------------------------------
Author: Scott Wilson
http://www.thatsoftwareguy.com 
Zen Forum PM swguy
Donations welcome at http://donate.thatsoftwareguy.com/

Even more information on this contribution is provided at 

http://www.thatsoftwareguy.com/zencart_better_together.html

and see also 

http://www.thatsoftwareguy.com/zencart_better_together_world.html

Please read the FAQ on this page before posting to the forum.

No warranties expressed or implied; use at your own risk.

This module should only be used with ZenCart 1.3.5 or higher. 
If you are still running an earlier verison of ZenCart, please upgrade
before installing this module.  Alternately, you may use Better Together
version 1.1, which is compatible with ZenCart 1.3.0 or higher.

History: 
2.5  06/01/2013 - Added one to many feature. 
2.4  04/16/2013 - Internal - Consolidated all fixes for variants, plus 
                  Code Inspection.  NO FUNCTIONAL CHANGES.
2.3b 09/01/2011 - TwoForOne marketing data issues on listing pages.
2.3a 07/23/2010 - Fixed formatting issue in marketing text.
2.3  05/01/2010 - Added cross sells.
2.2  04/03/2010 - VAT standardization.
2.1a 02/18/2010 - Minor housekeeping.
2.1  12/16/2009 - added images to marketing text
2.0.1_vat  12/03/2009 - added VAT style recalculate tax 
2.0  09/01/2008 - Added price sorting, add_cat_to_prod
1.6  12/14/2007 - Compatibility with 1.3.8.    
1.5a 09/18/2007 - Fixed Google Checkout issue.
1.5  09/08/2007 - Fixed some messages; consolidated marketing text down to 
                  one div; only display marketing text if module turned on
1.4a 07/31/2007 - Bad file upload
1.4 07/31/2007 - Simplified inclusion of marketing logic by adding it to this 
                 package.  Some minor language improvements.
1.3 12/30/2006 - Adding marketing ability to print discounts in reverse order.
                 (If buy A, get B free is offered, can now show this on 
                 B's page, not just A's page.)
               - Allow products which are ordered in odd quantities to be 
                 used as two-for-ones *and* better together discounts if so
                 specified.  Previously, two-for-one products were not 
                 additionally checked for better together discounts.
1.2 12/03/2006 - Adding explicit two for one support
1.1 09/26/2006 - Second Release (providing PHP 4 compatibility)
1.0 09/15/2006 - First Release 
--------------------------------------------------
Overview: 
The gold standard of online retailing is Amazon.com. Zen Cart store operators
looking to increase their profitability should constantly be asking, "WWAD?"
or "What would Amazon do?"

When you look at an item in Amazon, not only is a cross selling recommendation
made, a discount is offered to persuade the customer to accept the
recommendation.  This mod permits you to offer this type of discounted cross
selling in your Zen Cart.

You may specify

* Buy item <x>, get item <y> at a discount
* Buy item <x>, get an item from category <c> at a discount
* Buy an item from category <a>, get an item from category <c> at a discount
* Buy an item from category <a>, get item <x> at a discount

You may also specify cross sells for any of these variants.  Cross sells
are pairings like the ones listed above, but without a discount.

Discounts may be specified as percentages of the latter item's price or as 
absolute values in the currency you cart uses.

Using these discount specifications, messages are automatically displayed on 
the product_info page offering cross sell offers applicable to that item.

At the moment, linking must be done in code by default. 
If you wish, you may upgrade to the Better Together Admin Panel; see 
http://www.thatsoftwareguy.com/zencart_better_together_admin.html

Detailed Description: 

Linkages are specified in the setup() method at the bottom of the file 
includes/modules/order_total/ot_better_together.php.
Several examples are provided.

In addition to linkages, two for one offers can be done for identical
products.  Linkages will be discussed first.

Four types of linkages may be performed.   The format of each of these
is the same: first identifier (product or category), second identifier
(product or category), "%" or "$" to indicate how discounting is to be done  
(or "X" for a cross sell), and a number, indicating the discount amount.  

Note that where the word "category" is used, it means parent category,
which is not the same as top level category for items in subcategories.

The four calls for the four types of discounting are 
  
* add_prod_to_prod() 
* add_prod_to_cat()
* add_cat_to_cat() 
* add_cat_to_prod()

If a straight two for one discount is what is desired, the calls are

* add_twoforone_prod()
* add_twoforone_cat()


Let's consider two products: product 5 from category 3, and product 2
from category 1.

So suppose you want to offer a 50% discount on product 5 with the purchase
of product 2.   In the setup() function, add the line 

   $this->add_prod_to_prod(2,5,"%", 50); 

Want to make it buy product 2, get product 5 free? 
   $this->add_prod_to_prod(2,5,"%", 100); 

How about buy one product 2, get one free? 
   $this->add_prod_to_prod(2,2,"%", 100); 

If you just want to cross sell product 5 when product 2 is displayed, use

   $this->add_prod_to_prod(2,5,"X", 0); 


Remember product 5 is in category 3. If instead of specifying product 5
in particular, you want to discount any item in category 3 by 20% with the 
purchase of a product 2 item, use

   $this->add_prod_to_cat(2,3,"%", 20); 

Discount can be done in currencies as well.  To offer $7 (or 7 of whatever
currency your cart uses), use 

   $this->add_prod_to_cat(2,3,"$", 7); 

(The "$" is just used to specify currency; your cart's currency settings 
will be respected when computing the discount.)

Remember product 2 is in category 1.  If you want to widen the discount to 
provide a discount of 20% off any item in category 3 when an item from
category 1 is purchased, use 

   $this->add_cat_to_cat(1,3,"%", 20); 

Any number of these discounts may be offered; discount computation will
be done in the order in which your discounts are specified in setup(),
and items will be processed in price order for the first parameter and 
reverse price order for the second.  In other words, the least expensive 
eligible item will be discounted by Better Together.  

(Note that in Better Together 1.x, items were processed in the order in 
which they appeared in the cart.)

Using the examples above, suppose these items are in your cart: 

* 1 - Product 2, category 1
* 2 - Product 10, category 1
* 2 - Product 20, category 3
* 2 - Product 5, category 3

and suppose you have coded these discounts: 

   $this->add_prod_to_prod(2,5,"$", 7); 
   $this->add_cat_to_cat(1,3,"%", 25); 

The following discounts will be computed: 
   - $7 off ONE product 5 because of ONE product 2 (rule 1)
   - 25% each off TWO product 20 because of TWO product 10 (rule 2) 

To get $7 off the second product 5, the customer would need to add 
a second product 2 to the cart.

With the same cart, coding 

   $this->add_cat_to_cat(1,3,"%", 25); 
   $this->add_prod_to_prod(2,5,"$", 7); 

Would compute the following discount: 
  - 25% off ONE product 20 because of ONE product 2 (rule 1)
  - 25% off ONE product 20 because of ONE product 10 (rule 1)
  - 25% off ONE product 5 because of ONE product 10 (rule 1)

Obviously these could be very different discounts!  

To create a two for one discount for product 5, simply code 
         $this->add_twoforone_prod(5);

And to create two for one discount for all products in category 3, code
         $this->add_twoforone_cat(3);

Note the difference between 
         $this->add_twoforone_cat(3);
and 
         $this->add_cat_to_cat(3,3,"%", 100); 

The latter says, "buy any item from category three, and get 100% 
off any other item from category three."  The former says, "all
items in category three are buy one, get an identical item free."
So if a customer bought items 20 and 30 from category three, 
a discount would only be given in the latter case.

To make these discounts visible on your product info page, customize the file
includes/templates/template_default/templates/tpl_product_info_display.php
as described in step 6 below in the installation instructions.  
This step will create text like this: 

   Buy this item, get an item from (link)Levis Jeans free
   Buy this item, get a (link)t-shirt free

The link is created to facilitate the cross-sell.

This step is optional; if you prefer, you can add your own cross-selling text.

If you need help adding custom logic, please go to www.thatsoftwareguy.com
and send me email.  There is a fee for this service based on the complexity
of the requested change.

--------------------------------------------------

Installation Instructions: 
0. Back up everything!  Try this in a test environment prior to installing
it on a live shop.

1. If you already have the Better Together module installed, please 
deinstall your old copy by going to Admin->Modules->Order Total, 
selecting "Better Together" and pressing the "Remove" button.  Make
a note of your settings so you can apply them to the new version.

2. Copy the contents of the folder you have unzipped to 
the root directory of your shop.  NOTE that these files assume
your template name is "custom," so any file with "custom" 
in its path name will need to be renamed if you are using a 
different template name.  

3. Login to admin and in Modules->Order Total you will see 'Better Together' listed along with all the other modules available.

4. Click on 'Better Together' to highlight the module and click on 'Install'

5. Decide on the parameters you wish to use.  The easiest way to do this
is to open a shopping cart in another window, and just start adding 
discounts to includes/modules/order_total/ot_better_together.php.
The discounts are shown on the second step in 
"Your Total" under "Better Together."

6. Customize the tpl_product_info_display.php file to advertise
your discounts.  

6a. Put the file 
includes/templates/template_default/templates/tpl_product_info_display.php
into includes/templates/<Your Template>/templates if you haven't already
done so. 

6b. Put the file 
./includes/templates/custom/templates/tpl_better_together_marketing.php
into includes/templates/<Your Template>/templates 

(If you did step 2 properly, it should already be there.)

Then add this block of code to the tpl_product_info_display.php file 

<?php 
require($template->get_template_dir('/tpl_better_together_marketing.php',DIR_WS_TEMPLATE, $current_page_base,'templates'). '/tpl_better_together_marketing.php');
?>

The placement of this code is a matter of personal preference.
Try placing it below the product description and adjust to your taste.

Note that in version 2.1 and greater, you can use
tpl_better_together_marketing_images.php, i.e. 

<?php 
require($template->get_template_dir('/tpl_better_together_marketing.php',DIR_WS_TEMPLATE, $current_page_base,'templates'). '/tpl_better_together_marketing.php');
?>

if you wish to display images in your marketing text.

The cross sells work differently - since there is no discount to be displayed, they are shown more like a regular Zen Cart centerbox.

For instance, suppose your setup function looked like this:

    function setup() {
      // Add all linkages here
      $this->add_prod_to_prod(3, 83, 'X', 0); 
      $this->add_prod_to_prod(3, 25, 'X', 0); 
    }


When you are on the product info page for product 3, if you 
add the Better Together cross sell centerbox, you will see products 
25 and 83 displayed.

To display this centerbox, simply add the following code to the bottom of your includes/templates/YOUR TEMPLATE/templates/tpl_product_info_display.php file:

<?php
require($template->get_template_dir('/tpl_better_together_xsells.php',DIR_WS_TEMPLATE, 
   $current_page_base,'templates'). '/tpl_better_together_xsells.php');
?>


Note that cross sells, unlike regular Better Together offers, are not bidirectional; in the example above, product 3 will not appear on the page for products 83 and 25 unless you explicitly add a cross sell statement to do this:

      // ... 
      $this->add_prod_to_prod(83, 3, 'X', 0); 
      $this->add_prod_to_prod(25, 3, 'X', 0); 

7. If you wish, get a copy of the Better Together Promotional Page by 
following the links on 

http://www.thatsoftwareguy.com/zencart_better_together.html

This page displays all the Better Together discounts you are offering.
 
New Files
=========
includes/languages/english/modules/order_total/ot_better_together.php
includes/modules/order_total/ot_better_together.php
includes/templates/custom/templates/tpl_better_together_marketing.php
includes/templates/custom/templates/tpl_better_together_marketing_images.php
includes/templates/custom/templates/tpl_better_together_xsells.php
includes/templates/custom/css/stylesheet_bettertogether_product.css


