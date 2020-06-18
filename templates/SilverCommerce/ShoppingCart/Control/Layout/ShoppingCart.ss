<% require css('silvercommerce/shoppingcart: client/dist/css/shoppingcart.css') %>

<div class="col-sm-12 content-container typography shoppingcart">
    <h1><%t SilverCommerce\ShoppingCart.CartName 'Shopping Cart' %></h1>

    <% if $Items.exists %>
        <div class="row line">
            <div class="unit size2of3 col col-xs-12 col-lg-8">
                <div class="shoppingcart-form">
                    $CartForm

                    <hr/>
                </div>

                <div class="row">
                    <% if $SiteConfig.ShowCartDiscountForm %>
                        <div class="checkout-cart-discounts unit col-xs-12 col-lg-6">
                            $DiscountForm

                            <% loop $Discounts %>
                                <ul class="list-unstyled">
                                    <span class="text-muted">
                                        $Title: $Value.Nice 
                                        <a class="pl-2" href="$RemoveLink"><i class="fas fa-window-close"></i></a>
                                    </span>
                                </p>
                            <% end_loop %>
                        </div>
                    <% end_if %>

                    <% if isDeliverable && $SiteConfig.ShowCartPostageForm && $PostageForm %>
                        <div class="checkout-cart-postage unit col-xs-12 col-lg-6">
                            $PostageForm
                        </div>
                    <% end_if %>
                </div>
            </div>

            <div class="unit size1of3 col-xs-12 col-lg-4">
                <table class="shoppingcart-total-table table">
                    <tr class="subtotal">
                        <td class="text-right">
                            <strong>
                                <%t SilverCommerce\ShoppingCart.SubTotal 'Sub Total' %>
                            </strong>
                        </td>
                        <td class="text-right">
                            {$SubTotal.Nice}
                        </td>
                    </tr>
                    
                    <% if $Discounts.exists %>
                        <tr class="discount">
                            <td class="text-right">
                                <strong>
                                    <%t SilverCommerce\ShoppingCart.Discounts 'Discounts' %>
                                </strong>
                            </td>
                            <td class="text-right">
                                {$DiscountTotal.Nice}
                            </td>
                        </tr>
                    <% end_if %>

                    <% if $isDeliverable %>
                        <tr class="shipping">
                            <td class="text-right">
                                <strong>
                                    <%t SilverCommerce\ShoppingCart.Postage 'Postage' %>
                                </strong>
                            </td>
                            <td class="text-right">
                                {$PostagePrice.Nice}
                            </td>
                        </tr>
                    <% end_if %>
                    
                    <% if $ShowTax %>
                        <tr class="tax">
                            <td class="text-right">
                                <strong>
                                    <%t SilverCommerce\ShoppingCart.Tax 'Tax' %>
                                </strong>
                            </td>
                            <td class="text-right">
                                {$TaxTotal.Nice}
                            </td>
                        </tr>
                    <% end_if %>
                    
                    <tr class="total lead text-success">
                        <td class="text-right">
                            <strong class="uppercase bold">
                                <%t SilverCommerce\ShoppingCart.CartTotal 'Total' %>
                            </strong>
                        </td>
                        <td class="text-right">
                            {$Total.Nice}
                        </td>
                    </tr>
                </table>
                
                <p class="checkout-cart-proceed line units-row end">
                    <a href="{$Link('checkout')}" class="btn btn-green btn-big btn-lg btn-success">
                        <%t SilverCommerce\ShoppingCart.CartProceed 'Proceed to Checkout' %>
                    </a>
                </p>
            </div>
        </div>
    <% else %>
        <p>
            <strong>
                <%t SilverCommerce\ShoppingCart.CartIsEmpty 'Your cart is currently empty' %>
            </strong>
        </p>
    <% end_if %>
</div>
