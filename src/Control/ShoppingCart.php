<?php

namespace SilverCommerce\ShoppingCart\Control;

use Exception;
use SilverStripe\i18n\i18n;
use SilverStripe\Forms\Form;
use SilverStripe\View\SSViewer;
use SilverStripe\Forms\FieldList;
use SilverStripe\Control\Director;
use SilverStripe\Forms\FormAction;
use SilverStripe\Control\Controller;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\ORM\ValidationException;
use SilverCommerce\Checkout\Control\Checkout;
use SilverCommerce\Postage\Forms\PostageForm;
use SilverCommerce\Discounts\Model\AppliedDiscount;
use SilverStripe\CMS\Controllers\ContentController;
use SilverCommerce\Discounts\Forms\DiscountCodeForm;
use SilverCommerce\ShoppingCart\ShoppingCartFactory;

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
    private static $checkout_class = Checkout::class;

    /**
     * Redirect the user to the cart when an item is added?
     *
     * @var boolean
     */
    private static $redirect_on_add = false;

    /**
     * The associated dataRecord
     *
     * @var ShoppingCartModel
     */
    protected $dataRecord;

    /**
     * These methods are mapped to sub URLs of this
     * controller.
     *
     * @var array
     */
    private static $allowed_actions = [
        "remove",
        "emptycart",
        "usediscount",
        "setdeliverytype",
        "checkout",
        'removediscount',
        "CartForm",
        "PostageForm",
        "DiscountForm"
    ];

    /**
     * Overwrite default init to support subsites (if installed)
     *
     * @return void
     */
    protected function init()
    {
        parent::init();

        // Setup current controller on init (as Security::getMember()
        // is not available on construction)
        $dataRecord = ShoppingCartFactory::create()->getOrder();
        $this->setDataRecord($dataRecord);
        $this->setFailover($this->dataRecord);

        # Check for subsites and add support
        if (class_exists(Subsite::class)) {
            $subsite = Subsite::currentSubsite();

            if ($subsite && $subsite->Theme) {
                SSViewer::add_themes([$subsite->Theme]);
            }

            if ($subsite && i18n::getData()->validate($subsite->Language)) {
                i18n::set_locale($subsite->Language);
            }
        }
    }
    
    public function getTitle()
    {
        if ($this->config()->title) {
            return $this->config()->title;
        } else {
            _t("SilverCommerce\ShoppingCart.CartName", "Your Basket");
        }
    }

    public function getMetaTitle()
    {
        return $this->getTitle();
    }

    public function getDataRecord()
    {
        return $this->dataRecord;
    }

    public function setDataRecord($record)
    {
        $this->dataRecord = $record;
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
        $item = null;
        $title = null;

        if (isset($key)) {
            $item = $this
                ->Items()
                ->find("Key", $key);
        }

        if (isset($item)) {
            $title = $item->Title;
            ShoppingCartFactory::create()
                ->removeItem($item->Key)
                ->write();

            $form = $this->CartForm();
            $form->sessionMessage(_t(
                "ShoppingCart.RemovedItem",
                "Removed '{title}' from your basket",
                ["title" => $title]
            ));
        }

        return $this->redirectBack();
    }
    
    /**
     * Action that will clear shopping cart and associated items
     *
     */
    public function emptycart()
    {
        ShoppingCartFactory::create()->delete();
        
        $form = $this->CartForm();
        $form->sessionMessage(_t(
            "ShoppingCart.EmptiedCart",
            "Emptied Your Basket"
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
            $this->setDiscount($code_to_search);
            ShoppingCartFactory::create()->write();
        } elseif ($curr && $code->Code == $code_to_search) {
            $code = $this->getDiscount();
        }

        $this->extend("onBeforeUseDiscount", $code);

        return $this
            ->customise([
                "Discount" => $code
            ])->render();
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

        $checkout = Injector::inst()
            ->get($this->config()->checkout_class);
        $checkout->setEstimate($this->dataRecord);

        $this->extend("onBeforeCheckout");

        $this->redirect($checkout->Link());
    }

    public function removediscount()
    {
        $id = $this->request->param('ID');

        $discount = AppliedDiscount::get()->byID($id);

        if ($discount->exists()) {
            $discount->delete();
        }

        $this->redirectBack();
    }
    
    /**
     * Should the purchase total show a breakdown of tax and subtotal?
     *
     * @return boolean
     */
    public function getShowTax()
    {
        $config = SiteConfig::current_site_config();
        return !($config->ShowPriceAndTax);
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
                    _t('ShoppingCart.UpdateCart', 'Update Basket')
                )
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
        $form = DiscountCodeForm::create(
            $this,
            "DiscountForm",
            ShoppingCartFactory::create()->getOrder()
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
                $this->dataRecord,
                $this->dataRecord->SubTotal,
                $this->dataRecord->TotalWeight,
                $this->dataRecord->TotalItems
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
            $factory = ShoppingCartFactory::create();
            foreach ($this->Items() as $item) {
                foreach ($data as $key => $value) {
                    $sliced_key = explode("_", $key);
                    if ($sliced_key[0] == "Quantity") {
                        if (isset($item) && ($item->Key == $sliced_key[1])) {
                            if ($value > 0) {
                                $factory->updateItem(
                                    $item->Key,
                                    $value,
                                    false
                                );
                            } else {
                                $factory->removeItem($item->Key);
                            }

                            $form->sessionMessage(
                                _t("ShoppingCart.UpdatedCart", "Updated Your Basket"),
                                ValidationResult::TYPE_GOOD
                            );
                        }
                    }
                }
            }
            $factory->write();
        } catch (ValidationException $e) {
            $form->sessionMessage(
                $e->getMessage()
            );
        } catch (Exception $e) {
            $form->sessionMessage(
                $e->getMessage()
            );
        }
        
        return $this->redirectBack();
    }
}
