<?php

namespace SilverCommerce\ShoppingCart\Control;

use SilverStripe\Forms\Form;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Control\Cookie;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Control\Director;
use SilverStripe\Forms\FormAction;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ValidationException;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverCommerce\Checkout\Control\Checkout;
use SilverCommerce\OrdersAdmin\Model\Discount;
use SilverCommerce\OrdersAdmin\Model\Estimate;
use SilverCommerce\OrdersAdmin\Model\LineItem;
use SilverCommerce\Postage\Helpers\PostageOption;
use SilverStripe\CMS\Controllers\ContentController;
use SilverCommerce\OrdersAdmin\Model\LineItemCustomisation;
use SilverCommerce\ShoppingCart\Tasks\CleanExpiredEstimatesTask;
use SilverCommerce\Postage\Forms\PostageForm;

/**
 * Holder for items in the shopping cart and interacting with them, as
 * well as rendering these items into an interface that allows editing
 * of items,
 *
 * @author ilateral (http://www.ilateral.co.uk)
 * @package shoppingcart
 */
class ShoppingCart extends Controller
{

    /**
     * URL Used to access this controller
     *
     * @var string
     * @config
     */
    private static $url_segment = 'shoppingcart';
    
    /**
     * Name of the current controller. Mostly used in templates.
     *
     * @var string
     * @config
     */
    private static $class_name = "ShoppingCart";

    /**
     * Setup default templates for this controller
     *
     * @var array
     */
    protected $templates = [
        "index" => [ShoppingCart::class, "Page"],
        "usediscount" => [ShoppingCart::class . "_usediscount", ShoppingCart::class, "Page"]
    ];
    
    /**
     * Overwrite the default title for this controller which is taken
     * from the translation files. This is used for Title and MetaTitle
     * variables in templates.
     *
     * @var string
     * @config
     */
    private static $title;
    
    /**
     * Class Name of object we use as an assotiated estimate.
     * This defaults to Estimate
     *
     * @var string
     * @config
     */
    private static $estimate_class = Estimate::class;

    /**
     * Class Name of object we use as an assotiated estimate.
     * This defaults to Estimate
     *
     * @var string
     * @config
     */
    private static $checkout_class = Checkout::class;
    
    /**
     * Class Name of item we add to the shopping cart/an estimate.
     * This defaults to OrderItem.
     *
     * @var string
     * @config
     */
    private static $item_class = LineItem::class;

    /**
     * Class Name of a line item customisation that will get added to
     * a line item.
     *
     * @var string
     * @config
     */
    private static $item_customisation_class = LineItemCustomisation::class;
    
    /**
     * Should the cart globally check for stock levels on items added?
     * Using this setting will ignore individual "Stocked" settings
     * on Shopping Cart Items.
     *
     * @var string
     * @config
     */
    private static $check_stock_levels = false;

    /**
     * Show the discount form on the shopping cart
     *
     * @var boolean
     * @config
     */
    private static $show_discount_form = false;

    /**
     * whether or not the cleaning task should be left to a cron job
     *
     * @var boolean
     * @config
     */
    private static $cron_cleaner = false;

    /**
     * An estimate object that this shopping cart is associated
     * with.
     *
     * This estimate is used to calculate things such as Total,
     * Tax, etc.
     *
     * @var Estimate
     */
    protected $estimate;
    
    /**
     * These methods are mapped to sub URLs of this
     * controller.
     *
     * @var array
     */
    private static $allowed_actions = [
        "remove",
        "emptycart",
        "clear",
        "update",
        "usediscount",
        "setdeliverytype",
        "checkout",
        "CartForm",
        "PostageForm",
        "DiscountForm"
    ];
    
    public function getTitle()
    {
        if ($this->config()->title) {
            return $this->config()->title;
        } else {
            _t("SilverCommerce\ShoppingCart.CartName", "Shopping Cart");
        }
    }
    
    public function getMetaTitle()
    {
        return $this->getTitle();
    }
    
    public function getShowDiscountForm()
    {
        return $this->config()->show_discount_form;
    }
    
    public function getEstimate()
    {
        return $this->estimate;
    }
    
    public function setEstimate($estimate)
    {
        $this->estimate = $estimate;
        return $this;
    }
    
    public function getItems()
    {
        return $this->estimate->Items();
    }

    public function getDiscount()
    {
        return $this->estimate->Discount();
    }
    
    public function setDiscount(Discount $discount)
    {
        $this->estimate->DiscountID = $discount->ID;
        return $this;
    }

