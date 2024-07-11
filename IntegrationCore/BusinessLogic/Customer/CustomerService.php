<?php

namespace Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\Customer;

use Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\BaseService;
use Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\CustomerReference\CustomerReferenceService;
use Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\Http\DTO\Customer;
use Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\Http\Exceptions\UnprocessableEntityRequestException;
use Mollie\Bundle\PaymentBundle\IntegrationCore\Infrastructure\Http\Exceptions\HttpAuthenticationException;
use Mollie\Bundle\PaymentBundle\IntegrationCore\Infrastructure\Http\Exceptions\HttpCommunicationException;
use Mollie\Bundle\PaymentBundle\IntegrationCore\Infrastructure\Http\Exceptions\HttpRequestException;
use Mollie\Bundle\PaymentBundle\IntegrationCore\Infrastructure\ServiceRegister;

/**
 * Class CustomerService
 *
 * @package Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\Customer
 */
class CustomerService extends BaseService
{
    /**
     * Fully qualified name of this class.
     */
    const CLASS_NAME = __CLASS__;
    /**
     * Singleton instance of this class.
     *
     * @var static
     */
    protected static $instance;

    /**
     * Creates a customer on the Mollie API and stores in local database
     *
     * @param Customer $customer
     * @param $shopReference
     *
     * @return string|null ID of created customer, null if not created
     *
     * @throws HttpAuthenticationException
     * @throws HttpCommunicationException
     * @throws HttpRequestException
     * @throws UnprocessableEntityRequestException
     */
    public function createCustomer(Customer $customer, $shopReference)
    {
        $customerReference = $this->getCustomerReferenceService()->getByShopReference($shopReference);

        if ($customerReference) {
            return $customerReference->getMollieReference();
        }

        try {
            $mollieCustomer = $this->getProxy()->createCustomer($customer);
        } catch (UnprocessableEntityRequestException $exception) {
            return null;
        }

        $this->getCustomerReferenceService()->saveCustomerReference($mollieCustomer, $shopReference);

        return ($mollieCustomer->getId() !== null && $mollieCustomer->getId() !== '')
            ? $mollieCustomer->getId() : null;
    }

    /**
     * Returns customer id from local db by shop reference
     *
     * @param $shopReference
     *
     * @return string|null
     */
    public function getSavedCustomerId($shopReference)
    {
        $customer = $this->getCustomerReferenceService()->getByShopReference($shopReference);

        return $customer ? $customer->getMollieReference() : null;
    }

    /**
     * Removes customer from local database and from mollie api
     *
     * @param string $shopReference
     *
     * @throws UnprocessableEntityRequestException
     * @throws HttpAuthenticationException
     * @throws HttpCommunicationException
     * @throws HttpRequestException
     */
    public function removeCustomer($shopReference)
    {
        $customerReference = $this->getCustomerReferenceService()->getByShopReference($shopReference);

        if ($customerReference) {
            $this->getProxy()->deleteCustomer($customerReference->getMollieReference());
        }

        $this->getCustomerReferenceService()->deleteByShopReference($shopReference);
    }

    /**
     * @return CustomerReferenceService
     */
    protected function getCustomerReferenceService()
    {
        /** @var CustomerReferenceService $customerReferenceService */
        $customerReferenceService = ServiceRegister::getService(CustomerReferenceService::CLASS_NAME);

        return $customerReferenceService;
    }
}
