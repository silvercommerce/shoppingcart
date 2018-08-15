<?php

namespace SilverCommerce\ShoppingCart;

use SilverStripe\Control\Cookie;
use SilverStripe\Security\Security;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverCommerce\OrdersAdmin\Model\LineItem;
use SilverCommerce\OrdersAdmin\Model\LineItemCustomisation;
use SilverCommerce\ShoppingCart\Tasks\CleanExpiredEstimatesTask;
use SilverCommerce\ShoppingCart\Model\ShoppingCart as ShoppingCartModel;
use SilverCommerce\ShoppingCart\Control\ShoppingCart as ShoppingCartController;

/**
 * Factory to handle setting up and interacting with a ShoppingCart
 * object.
 * 
 */
class ShoppingCartFactory
{
    use Injectable;
    use Configurable;

    /**
     * Name of the test cookie used to check if cookies are allowed
     */
    const TEST_COOKIE = "ShoppingCartFactoryTest";

    /**
     * Name of Cookie/Session used to track cart access key 
     */
    const COOKIE_NAME = "ShoppingCart.Key";

    /**
     * The default class that is used by the factroy
     * 
     * @var string
     */
    private static $model = ShoppingCartModel::class;

    /**
     * The default class that is used by the factroy
     * 
     * @var string
     */
    private static $controller = ShoppingCartController::class;

    /**
     * Should the cart globally check for stock levels on items added?
     * Using this setting will ignore individual "Stocked" settings
     * on Shopping Cart Items.
     *
     * @var string
     */
    private static $check_stock_levels = false;

    /**
     * whether or not the cleaning task should be left to a cron job
     *
     * @var boolean
     * @config
     */
    private static $cron_cleaner = false;

    /**
     * The current shopping cart
     * 
     * @var ShoppingCart
     */
    protected $current;

    /**
     * Setup the shopping cart and return an instance
     * 
     * @return ShoppingCart
     **/ 
    public function __construct()
    {
        $cookies = $this->cookiesSupported();
        $member = Security::getCurrentUser();

        $cart = $this->findOrMakeCart();
        
        // If we don't have any discounts, a user is logged in and he has
        // access to discounts through a group, add the discount here
        if (!$cart->getDiscount()->exists() && $member && $member->getDiscount()) {
            $cart->DiscountCode = $member->getDiscount()->Code;
        }

        if (!$this->config()->cron_cleaner) {
            $this->cleanOld();
        }

        $this->current = $cart;
    }

    /**
     * Get the current session from the current request
     * 
     * @return Session
     */
    public function getSession()
    {
        $request = Injector::inst()->get(HTTPRequest::class);
        return $request->getSession();
    }

    /**
     * Either find an existing cart, or create a new one.
     * 
     * @return ShoppingCartModel
     */
    public function findOrMakeCart()
    {
        $cookies = $this->cookiesSupported();
        $session = $this->getSession();
        $classname = self::config()->model;
        $cart = null;
        $write = false;
        $member = Security::getCurrentUser();

        if ($cookies) {
            $cart_id = Cookie::get(self::COOKIE_NAME);
        } else {
            $cart_id = $session->get(self::COOKIE_NAME);
        }

        // Try to get a cart from the the DB
        if (isset($cart_id)) {
            $cart = $classname::get()->find('AccessKey', $cart_id);
        }

        // Does the current member have a cart?
        if (empty($cart) && isset($member) && $member->Cart()->exists()) {
            $cart = $member->Cart();
        }

        // Finally, if nothing is set, create a new instance to return
        if (empty($cart)) {
            $cart = $classname::create();
        }

        return $cart;
    }

    /**
     * Run the task to clean old shopping carts
     * 
     * @return null 
     */
    public function cleanOld()
    {
        $siteconfig = SiteConfig::current_site_config();
        $date = $siteconfig->dbobject("LastEstimateClean");
        $request = Injector::inst()->get(HTTPRequest::class);

        if (!$date || ($date && !$date->IsToday())) {
            $task = Injector::inst()->create(CleanExpiredEstimatesTask::class);
            $task->setSilent(true);
            $task->run($request);
            $siteconfig->LastEstimateClean = DBDatetime::now()->Value;
            $siteconfig->write();
        }
    }

    /**
     * Test to see if the current user supports cookies
     * 
     * @return boolean
     */
    public function cookiesSupported()
    {
        Cookie::set(self::TEST_COOKIE, 1);
        $cookie = Cookie::get(self::TEST_COOKIE);
        Cookie::force_expiry(self::TEST_COOKIE);

        return (empty($cookie)) ? false : true;
    }

    /**
     * Get the current shopping cart
     * 
     * @return ShoppingCart
     */ 
    public function getCurrent()
    {
        return $this->current;
    }

