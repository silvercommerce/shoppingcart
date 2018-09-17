<% require css('silvercommerce/shoppingcart: client/dist/css/shoppingcart.css') %>

<div class="col-sm-12 content-container typography shoppingcart">
    <h1><%t SilverCommercec\ShoppingCart.CartName 'Shopping Cart' %></h1>

    <% if $Items.exists %>
        <div class="shoppingcart-form">
            $CartForm
        </div>

        <hr/>

        <div class="row line">
            <div class="unit size2of3 col-xs-12 col-md-8">
                <% if $SiteConfig.ShowCartDiscountForm %>
                    <div class="checkout-cart-discounts line units-row end">
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
                    
                    <hr/>
                <% end_if %>

                <% if isDeliverable %>
                    <div class="row line">
                        <% if $SiteConfig.ShowCartPostageForm && $PostageForm %>
                            <div class="size10f2 col-xs-12 col-sm-6 checkout-cart-postage">
                                $PostageForm
                            </div>
                        <% else %>
                            <br/>
                        <% end_if %>
                    </div>
                <% end_if %>
            </div>

            <div class="unit size1of3 col-xs-12 col-md-4">
                <table class="shoppingcart-total-table table">
                    <tr class="subtotal">
                        <td class="text-right">
                            <strong>
                                <%t SilverCommercec\ShoppingCart.SubTotal 'Sub Total' %>
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
                                    <%t SilverCommercec\ShoppingCart.Discounts 'Discounts' %>
                                </strong>
                            </td>
                            <td class="text-right">
                                $DiscountTotal.Nice
                            </td>
                        </tr>
                    <% end_if %>

                    <% if $isDeliverable %>
                        <tr class="shipping">
                            <td class="text-right">
                                <strong>
                                    <%t SilverCommercec\ShoppingCart.Postage 'Postage' %>
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
                                    <%t SilverCommercec\ShoppingCart.Tax 'Tax' %>
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
                                <%t SilverCommercec\ShoppingCart.CartTotal 'Total' %>
                            </strong>
                        </td>
                        <td class="text-right">
                            {$Total.Nice}
                        </td>
                    </tr>
                </table>
                
                <p class="checkout-cart-proceed line units-row end">
                    <a href="{$Link('checkout')}" class="btn btn-green btn-big btn-lg btn-success">
                        <%t SilverCommercec\ShoppingCart.CartProceed 'Proceed to Checkout' %>
                    </a>
                </p>
            </div>
        </div>
    <% else %>
        <p>
            <strong>
                <%t SilverCommercec\ShoppingCart.CartIsEmpty 'Your cart is currently empty' %>
            </strong>
        </p>
    <% end_if %>
</div>
