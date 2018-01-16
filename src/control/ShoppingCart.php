<?php

namespace SilverCommerce\ShoppingCart\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\Cookie;
use SilverStripe\Security\Member;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\i18n\i18n;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Control\HTTPRequest;
use SilverCommerce\OrdersAdmin\Model\Estimate;
use SilverCommerce\OrdersAdmin\Model\LineItem;
use SilverCommerce\OrdersAdmin\Model\Discount;
use SilverCommerce\OrdersAdmin\Model\PostageArea;
use SilverCommerce\OrdersAdmin\Tools\ShippingCalculator;
use SilverCommerce\ShoppingCart\Tasks\CleanExpiredEstimatesTask;

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
     * Specifies the flag for collection 
     *
     * @var string
     */
    const COLLECTION = 'collect';

    /**
     * Specifiec the flag for delivery
     *
     * @var string
     */
    const DELIVERY = 'deliver';

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
     * flag for collection
     *
     * @var string
     * @config
     */
    private static $collection = "collect";
    
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
     * Class Name of item we add to the shopping cart/an estimate.
     * This defaults to OrderItem
     *
     * @var string
     * @config
     */
    private static $item_class = LineItem::class;
    
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
        "CartForm",
        "PostageForm",
        "DiscountForm"
    ];

    /**
     * Getters and setters
     *
     */
    public function getClassName()
    {
        return self::config()->class_name;
    }
    
    public function getTitle()
    {
        return ($this->config()->title) ? $this->config()->title : _t("ShoppingCart.CartName", "Shopping Cart");
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
        return $this->estimate->Postage();
    }

    public function setPostage(PostageArea $postage)
    {
        $this->estimate->PostageID = $postage->ID;
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
     * Get any postage items that have been set
     *
     * @return ArrayList
     */
    public function getAvailablePostage()
    {
        $session = $this->getSession();
        $postage = $session->get("ShoppingCart.AvailablePostage");

        if (!$postage) {
            $postage = ArrayList::create();
        }

        return $postage;
    }
    
    /**
     * Set postage that is available to the shopping cart based on the
     * country and zip code submitted
     *
     * @param $country 2 character country code
     * @param $code Zip or Postal code
     * @return ShoppingCart
     */
    public function setAvailablePostage($country, $code)
    {
        $session = $this->getSession();
        $postage_areas = new ShippingCalculator($code, $country);

        $postage_areas
            ->setCost($this->SubTotalCost)
            ->setWeight($this->TotalWeight)
            ->setItems($this->TotalItems);

        $postage_areas = $postage_areas->getPostageAreas();

        $this->extend('updateAvailablePostage',$postage_areas);

        $session->set("ShoppingCart.AvailablePostage", $postage_areas);

        // If current postage is not available, clear it.
        $postage_id = $session->get("ShoppingCart.PostageID");

        if (!$postage_areas->find("ID", $postage_id)) {
            if ($postage_areas->exists()) {
                $session->set("ShoppingCart.PostageID", $postage_areas->first()->ID);
            } else {
                $session->clear("ShoppingCart.PostageID");
            }
        }

        return $this;
    }
    
    /**
     * Are we collecting the current cart? If click and collect is
     * disabled then this returns false, otherwise checks if the user
     * has set this via a session.
     *
     * @return Boolean
     */
    public function isCollection()
    {
        $config = SiteConfig::current_site_config();
        $session = $this->getSession();

        if ($config->EnableClickAndCollext) {
            $type = $session->get("ShoppingCart.Delivery");
            return ($type == self::COLLECTION) ? true : false;
        } else {
            return false;
        }
    }
    
    /**
     * Determine if the current cart contains delivereable items.
     * This is used to determine setting and usage of delivery and
     * postage options in the checkout.
     *
     * @return Boolean
     */
    public function isDeliverable()
    {
        $deliverable = false;
        
        foreach ($this->getItems() as $item) {
            if ($item->Deliverable) {
                $deliverable = true;
            }
        }
        
        return $deliverable;
    }
    
    /**
     * Determine if the current cart contains only locked items.
     *
     * @return Boolean
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
     * Shortcut for ShoppingCart::create, exists because create()
     * doesn't seem quite right.
     *
     * @return ShoppingCart
     */
    public static function get()
    {
        return Injector::inst()->create(ShoppingCart::class);
    }
    
    /**
     * Build the shopping cart from an estimate.
     *
     * If a user has logged in and also has items in a session, then
     * push these items into a saved estimate.
     *
     */
    public function __construct()
    {
        parent::__construct();

        $member = Member::currentUser();
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
        if ($member && !$member->getCart() && !$estimate_id) {
            $estimate = $estimate_class::create();
            $estimate->ShoppingCart = true;
            $write = true;
        } elseif ($member && $member->getCart()) {
            $estimate = $member->getCart();
        } elseif ($estimate_id) {
            $estimate = $estimate_class::get()->byID($estimate_id);
        }

        if (!$estimate) {
            $estimate = $estimate_class::create();
            $estimate->Cart = true;
            Debug::show($estimate);
            $write = true;
        }

        if ($contact && $estimate->CustomerID != $contact->ID) {
            $estimate->CustomerID = $contact->ID;
            $write = true;
        }

        if ($write) {
            $estimate->write();
        }

        // Get any saved items from a session
        if ($estimate_id && $estimate_id != $estimate->ID) {
            $old_est = $estimate_class::get()->byID($estimate_id);
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
            }

        }

        // Set our estimate to this cart
        if (!$member) {
            Cookie::set('ShoppingCart.EstimateID', $estimate->ID);
        }
        
        // If we don't have any discounts, a user is logged in and he has
        // access to discounts through a group, add the discount here
        if (!$estimate->Discount()->exists() && $member && $member->getDiscount()) {
            $estimate->DiscountID = $member->getDiscount()->ID;
            $estimate->write();
        }

        $this->setEstimate($estimate);
        
        // Allow extension of the shopping cart after initial setup
        $this->extend("augmentSetup");
    }
    
    /**
     * Return a rendered button for the shopping cart
     *
     * @return string
     */
    public function getViewCartButton()
    {
        return $this->renderWith('ViewCartButton');
    }

    public function init()
    {
        parent::init();

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
                $form->sesionMessage(_t(
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
        $form->sesionMessage(_t(
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
        $session = $this->getSession();
        
        if ($type && in_array($type, [self::COLLECTION, self::DELIVERY])) {
            $session->set("ShoppingCart.Delivery", $type);
            $this->getEstimate()->PostageID = 0;
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

        foreach($customisations as $customisation) {
            $item->Customisations()->add($customisation);
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
        
        $member = Member::currentUser();
        $contact = $member->Contact();
        $estimate = $this->getEstimate();
        $session = $this->getSession();
        
        // Update available postage (or clear any set if not deliverable)
        $data = $session->get("Form.Form_PostageForm.data");
        if ($data && is_array($data) && $this->isDeliverable()) {
            $country = $data["Country"];
            $code = $data["ZipCode"];
            $this->setAvailablePostage($country, $code);
        } else {
            $estimate->PostageID = 0;
        }

        $estimate->write();

        // Extend our save operation
        $this->extend("onAfterSave");
    }
    
    /**
     * Clear the shopping cart object and destroy sesions/cookies
     *
     */
    public function clear()
    {
        $estimate = $this->getEstimate();
        $this->removeAll();
        $estimate->PostageID = 0;
        $estimate->DiscountID = 0;
        $this->save();
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
        )->setTemplate("ShoppingCartForm");
        
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
            $available_postage = $this->getAvailablePostage();
            $session = $this->getSession();
            
            // Setup form
            $form = Form::create(
                $this,
                'PostageForm',
                $fields = FieldList::create(
                    DropdownField::create(
                        'Country',
                        _t('ShoppingCart.Country', 'Country'),
                        i18n::getData()->getCountries()
                    )->setEmptyString(""),
                    TextField::create(
                        "ZipCode",
                        _t('Checkout.ZipCode', "Zip/Postal Code")
                    )
                ),
                $actions = FieldList::create(
                    FormAction::create(
                        "doSetPostage",
                        _t('Checkout.Search', "Search")
                    )->addExtraClass('btn')
                    ->addExtraClass('btn btn-green btn-success')
                ),
                $required = RequiredFields::create(array(
                    "Country",
                    "ZipCode"
                ))
            )->setLegend(_t(
                "ShoppingCart.EstimateShipping",
                "Estimate Shipping"
            ));

            // If we have stipulated a search, then see if we have any results
            // otherwise load empty fieldsets
            if ($available_postage->exists()) {
                // Loop through all postage areas and generate a new list
                $postage_array = array();
                
                foreach ($available_postage as $area) {
                    $area_currency = new Currency("Cost");
                    $area_currency->setValue($area->Cost);
                    $postage_array[$area->ID] = $area->Title . " (" . $area_currency->Nice() . ")";
                }
                
                $fields->add(OptionsetField::create(
                    "PostageID",
                    _t('ShoppingCart.SelectPostage', "Select Postage"),
                    $postage_array
                ));
                
                $actions
                    ->dataFieldByName("action_doSetPostage")
                    ->setTitle(_t('ShoppingCart.Update', "Update"));
            }
            
            // Check if the form has been re-posted and load data
            $data = $session->get("Form.{$form->FormName()}.data");
            if (is_array($data)) {
                $form->loadDataFrom($data);
            }
            
            // Check if the postage area has been set, if so, Set Postage ID
            $data = array();
            $data["PostageID"] = $session->get("Checkout.PostageID");
            if (is_array($data)) {
                $form->loadDataFrom($data);
            }
            
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
    
    /**
     * Method that deals with get postage details and setting the
     * postage
     *
     * @param $data
     * @param $form
     */
    public function doSetPostage($data, $form)
    {
        $session = $this->getSession();
        $country = $data["Country"];
        $code = $data["ZipCode"];
        
        $this->setAvailablePostage($country, $code);        
        $areas = $this->getAvailablePostage();
        $postage = null;
        
        // Check that postage is set, if not, see if we can set a default
        if (array_key_exists("PostageID", $data) && $data["PostageID"]) {
            // First is the current postage ID in the list of postage
            // areas
            $postage = $areas->find("ID", $data["PostageID"]);
            $id = 0;

            if ($postage) {
                $data["PostageID"] = $postage->ID;
            }
        } elseif ($areas->exists()) {
            $postage = $areas->first();
            $data["PostageID"] = $postage->ID;
        }

        if ($postage) {
            $this->setPostage($postage);
        }

        // Set the form pre-populate data before redirecting
        $session->set("Form.{$form->FormName()}.data", $data);
        
        $url = Controller::join_links(
            $this->Link(),
            "#{$form->FormName()}"
        );
        
        return $this->redirect($url);
    }
}