    /**
     * Add an item to the shopping cart. By default this should be a
     * line item, but this method will determine if the correct object
     * has been provided before attempting to add.
     *
     * @param array $item The item to add (defaults to @link LineItem)
     * @param array $customisations (A list of @LineItemCustomisations customisations to provide)
     * 
     * @throws ValidationException
     * @return self
     */
    public function addItem($item, $customisations = [])
    {
        $cart = $this->getCurrent();
        $stock_item = $item->FindStockItem();
        $added = false;

        if (!$item instanceof LineItem) {
            throw new ValidationException(_t(
                "ShoppingCart.WrongItemClass",
                "Item needs to be of class {class}",
                ["class" => LineItem::class]
            ));
        }

        // Start off by writing our item object (if it is
        // not in the DB)
        if (!$item->exists()) {
            $item->write();
        }

        if (!is_array($customisations)) {
            $customisations = [$customisations];
        }

        // Find any item customisation associations
        $custom_association = null;
        $custom_associations = array_merge(
            $item->hasMany(),
            $item->manyMany()
        );

        // Define association of item to customisations
        foreach ($custom_associations as $key => $value) {
            $class = $value::create();
            if ($value instanceof LineItemCustomisation) {
                $custom_association = $key;
                break;
            }
        }

        // Map any customisations to the current item
        if (isset($custom_association)) {
            foreach ($customisations as $customisation) {
                if ($customisation instanceof LineItemCustomisation) {
                    if (!$customisation->exists()) {
                        $customisation->write();
                    }
                    $item->{$custom_association}()->add($customisation);
                }
            }
        }

        // Ensure we update the item key
        $item->write();

        // If the current cart isn't in the DB, save it
        if (!$cart->exists()) {
            $this->save();
        }

        // Check if object already in the cart, update quantity
        // and delete new item
        $existing_item = $cart->Items()->find("Key", $item->Key);

        if (isset($existing_item)) {
            $this->updateItem(
                $existing_item,
                $existing_item->Quantity + $item->Quantity
            );
            $item->delete();
            $added = true;
        }

        // If no update was sucessfull then add item
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

            $cart
                ->Items()
                ->add($item);
        }

        return $this;
    }

    /**
     * Find an existing item and update its quantity
     *
     * @param LineItem $item     the item in the cart to update
     * @param int      $quantity the new quantity
     * 
     * @throws ValidationException
     * @return self
     */
    public function updateItem($item, $quantity)
    {
        $stock_item = $item->FindStockItem();

        if (!$item instanceof LineItem) {
            throw new ValidationException(_t(
                "ShoppingCart.WrongItemClass",
                "Item needs to be of class {class}",
                ["class" => LineItem::class]
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
        $item->write();
        
        return $this;
    }

    /**
     * Remove a LineItem from ShoppingCart
     *
     * @param LineItem $item The item to remove
     * 
     * @return self
     */
    public function removeItem($item)
    {
        if (!$item instanceof LineItem) {
            throw new ValidationException(_t(
                "ShoppingCart.WrongItemClass",
                "Item needs to be of class {class}",
                ["class" => LineItem::class]
            ));
        }

        $item->delete();

        return $this;
    }


    /**
     * Destroy current shopping cart
     * 
     * @return self
     */
    public function delete()
    {
        $cookies = $this->cookiesSupported();
        $cart = $this->getCurrent();

        // Only delete the cart if it has been written to the DB
        if ($cart->exists()) {
            $cart->delete();
        }

        if ($cookies) {
            Cookie::force_expiry(self::COOKIE_NAME);
        } else {
            $session->clear(self::COOKIE_NAME);
        }

        return $this;
    }

    /**
     * Save the current shopping cart, by writing it to the DB and
     * generating a cookie/session (if user not logged in).
     *
     * @return self
     */
    public function save()
    {
        $cookies = $this->cookiesSupported();
        $member = Security::getCurrentUser();
        $cart = $this->getCurrent();
        $cart->write();

        // If the cart exists and the current user's cart doesn't
        // match, they have just logged in, replace their cart with
        // the new one.
        if ($cart->exists() && isset($member) && $member->Cart() != $cart) {
            // Remove existing cart
            if ($member->Cart()->exists()) {
                $member->Cart()->delete();
            }

            $member->CartID = $cart->ID;
            $member->write();

            if ($cookies) {
                Cookie::force_expiry(self::COOKIE_NAME);
            } else {
                $session->clear(self::COOKIE_NAME);
            }
        }

        if (!$member && $cookies) {
            Cookie::set(self::COOKIE_NAME, $cart->AccessKey);
        } elseif (!$member) {
            $session = $this->getSession();
            $session->set(self::COOKIE_NAME, $cart->AccessKey);
        }

        return $this;
    }
}