    public function getPostage()
    {
        return $this->estimate->getPostage();
    }

    public function setPostage(PostageOption $postage)
    {
        $this->estimate->setPostage($postage);
        return $this;
    }
    
    /**
     * Get the link to this controller
     *
     * @param string $action The action you want to add to the link
     * @return string
     */
    public function Link($action = null)
    {
        return Controller::join_links(
            $this->config()->url_segment,
            $action
        );
    }

    /**
     * Get an absolute link to this controller
     *
     * @param string $action The action you want to add to the link
     * @return string
     */
    public function AbsoluteLink($action = null)
    {
        return Director::absoluteURL($this->Link($action));
    }

    /**
     * Get a relative (to the root url of the site) link to this
     * controller
     *
     * @param string $action The action you want to add to the link
     * @return string
     */
    public function RelativeLink($action = null)
    {
        return Controller::join_links(
            Director::baseURL(),
            $this->Link($action)
        );
    }

    public function getSession()
    {
        $request = Injector::inst()->get(HTTPRequest::class);
        return $request->getSession();
    }

    /**
     * Are we collecting the current cart? If click and collect is
     * disabled then this returns false, otherwise checks if the user
     * has set this via a session.
     *
     * @return boolean
     */
    public function isCollection()
    {
        $config = SiteConfig::current_site_config();

        if ($config->EnableClickAndCollect) {
            return $this->estimate->isCollection();
        } else {
            return false;
        }
    }
    
    /**
     * Determine if the current cart contains delivereable items.
     * This is used to determine setting and usage of delivery and
     * postage options in the checkout.
     *
     * @return boolean
     */
    public function isDeliverable()
    {
        return $this->estimate->isDeliverable();
    }
    
