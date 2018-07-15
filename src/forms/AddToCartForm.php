<?php

namespace SilverCommerce\ShoppingCart\Forms;

use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Validator;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Control\RequestHandler;
use SilverCommerce\OrdersAdmin\Model\LineItem;
use SilverCommerce\ShoppingCart\ShoppingCartFactory;
use SilverCommerce\QuantityField\Forms\QuantityField;
use SilverCommerce\ShoppingCart\Control\ShoppingCart;

/**
 * Form dedicated to adding the selected item to cart.
 * 
 * This form is intended to be used 
 */
class AddToCartForm extends Form
{
    /**
     * Default form Name property
     */
    const DEFAULT_NAME = "AddToCartForm";

    public function __construct(RequestHandler $controller = null, $name = self::DEFAULT_NAME, FieldList $fields = null, FieldList $actions = null, Validator $validator = null)
    {
        $base_fields = FieldList::create(
            HiddenField::create('ID'),
            HiddenField::create('ClassName'),
            QuantityField::create(
                'Quantity',
                _t('ShoppingCart.Qty','Qty')
            )
        );

        if (!empty($fields)) {
            $base_fields->merge($fields);
        }

        $fields = $base_fields;

        $base_actions = FieldList::create(
            FormAction::create(
                'doAddItemToCart',
                _t('Catalogue.AddToCart','Add to Cart')
            )->addExtraClass('btn btn-primary')
        );

        if (!empty($actions)) {
            $base_actions->merge($actions);
        }

        $actions = $base_actions;

        $base_validator = RequiredFields::create(["Quantity"]);

        if (!empty($validator)) {
            $base_validator->appendRequiredFields($validator);
        }

        $validator = $base_validator;

        parent::__construct(
            $controller,
            $name,
            $fields,
            $actions,
            $validator
        );

        $this->addExtraClass("add-to-cart-form");

        $this->setTemplate("SilverCommerce\\ShoppingCart\\Forms\\Includes\\AddToCartForm");

        $this->extend("updateAddToCartForm");
    }

    /**
     * Get the classname of the product.
     * 
     * @return string
     */
    public function getProductClass()
    {
        return $this
            ->Fields()
            ->dataFieldByName("ClassName")
            ->getValue();
    }

    /**
     * Set the classname of the product to add
     * 
     * @param $class Classname of product
     * @return self
     */
    public function setProductClass($class)
    {
        $this
            ->Fields()
            ->dataFieldByName("ClassName")
            ->setValue($class);

        return $this;
    }

    /**
     * Get the ID of the product.
     * 
     * @return string
     */
    public function getProductID()
    {
        return $this
            ->Fields()
            ->dataFieldByName("ID")
            ->getValue();
    }

    /**
     * Set the ID of the product to add
     * 
     * @param $ID ID of product
     * @return self
     */
    public function setProductID($ID)
    {
        $this
            ->Fields()
            ->dataFieldByName("ID")
            ->setValue($ID);

        return $this;
    }

    public function doAddItemToCart($data)
    {
        $classname = $data["ClassName"];
        $id = $data["ID"];
        $cart = ShoppingCartFactory::create();
        $error = false;
        $redirect_to_cart = Config::inst()
            ->get(ShoppingCart::class, "redirect_on_add");

        if ($object = $classname::get()->byID($id)) {
            // Attempt to get tax rate from object
            // On a Product this is handleed via casting
            // But could be a direct association as well.
            $tax_id = $object->TaxID;

            $deliverable = (isset($object->Deliverable)) ? $object->Deliverable : true;
            
            $item_to_add = LineItem::create([
                "Title" => $object->Title,
                "Content" => $object->Content,
                "Price" => $object->Price,
                "Quantity" => $data['Quantity'],
                "StockID" => $object->StockID,
                "Weight" => $object->Weight,
                "ProductClass" => $object->ClassName,
                "Stocked" => $object->Stocked,
                "Deliverable" => $deliverable,
                "TaxID" => $tax_id,
            ]);

            // Try and add item to cart, return any exceptions raised
            // as a message
            try {
                $cart->addItem($item_to_add);

                $message = _t(
                    'ShoppingCart.AddedItemToCart',
                    'Added "{item}" to your shopping cart',
                    ["item" => $object->Title]
                );

                $this->sessionMessage(
                    $message,
                    ValidationResult::TYPE_GOOD
                );
            } catch(Exception $e) {
                $error = true;
                $this->sessionMessage(
                    $e->getMessage()
                );
            }
        } else {
            $error = true;
            $this->sessionMessage(
                _t("ShoppingCart.ErrorAddingToCart", "Error adding item to cart")
            );
        }

        if ($redirect_to_cart && !$error) {
            return $this
                ->getController()
                ->redirect($cart->Link());
        }

        return $this
            ->getController()
            ->redirectBack();
    }
}