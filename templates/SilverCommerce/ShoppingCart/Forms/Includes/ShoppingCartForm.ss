<form $FormAttributes>
	<% if $Message %>
		<p id="{$FormName}_error" class="message $MessageType">$Message</p>
	<% else %>
		<p id="{$FormName}_error" class="message $MessageType" style="display: none"></p>
	<% end_if %>
    <fieldset class="shoppingcart-items">
		$Fields.dataFieldByName(SecurityID)
		
		<div class="table-responsive">
			<table class="table table-hover">
				<thead>
					<tr>
						<th class="image"></th>
						<th class="description">
							<%t ShoppingCart.Description "Description" %>
						</th>
						<th class="price">
							<%t ShoppingCart.Price "Price" %>
						</th>
						<th class="quantity">
							<%t ShoppingCart.Qty "Qty" %>
						</th>
						<th class="actions"></th>
					</tr>
				</thead>

				<tbody>
					<% loop $Controller.Items %>
						<tr>
							<td>
								<img src="$Image.Fill(75,75).URL" alt="Image.Title">
							</td>
							<td>
								<strong>
									<% if $FindStockItem %><a href="{$FindStockItem.Link}">$Title</a>
									<% else %>$Title<% end_if %>
								</strong><br/>
								<% if $Content %>$Content.Summary(10)<br/><% end_if %>                            
								<% if $Customisations && $Customisations.exists %><div class="small">
									<% loop $Customisations %><div class="{$ClassName}">
										<strong>{$Title}:</strong> {$Value}
										<% if not $Last %></br><% end_if %>
									</div><% end_loop %>
								</div><% end_if %>
							</td>
							<td class="price">
								$getFormattedPrice($ShowPriceWithTax)
							</td>
							<td class="quantity">
								<input
									type="text"
									name="Quantity_{$Key}"
									value="{$Quantity}"
									<% if $Locked %>
									title="<%t ShoppingCart.ItemCannotBeEdited "This item cannot be edited" %>"
									readonly
									<% end_if %>
								/>
							</td>
							<td class="remove">
								<a href="{$Top.Controller.Link('remove')}/{$Key}" class="btn btn-red btn-outline-danger">
									x
								</a>
							</td>
						</tr>
					<% end_loop %>
				</tbody>
			</table>
		</div>
    </fieldset>

    <fieldset class="shoppingcart-actions Actions row justify-content-end">
		<div class="btn-group justify-content-end d-flex col-md-6 align-self-end">
			<a href="$Controller.Link('emptycart')" class="btn btn-outline-danger">
				<%t ShoppingCart.CartEmpty "Empty Your Basket" %>
			</a>
			
			$Actions.dataFieldByName(action_doUpdate).addExtraClass('btn btn-outline-info').removeExtraClass('btn-primary').Field
		</div>
    </fieldset>
</form>
