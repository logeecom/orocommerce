<?php

namespace Mollie\Bundle\PaymentBundle\Form\Type;

use Mollie\Bundle\PaymentBundle\Entity\PaymentMethodSettings;
use Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\PaymentMethod\Model\PaymentMethodConfig;
use Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\PaymentMethod\PaymentMethods;
use Mollie\Bundle\PaymentBundle\IntegrationCore\BusinessLogic\Surcharge\SurchargeType;
use Mollie\Bundle\PaymentBundle\Manager\ProductAttributesProvider;
use Oro\Bundle\ApiBundle\Form\Type\NumberType;
use Oro\Bundle\LocaleBundle\Form\Type\LocalizedFallbackValueCollectionType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Form type for Mollie integration payment methods settings
 */
class PaymentMethodSettingsType extends AbstractType
{
    const BLOCK_PREFIX = 'mollie_payment_method_setting_type';
    const DEFAULT_TRANSACTION_DESCRIPTION = '{orderNumber}';

    const ALIAS = 'product';

    /**
     * @var \Symfony\Contracts\Translation\TranslatorInterface
     */
    private $translator;
    /**
     * @var ProductAttributesProvider
     */
    private $productAttributesProvider;


    /**
     * PaymentMethodSettingsType constructor.
     *
     * @param TranslatorInterface $translator
     * @param ProductAttributesProvider $productAttributesProvider
     */
    public function __construct(
        TranslatorInterface $translator,
        ProductAttributesProvider $productAttributesProvider
    ) {
        $this->translator = $translator;
        $this->productAttributesProvider = $productAttributesProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('mollieMethodId', HiddenType::class)
            ->add('mollieMethodDescription', HiddenType::class)
            ->add('enabled', HiddenType::class)
            ->add('imagePath', HiddenType::class)
            ->add('originalImagePath', HiddenType::class)
            ->add(
                'names',
                LocalizedFallbackValueCollectionType::class,
                [
                    'label' => 'mollie.payment.config.payment_methods.name.label',
                    'required' => true,
                    'entry_options' => ['constraints' => [new NotBlank()]],
                ]
            )
            ->add(
                'descriptions',
                LocalizedFallbackValueCollectionType::class,
                [
                    'label' => 'mollie.payment.config.payment_methods.description.label',
                    'required' => true,
                    'entry_options' => ['constraints' => [new NotBlank()]],
                ]
            )
            ->add(
                'paymentDescriptions',
                LocalizedFallbackValueCollectionType::class,
                [
                    'label' => 'mollie.payment.config.payment_methods.payment.description.label',
                    'tooltip' => 'mollie.payment.config.payment_methods.payment.description.tooltip.label',
                    'required' => true,
                    'entry_options' => ['constraints' => [new NotBlank()]],
                ]
            )
            ->add(
                'image',
                FileType::class,
                [
                    'label' => 'mollie.payment.config.payment_methods.image.label',
                    'required' => false,
                    'constraints' => [
                        new Image([
                            'maxSize' => '2M',
                            'mimeTypesMessage' => $this->translator->trans(
                                'mollie.payment.config.payment_methods.image.mime_type_error'
                            ),
                        ]),
                    ],
                ]
            );

        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'onPreSetData']);
    }

    /**
     * @param FormEvent $event
     */
    public function onPreSetData(FormEvent $event)
    {
        /** @var PaymentMethodSettings|null $paymentMethodSetting */
        $paymentMethodSetting = $event->getData();
        if (!$paymentMethodSetting) {
            return;
        }

        $paymentMethodConfig = $paymentMethodSetting->getPaymentMethodConfig();
        if (!$paymentMethodConfig) {
            return;
        }

        $orderExpiryDaysTooltip = $paymentMethodConfig->isApiMethodRestricted() ?
            'mollie.payment.config.payment_methods.orderExpiryDays.klarna_tooltip' :
            'mollie.payment.config.payment_methods.orderExpiryDays.tooltip';

        $event->getForm()->add(
            'orderExpiryDays',
            IntegerType::class,
            [
                'label' => 'mollie.payment.config.payment_methods.orderExpiryDays.label',
                'tooltip' => $orderExpiryDaysTooltip,
                'required' => false,
                'constraints' => [new Range(['min' => 1, 'max' => 100])],
            ]
        );

        if ($paymentMethodConfig->getOriginalAPIConfig()->getId() === 'banktransfer') {
            $event->getForm()->add(
                'paymentExpiryDays',
                IntegerType::class,
                [
                    'label' => 'mollie.payment.config.payment_methods.paymentExpiryDays.label',
                    'tooltip' => 'mollie.payment.config.payment_methods.paymentExpiryDays.tooltip',
                    'required' => false,
                    'constraints' => [new Range(['min' => 1, 'max' => 100])],
                ]
            );
        }

        if (!$paymentMethodConfig->isSurchargeRestricted()) {
            $surchargeTypeChoices = [
                'mollie.payment.config.payment_methods.payment_surcharge.option.no_fee.label' => SurchargeType::NO_FEE,
                'mollie.payment.config.payment_methods.payment_surcharge.option.fixed_fee.label' => SurchargeType::FIXED_FEE,
                'mollie.payment.config.payment_methods.payment_surcharge.option.percentage.label' => SurchargeType::PERCENTAGE,
                'mollie.payment.config.payment_methods.payment_surcharge.option.fixed_fee_and_percentage.label' => SurchargeType::FIXED_FEE_AND_PERCENTAGE
            ];
            $event->getForm()->add(
                'surchargeType',
                ChoiceType::class,
                [
                    'choices' => $surchargeTypeChoices,
                    'label' => 'mollie.payment.config.payment_methods.payment_surcharge.label',
                    'tooltip' => 'mollie.payment.config.payment_methods.payment_surcharge.tooltip',
                    'required' => true,
                    'placeholder' => false,
                    'attr' => [
                        'class' => 'mollie-surcharge-type-select',
                        'data-method-wrapper' => $paymentMethodConfig->getMollieId(),
                    ],
                ]
            );

            $surchargeFixedAmountTooltip = 'mollie.payment.config.payment_methods.surcharge_fixed_amount.tooltip';
            if ($paymentMethodConfig->isSurchargeRestricted()) {
                $surchargeFixedAmountTooltip = 'mollie.payment.config.payment_methods.surcharge_fixed_amount.klarna_tooltip';
            }

            $event->getForm()->add(
                'surchargeFixedAmount',
                NumberType::class,
                [
                    'label' => 'mollie.payment.config.payment_methods.surcharge_fixed_amount.label',
                    'tooltip' => $surchargeFixedAmountTooltip,
                    'required' => false,
                    'attr' => ['autocomplete' => 'off'],
                    'constraints' => [new Range(['min' => 0])],
                ]
            );

            $surchargePercentageTooltip = 'mollie.payment.config.payment_methods.surcharge_percentage.tooltip';
            if ($paymentMethodConfig->isSurchargeRestricted()) {
                $surchargePercentageTooltip = 'mollie.payment.config.payment_methods.surcharge_percentage.klarna_tooltip';
            }

            $surchargeLimitTooltip = 'mollie.payment.config.payment_methods.surcharge_limit.tooltip';
            if ($paymentMethodConfig->isSurchargeRestricted()) {
                $surchargeLimitTooltip = 'mollie.payment.config.payment_methods.surcharge_limit.klarna_tooltip';
            }

            $event->getForm()->add(
                'surchargePercentage',
                NumberType::class,
                [
                    'label' => 'mollie.payment.config.payment_methods.surcharge_percentage.label',
                    'tooltip' => $surchargePercentageTooltip,
                    'required' => false,
                    'attr' => ['autocomplete' => 'off'],
                    'constraints' => [new Range(['min' => 0, 'max' => 100])],
                ]
            )->add(
                'surchargeLimit',
                NumberType::class,
                [
                    'label' => 'mollie.payment.config.payment_methods.surcharge_limit.label',
                    'tooltip' => $surchargeLimitTooltip,
                    'required' => false,
                    'attr' => ['autocomplete' => 'off'],
                    'constraints' => [new Range(['min' => 0])],
                ]
            );
        }

        if (!$paymentMethodConfig->isApiMethodRestricted()) {
            $paymentMethodApiChoices = [
                'mollie.payment.config.payment_methods.method.option.payment_api.label' => PaymentMethodConfig::API_METHOD_PAYMENT,
                'mollie.payment.config.payment_methods.method.option.order_api.label' => PaymentMethodConfig::API_METHOD_ORDERS,
            ];
            $event->getForm()->add(
                'method',
                ChoiceType::class,
                [
                    'choices' => $paymentMethodApiChoices,
                    'label' => 'mollie.payment.config.payment_methods.method.label',
                    'tooltip' => 'mollie.payment.config.payment_methods.method.tooltip',
                    'required' => true,
                    'placeholder' => false,
                    'attr' => [
                        'class' => 'mollie-method-select',
                        'data-method-wrapper' => $paymentMethodConfig->getMollieId(),
                    ],
                ]
            )->add(
                'transactionDescriptions',
                LocalizedFallbackValueCollectionType::class,
                [
                    'label' => 'mollie.payment.config.payment_methods.transactionDescription.label',
                    'tooltip' => 'mollie.payment.config.payment_methods.transactionDescription.tooltip',
                    'required' => true,
                    'entry_options' => ['constraints' => [new NotBlank()]],
                ]
            );
        }


        if ($paymentMethodConfig->isMollieComponentsSupported()) {
            $event->getForm()->add(
                'mollieComponents',
                CheckboxType::class,
                [
                    'value' => 1,
                    'label' => 'mollie.payment.config.payment_methods.mollie_components.label',
                    'tooltip' => 'mollie.payment.config.payment_methods.mollie_components.tooltip',
                    'required' => true,
                ]
            )->add(
                'singleClickPayment',
                CheckboxType::class,
                [
                    'value' => 1,
                    'label' => 'mollie.payment.config.payment_methods.single_click_payment.label',
                    'tooltip' => 'mollie.payment.config.payment_methods.single_click_payment.tooltip',
                    'required' => true,
                ]
            );
        }

        if ($paymentMethodConfig->isSingleClickPaymentSupported()) {
            $event->getForm()->add(
                'singleClickPaymentApprovalText',
                LocalizedFallbackValueCollectionType::class,
                [
                    'label' => 'mollie.payment.config.payment_methods.single_click_payment_approval_text.label',
                    'tooltip' => 'mollie.payment.config.payment_methods.single_click_payment_approval_text.tooltip',
                    'required' => true,
                    'entry_options' => ['constraints' => [new NotBlank()]],
                ]
            )->add(
                'singleClickPaymentDescription',
                LocalizedFallbackValueCollectionType::class,
                [
                    'label' => 'mollie.payment.config.payment_methods.single_click_payment_description.label',
                    'tooltip' => 'mollie.payment.config.payment_methods.single_click_payment_description.tooltip',
                    'required' => true,
                    'entry_options' => ['constraints' => [new NotBlank()]],
                ]
            );
        }

        if ($paymentMethodConfig->isIssuerListSupported()) {
            $issuerListStyleOptions = [
                'mollie.payment.config.payment_methods.issuer_list.option.list' => PaymentMethodConfig::ISSUER_LIST,
                'mollie.payment.config.payment_methods.issuer_list.option.dropdown' => PaymentMethodConfig::ISSUER_DROPDOWN,
            ];
            $event->getForm()->add(
                'issuerListStyle',
                ChoiceType::class,
                [
                    'choices' => $issuerListStyleOptions,
                    'label' => 'mollie.payment.config.payment_methods.issuer_list.label',
                    'tooltip' => 'mollie.payment.config.payment_methods.issuer_list.tooltip',
                    'required' => true,
                    'placeholder' => false,
                ]
            );
        }

        if ($paymentMethodConfig->getMollieId() === PaymentMethods::Vouchers) {
            $event->getForm()->add(
                'voucherCategory',
                ChoiceType::class,
                [
                    'choices' => [
                        'mollie.payment.config.payment_methods.category.choice.none' => PaymentMethodConfig::VOUCHER_CATEGORY_NONE,
                        'mollie.payment.config.payment_methods.category.choice.meal' => PaymentMethodConfig::VOUCHER_CATEGORY_MEAL,
                        'mollie.payment.config.payment_methods.category.choice.eco' => PaymentMethodConfig::VOUCHER_CATEGORY_ECO,
                        'mollie.payment.config.payment_methods.category.choice.gift' => PaymentMethodConfig::VOUCHER_CATEGORY_GIFT,
                    ],
                    'label' => 'mollie.payment.config.payment_methods.category.label',
                    'required' => true,
                    'placeholder' => false,
                    'tooltip' => 'mollie.payment.config.payment_methods.category.tooltip',
                ]
            );
        }
        if ($paymentMethodConfig->getMollieId() === PaymentMethods::Vouchers) {
            $event->getForm()->add(
                'productAttribute',
                ChoiceType::class,
                [
                    'choices' => $this->productAttributesProvider->getProductAttributes(),
                    'label' => 'mollie.payment.config.payment_methods.attribute.label',
                    'required' => true,
                    'placeholder' => false,
                    'tooltip' => 'mollie.payment.config.payment_methods.attribute.tooltip',
                ]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => PaymentMethodSettings::class,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return self::BLOCK_PREFIX;
    }
}
