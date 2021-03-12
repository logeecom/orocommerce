<?php


namespace Mollie\Bundle\PaymentBundle\Manager;

use Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\PaymentMethod\Model\PaymentMethodConfig;
use Oro\Bundle\ProductBundle\Entity\Product;

class ProductAttributeResolver
{
    /**
     * @var Product
     */
    protected $product;
    /**
     * @var string
     */
    protected $fallbackAttribute;
    /**
     * @var string
     */
    protected $productProperty;

    /**
     * ProductAttributeProvider constructor.
     *
     * @param Product $product
     * @param string $fallbackAttribute
     * @param string $productProperty
     */
    public function __construct(Product $product, $fallbackAttribute, $productProperty)
    {
        $this->product = $product;
        $this->fallbackAttribute = $fallbackAttribute;
        $this->productProperty = $productProperty;
    }

    /**
     * Returns attribute value, based on the configuration
     *
     * @return string|null
     */
    public function getPropertyValue()
    {
        $methodName = $this->getPropertyGetterName();
        if ($methodName) {
            $category = $this->product->{$methodName}();
            if ($category) {
                return $category->getId();
            }
        }

        return $this->fallbackAttribute !== PaymentMethodConfig::VOUCHER_CATEGORY_NONE ?
            $this->fallbackAttribute : null;
    }

    /**
     * Returns method name for getting product attribute
     *
     * @return string|null
     */
    protected function getPropertyGetterName()
    {
        if (method_exists($this->product, $this->productProperty)) {
            return $this->productProperty;
        }

        if (method_exists($this->product, "get$this->productProperty")) {
            return "get$this->productProperty";
        }

        if (method_exists($this->product, 'get' . MethodNameGenerator::fromSnakeCase($this->productProperty))) {
            return 'get' . MethodNameGenerator::fromSnakeCase($this->productProperty);
        }

        if (method_exists($this->product, 'get' . MethodNameGenerator::fromSnakeCase($this->productProperty, false))) {
            return 'get' . MethodNameGenerator::fromSnakeCase($this->productProperty, false);
        }

        return null;
    }
}
