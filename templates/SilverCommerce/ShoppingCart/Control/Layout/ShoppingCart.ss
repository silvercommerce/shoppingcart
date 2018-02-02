<% require css('silvercommerce/shoppingcart: client/dist/css/shoppingcart.css') %>

<div class="content-container container typography checkout-cart">
    <h1><%t ShoppingCart.CartName 'Shopping Cart' %></h1>

    <% if $Items.exists %>
        <div class="checkout-cart-form">
            $CartForm
        </div>

        <hr/>

        <div class="units-row row line">
            <div class="unit-66 unit size2of3 col-xs-12 col-md-8">
                <% if $ShowDiscountForm %>
                    <div class="checkout-cart-discounts line units-row end">
                        <% if $ShowDiscountForm %>
                            $DiscountForm
                        <% end_if %>
                    </div>
                    
                    <hr/>
                <% end_if %>

                <% if isDeliverable %>
                    <div class="units-row row line">
                        <% if $ClickAndCollect %>
                            <div class="unit-50 size10f2 col-xs-12 col-sm-6 checkout-cart-clickandcollect">
                                <h3>
                                    <%t ShoppingCart.ReceiveGoods "How would you like to receive your goods?" %>
                                </h3>
                                
                                <div class="checkout-delivery-buttons">
                                    <a class="btn btn-primary<% if not $isCollection %> btn-active active<% end_if %> width-100" href="{$Link(setdeliverytype)}/post">
                                        <%t ShoppingCart.Delivered "Delivered" %>
                                    </a>
                                    <a class="btn btn-primary<% if $isCollection %> btn-active active<% end_if %> width-100" href="{$Link(setdeliverytype)}/collect">
                                        <%t ShoppingCart.CollectInstore "Collect Instore" %>
                                    </a>
                                </div>
                            </div>
                        <% end_if %>
                        
                        <% if $PostageForm && not $isCollection %>
                            <div class="unit-50 size10f2 col-xs-12 col-sm-6 checkout-cart-postage">
                                $PostageForm
                            </div>
                        <% else %>
                            <br/>
                        <% end_if %>
                    </div>
                <% end_if %>
            </div>

            <% with $Estimate %>
                <div class="unit-33 unit size1of3 col-xs-12 col-md-4">
                    <table class="checkout-total-table width-100">
                        <tr class="subtotal">
                            <td class="text-right">
                                <strong>
                                    <%t ShoppingCart.SubTotal 'Sub Total' %>
                                </strong>
                            </td>
                            <td class="text-right">
                                {$SubTotal.Nice}
                            </td>
                        </tr>
                        
                        <% if $Discount.exists %>
                            <tr class="discount">
                                <td class="text-right">
                                    <strong>
                                        <%t ShoppingCart.Discount 'Discount' %>
                                    </strong><br/>
                                    ($Discount.Title)
                                </td>
                                <td class="text-right">
                                    {$DiscountAmount.Nice}
                                </td>
                            </tr>
                        <% end_if %>

                        <% if $Up.PostageForm %>
                            <tr class="shipping">
                                <td class="text-right">
                                    <strong>
                                        <%t ShoppingCart.Shipping 'Shipping' %>
                                    </strong>
                                </td>
                                <td class="text-right">
                                    {$PostageCost.Nice}
                                </td>
                            </tr>
                        <% end_if %>
                        
                        <% if $Up.ShowTax %>
                            <tr class="tax">
                                <td class="text-right">
                                    <strong>
                                        <%t ShoppingCart.Tax 'Tax' %>
                                    </strong>
                                </td>
                                <td class="text-right">
                                    {$TaxTotal.Nice}
                                </td>
                            </tr>
                        <% end_if %>
                        
                        <tr class="total">
                            <td class="text-right">
                                <strong class="uppercase bold">
                                    <%t ShoppingCart.CartTotal 'Total' %>
                                </strong>
                            </td>
                            <td class="text-right">
                                {$Total.Nice}
                            </td>
                        </tr>
                    </table>
                    
                    <p class="checkout-cart-proceed line units-row end">
                        <a href="{$BaseHref}checkout/checkout" class="btn btn-green btn-big btn-lg btn-success">
                            <%t ShoppingCart.CartProceed 'Proceed to Checkout' %>
                        </a>
                    </p>
                </div>
            <% end_with %>
        </div>
    <% else %>
        <p>
            <strong>
                <%t ShoppingCart.CartIsEmpty 'Your cart is currently empty' %>
            </strong>
        </p>
    <% end_if %>
</div>