    /**
     * Determine if the current cart contains only locked items.
     *
     * @return boolean
     */
    public function isLocked()
    {
        foreach ($this->getItems() as $item) {
            if (!$item->Locked) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get an instance of and intialise the
     * shopping cart.
     *
     * @return ShoppingCart
     */
    public static function get()
    {
        $cart = Injector::inst()
            ->create(ShoppingCart::class);
        
        $cart->extend("onBeforeInit");
        $cart->init();
        $cart->extend("onAfterInit");

        return $cart;
    }
    
    /**
     * Build the shopping cart from an estimate.
     *
     * If a user has logged in and also has items in a session, then
     * push these items into a saved estimate.
     *
     */
    public function init()
    {
        parent::init();

        $member = Security::getCurrentUser();
        $contact = null;
        $estimate_class = self::config()->estimate_class;
        $estimate_id = Cookie::get('ShoppingCart.EstimateID');
        $estimate = null;
        $write = false;

        if ($member) {
            $contact = $member->Contact();
        }

        // If the current member doesn't have a cart, set one
        // up, else get their estimate or create a blank one
        // (if no member).
        if (!empty($member) && !$member->getCart() && !$estimate_id) {
            $estimate = $estimate_class::create();
            $estimate->ShoppingCart = true;
            $write = true;
        } elseif (!empty($member) && $member->getCart()) {
            $estimate = $member->getCart();
        } elseif ($estimate_id) {
            $estimate = $estimate_class::get()->find('AccessKey', $estimate_id);
        }

        if (!$estimate) {
            $estimate = $estimate_class::create();
            $estimate->Cart = true;
            $write = true;
        }

        if (!empty($contact) && $contact->exists() && $estimate->CustomerID != $contact->ID) {
            $estimate->CustomerID = $contact->ID;
            $write = true;
        }

        if ($write) {
            $estimate->write();
        }

        // Get any saved items from a session
        if ($estimate_id && $estimate_id != $estimate->ID) {
            $old_est = $estimate_class::get()->find('AccessKey', $estimate_id);
            
            if ($old_est) {
                $items = $old_est->Items();

                // If the current member has an estimate, but also session items
                // add to the order
                foreach ($items as $item) {
                    $existing = $estimate
                        ->Items()
                        ->find("Key", $item->Key);
                    
                    if (!$existing) {
                        if ($member) {
                            $item->write();
                        }

                        $estimate
                            ->Items()
                            ->add($item);
                    }

                    if ($item->Customisation) {
                        $data = unserialize($item->Customisation);
                        if ($data instanceof ArrayList) {
                            foreach ($data as $data_item) {
                                $item
                                    ->Customisations()
                                    ->push($data_item);
                            }
                        }
                    }
                }

                $old_est->delete();
                Cookie::force_expiry('ShoppingCart.EstimateID');
                Cookie::force_expiry('ShoppingCart_EstimateID');
            }

        }

        // Set our estimate to this cart
        if (!$member) {
            Cookie::set('ShoppingCart.EstimateID', $estimate->AccessKey);
        }
        
        // If we don't have any discounts, a user is logged in and he has
        // access to discounts through a group, add the discount here
        if (!$estimate->Discount()->exists() && $member && $member->getDiscount()) {
            $estimate->DiscountID = $member->getDiscount()->ID;
            $estimate->write();
        }

        $this->setEstimate($estimate);

        if (!$this->config()->cron_cleaner) {
            $siteconfig = SiteConfig::current_site_config();
            $date = $siteconfig->dbobject("LastEstimateClean");
            if (!$date || ($date && !$date->IsToday())) {
                $task = Injector::inst()->create(CleanExpiredEstimatesTask::class);
                $task->setSilent(true);
                $task->run($this->getRequest());
                $siteconfig->LastEstimateClean = DBDatetime::now()->Value;
                $siteconfig->write();
            }
        }
    }
    
    /**
     * Return a rendered button for the shopping cart
     *
     * @return string
     */
    public function getViewCartButton()
    {
        return $this->renderWith('SilverCommerce\ShoppingCart\Includes\ViewCartButton');
    }

    /**
     * If content controller exists, return it's menu function
     * @param int $level Menu level to return.
     * @return ArrayList
     */
    public function getMenu($level = 1)
    {
        if (class_exists(ContentController::class)) {
            $controller = ContentController::singleton();
            return $controller->getMenu($level);
        }
    }

    public function Menu($level)
    {
        return $this->getMenu();
    }
    
    /**
     * Default acton for the shopping cart
     */
    public function index()
    {
        $this->extend("onBeforeIndex");

        return $this->render();
    }
    
    /**
     * Remove a product from ShoppingCart Via its ID. This action
     * expects an ID to be sent through the URL that matches a specific
     * key added to an item in the cart
     *
     * @return Redirect
     */
    public function remove()
    {
        $key = $this->request->param('ID');
        $title = "";
        
        if (!empty($key)) {
            $item = $this->getItems()->find("Key", $key);

            if ($item) {
                $title = $item->Title;
                $item->delete();
                $this->save();
                
                $form = $this->CartForm();
                $form->sessionMessage(_t(
                    "ShoppingCart.RemovedItem",
                    "Removed '{title}' from your cart",
                    ["title" => $title]
                ));
            }
            
        }
        
        return $this->redirectBack();
    }
    
    /**
     * Action that will clear shopping cart and associated items
     *
     */
    public function emptycart()
    {
        $this->extend("onBeforeEmpty");
        
        $this->clear();
        
        $form = $this->CartForm();
        $form->sessionMessage(_t(
            "ShoppingCart.EmptiedCart",
            "Shopping cart emptied"
        ));
        
        return $this->redirectBack();
    }
    
    
    /**
     * Action used to add a discount to the users session via a URL.
     * This is preferable to using the dicount form as disount code
     * forms seem to provide a less than perfect user experience
     *
     */
    public function usediscount()
    {   
        $code_to_search = $this->request->param("ID");
        $code = false;
        $curr = $this->getDiscount();
        
        if (!$code_to_search) {
            return $this->httpError(404, "Page not found");
        }
        
        // First check if the discount is already added (so we don't
        // query the DB if we don't have to).
        if (!$curr || ($curr && $curr->Code != $code_to_search)) {
            $code = Discount::get()
                ->filter("Code", $code_to_search)
                ->exclude("Expires:LessThan", date("Y-m-d"))
                ->first();
            
            if ($code) {
                $this->setDiscount($code);
                $this->save();
            }
        } elseif ($curr && $code->Code == $code_to_search) {
            $code = $this->getDiscount();
        }

        $this->extend("onBeforeUseDiscount");

        return $this
            ->customise([
                "Discount" => $code
            ])->render();
    }
    
    
    /**
     * Set the current session to click and collect (meaning no shipping)
     *
     * @return Redirect
     */
    public function setdeliverytype()
    {
        $type = $this->request->param("ID");
        $actions = array_keys(Estimate::config()->get("actions"));

        if ($type && in_array($type, $actions)) {
            $estimate = $this->getEstimate();
            $estimate->Action = $type;
            $estimate->PostageID = 0;
            $this->save();
        }
        
        $this->extend("onBeforeSetDeliveryType");
        
        $this->redirectBack();
    }
    
    /**
     * Add an item to the shopping cart. By default this should be a
     * line item, but this mothod will determine if the correct object
     * has been provided before attempting to add.
     *
     * @param array $item The item to add (defaults to @link LineItem)
     * @param array $customisations (A list of @LineItemCustomisations customisations to provide)
     * @throws ValidationException
     * @return ShoppingCart
     */
    public function add($item, $customisations = [])
    {
        $estimate = $this->getEstimate();
        $stock_item = $item->FindStockItem();
        $item_class = $this->config()->item_class;
        $item_customisation_class = $this->config()->item_customisation_class;
        $added = false;

        if (!$item instanceof $item_class) {
            throw new ValidationException(_t(
                "ShoppingCart.WrongItemClass",
                "Item needs to be of class {class}",
                ["class" => $item_class]
            ));
        }

        if (!is_array($customisations)) {
            $customisations = [$customisations];
        }

        // Start off by writing our item object (if it is
        // not in the DB)
        if (!$item->exists()) {
            $item->write();
        }

        // Find item customisation association
        $custom_association = null;
        $custom_associations = array_merge(
            $item->hasMany(),
            $item->manyMany()
        );

        foreach ($custom_associations as $key => $value) {
            if ($value == $item_customisation_class) {
                $custom_association = $key;
                break;
            }
        }

        if (isset($custom_association)) {
            foreach ($customisations as $customisation) {
                if ($customisation instanceof $item_customisation_class) {
                    if (!$customisation->exists()) {
                        $customisation->write();
                    }
                    $item->{$custom_association}()->add($customisation);
                }
            }
        }

        $item->write();

        // Check if object already in the cart, update quantity
        // and delete new item
        $existing_item = $estimate->Items()->find("Key", $item->Key);
        if ($existing_item) {
            $this->update($existing_item, $existing_item->Quantity + $item->Quantity);
            $item->delete();
            $added = true;
        }

        // If no update was sucessfull then add to cart items
        if (!$added) {
            // If we need to track stock, do it now
            if ($stock_item && ($stock_item->Stocked || $this->config()->check_stock_levels)) {
                if ($item->checkStockLevel($item->Quantity) < 0) {
                    throw new ValidationException(_t(
                        "ShoppingCart.NotEnoughStock",
                        "There are not enough '{title}' in stock",
                        ['title' => $stock_item->Title]
                    ));
                }
            }

            $this->extend("onBeforeAdd", $item);

            $estimate
                ->Items()
                ->add($item);
        }
            
        $this->save();

        return $this;
    }

    /**
     * Find an existing item and update its quantity
     *
     * @param Item the item in the cart to update
     * @param Quantity the new quantity
     * @throws ValidationException
     * @return ShoppingCart
     */
    public function update($item, $quantity)
    {
        $item_class = $this->config()->item_class;
        $stock_item = $item->FindStockItem();

        if (!$item instanceof $item_class) {
            throw new ValidationException(_t(
                "ShoppingCart.WrongItemClass",
                "Item needs to be of class {class}",
                ["class" => $item_class]
            ));
        }
        
        if ($item->Locked) {
            throw new ValidationException(_t(
                "ShoppingCart.UnableToEditItem",
                "Unable to change item's quantity"
            ));
        }

        // If we need to track stock, do it now
        if ($stock_item && ($stock_item->Stocked || $this->config()->check_stock_levels)) {
            if ($item->checkStockLevel($quantity) < 0) {
                throw new ValidationException(_t(
                    "ShoppingCart.NotEnoughStock",
                    "There are not enough '{title}' in stock",
                    ['title' => $stock_item->Title]
                ));
            }
        }
        
        $item->Quantity = floor($quantity);
        
        $this->extend("onBeforeUpdate", $item);

        $item->write();
        $this->save();
        
        return $this;
    }
    
    /**
     * Empty the shopping cart object of all items.
     *
     */
    public function removeAll()
    {
        foreach ($this->getItems() as $item) {
            $item->delete();
        }
    }
    
    /**
     * Save the current products list and postage to a session.
     *
     */
    public function save()
    {
        // Extend our save operation
        $this->extend("onBeforeSave");
        
        $estimate = $this->getEstimate();
        $estimate->write();

        // Extend our save operation
        $this->extend("onAfterSave");
    }
    
    /**
     * Clear the shopping cart object and destroy sessions/cookies
     *
     */
    public function clear()
    {
        $estimate = $this->getEstimate();
        $this->removeAll();
        $estimate->clearPostage();
        $estimate->DiscountID = 0;
        $this->save();
    }

    /**
     * Setup the checkout and redirect to it
     *
     * @return Redirect
     */
    public function checkout()
    {
        if (!class_exists($this->config()->checkout_class)) {
            return $this->httpError(404);
        }

        $estimate = $this->getEstimate();
        $checkout = Injector::inst()
            ->get($this->config()->checkout_class);
        $checkout->setEstimate($estimate);

        $this->extend("onBeforeCheckout");
        
        $this->redirect($checkout->Link());
    }
    
    /**
     * Shortcut to checkout config, to allow us to access it via
     * templates
     *
     * @return boolean
     */
    public function ShowTax()
    {
        $config = SiteConfig::current_site_config();
        return $config->ShowPriceAndTax;
    }

    /**
     * Form responsible for listing items in the shopping cart and
     * allowing management (such as addition, removal, etc)
     *
     * @return Form
     */
    public function CartForm()
    {   
        $form = Form::create(
            $this,
            "CartForm",
            FieldList::create(),
            FieldList::create(
                FormAction::create(
                    'doUpdate',
                    _t('ShoppingCart.UpdateCart', 'Update Cart')
                )->addExtraClass('btn btn-info')
            )
        )->setTemplate("SilverCommerce\ShoppingCart\Forms\Includes\ShoppingCartForm");
        
        $this->extend("updateCartForm", $form);
        
        return $form;
    }
    
    /**
     * Form that allows you to add a discount code which then gets added
     * to the cart's list of discounts.
     *
     * @return Form
     */
    public function DiscountForm()
    {
        $form = Form::create(
            $this,
            "DiscountForm",
            FieldList::create(
                TextField::create(
                    "DiscountCode",
                    _t("ShoppingCart.DiscountCode", "Discount Code")
                )->setAttribute(
                    "placeholder",
                    _t("ShoppingCart.EnterDiscountCode", "Enter a discount code")
                )
            ),
            FieldList::create(
                FormAction::create(
                    'doAddDiscount',
                    _t('ShoppingCart.Add', 'Add')
                )->addExtraClass('btn btn-info')
            )
        );
        
        $this->extend("updateDiscountForm", $form);
        
        return $form;
    }
    
    /**
     * Form responsible for estimating shipping based on location and
     * postal code
     *
     * @return Form
     */
    public function PostageForm()
    {
        if ($this->isDeliverable()) {
            $form = PostageForm::create(
                $this,
                "PostageForm",
                $this->estimate,
                $this->estimate->SubTotal,
                $this->estimate->TotalWeight,
                $this->estimate->TotalItems
            );

            $form->setLegend(_t(
                "SilverCommerce\ShoppingCart.EstimatePostage",
                "Estimate Postage"
            ));

            // Extension call
            $this->extend("updatePostageForm", $form);

            return $form;
        }
    }

    /**
     * Action that will update cart
     *
     * @param type $data
     * @param type $form
     */
    public function doUpdate($data, $form)
    {
        try {
            foreach ($this->getItems() as $item) {
                foreach ($data as $key => $value) {
                    $sliced_key = explode("_", $key);
                    if ($sliced_key[0] == "Quantity") {
                        if (isset($item) && ($item->Key == $sliced_key[1])) {
                            if ($value > 0) {
                                $this->update($item, $value);
                            } else {
                                $item->delete();
                            }

                            $form->sessionMessage(
                                _t("ShoppingCart.UpdatedShoppingCart", "Shopping cart updated"),
                                ValidationResult::TYPE_GOOD
                            );
                        }
                    }
                }
            }
        } catch (ValidationException $e) {
            $form->sessionMessage(
                $e->getMessage()
            );
        } catch (Exception $e) {
            $form->sessionMessage(
                $e->getMessage()
            );
        }
        
        $this->save();
        
        return $this->redirectBack();
    }
    
    /**
     * Action that will find a discount based on the code
     *
     * @param type $data
     * @param type $form
     */
    public function doAddDiscount($data, $form)
    {
        $code_to_search = $data['DiscountCode'];
        
        // First check if the discount is already added (so we don't
        // query the DB if we don't have to).
        if ($this->getDiscount()->Code != $code_to_search) {
            $code = Discount::get()
                ->filter("Code", $code_to_search)
                ->exclude("Expires:LessThan", date("Y-m-d"))
                ->first();
            
            if ($code) {
                $this->setDiscount($code);
                $this->save();
            }
        }
        
        return $this->redirectBack();
    }
}
