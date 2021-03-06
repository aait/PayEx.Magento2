<?php

namespace PayEx\Payments\Helper;

use PayEx\Px;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Sales\Model\Order\Payment\Transaction;


/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class Data extends AbstractHelper
{
    const MODULE_NAME = 'PayEx_Payments';

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @var \Magento\Payment\Model\Config
     */
    protected $_config;

    /**
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    protected $_moduleList;

    /**
     * @var \Magento\Sales\Model\Order\Config
     */
    protected $_orderConfig;

    /**
     * @var \PayEx\Px
     */
    protected $_px;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory
     */
    protected $orderStatusCollectionFactory;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var \Magento\Tax\Helper\Data
     */
    protected $taxHelper;

    /**
     * @var \Magento\Framework\App\ProductMetadata
     */
    protected $productMetadata;

    /**
     * Data constructor.
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Payment\Model\Config $config
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     * @param \Magento\Sales\Model\Order\Config $orderConfig
     * @param \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollectionFactory
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
     * @param \Magento\Tax\Helper\Data $taxHelper
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Payment\Model\Config $config,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollectionFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Tax\Helper\Data $taxHelper,
        \Magento\Framework\App\ProductMetadata $productMetadata
    )
    {
        parent::__construct($context);
        $this->_encryptor = $encryptor;
        $this->_config = $config;
        $this->_moduleList = $moduleList;
        $this->_orderConfig = $orderConfig;

        $this->orderStatusCollectionFactory = $orderStatusCollectionFactory;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;

        $this->taxHelper = $taxHelper;
        $this->productMetadata = $productMetadata;
    }

    /**
     * Get Module Version
     * @return string
     */
    public function getVersion()
    {
        return $this->_moduleList
            ->getOne(self::MODULE_NAME)['setup_version'];
    }

    /**
     * Retrieve information from payment configuration
     * @param $field
     * @param $paymentMethodCode
     * @param $storeId
     * @param bool|false $flag
     * @return bool|mixed
     */
    public function getConfigData($field, $paymentMethodCode, $storeId, $flag = false)
    {
        $path = 'payment/' . $paymentMethodCode . '/' . $field;

        if (!$flag) {
            return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            return $this->scopeConfig->isSetFlag($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        }
    }

    /**
     * Get Store
     * @param int|string|null|bool|\Magento\Store\Api\Data\StoreInterface $id [optional]
     * @return \Magento\Store\Api\Data\StoreInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStore($id = null)
    {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var \Magento\Store\Model\StoreManagerInterface $manager */
        $manager = $om->get('Magento\Store\Model\StoreManagerInterface');
        return $manager->getStore($id);
    }

    /**
     * Get Visitor IP address
     * @return string
     */
    public function getRemoteAddr()
    {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $ra */
        $ra = $om->get('Magento\Framework\HTTP\PhpEnvironment\RemoteAddress');

        return $ra->getRemoteAddress();
    }

    /**
     * Get PayEx Api Handler
     * @return \PayEx\Px
     */
    public function getPx()
    {
        if (!$this->_px) {
            $this->_px = new Px();

            // Set User Agent
            $this->_px->setUserAgent(sprintf("PayEx.Ecommerce.Php/%s PHP/%s Magento/%s PayEx.Magento2/%s",
                \PayEx\Px::VERSION,
                phpversion(),
                $this->productMetadata->getVersion(),
                $this->getVersion()
            ));
        }

        return $this->_px;
    }

    /**
     * Get verbose error message by Error Code
     * @param $errorCode
     * @return string | false
     */
    public function getErrorMessageByCode($errorCode)
    {
        $errorMessages = [
            'REJECTED_BY_ACQUIRER' => __('Your customers bank declined the transaction, your customer can contact their bank for more information'),
            //'Error_Generic' => __('An unhandled exception occurred'),
            '3DSecureDirectoryServerError' => __('A problem with Visa or MasterCards directory server, that communicates transactions for 3D-Secure verification'),
            'AcquirerComunicationError' => __('Communication error with the acquiring bank'),
            'AmountNotEqualOrderLinesTotal' => __('The sum of your order lines is not equal to the price set in initialize'),
            'CardNotEligible' => __('Your customers card is not eligible for this kind of purchase, your customer can contact their bank for more information'),
            'CreditCard_Error' => __('Some problem occurred with the credit card, your customer can contact their bank for more information'),
            'PaymentRefusedByFinancialInstitution' => __('Your customers bank declined the transaction, your customer can contact their bank for more information'),
            'Merchant_InvalidAccountNumber' => __('The merchant account number sent in on request is invalid'),
            'Merchant_InvalidIpAddress' => __('The IP address the request comes from is not registered in PayEx, you can set it up in PayEx Admin under Merchant profile'),
            'Access_MissingAccessProperties' => __('The merchant does not have access to requested functionality'),
            'Access_DuplicateRequest' => __('Your customers bank declined the transaction, your customer can contact their bank for more information'),
            'Admin_AccountTerminated' => __('The merchant account is not active'),
            'Admin_AccountDisabled' => __('The merchant account is not active'),
            'ValidationError_AccountLockedOut' => __('The merchant account is locked out'),
            'ValidationError_Generic' => __('Generic validation error'),
            'ValidationError_HashNotValid' => __('The hash on request is not valid, this might be due to the encryption key being incorrect'),
            //'ValidationError_InvalidParameter' => __('One of the input parameters has invalid data. See paramName and description for more information'),
            'OperationCancelledbyCustomer' => __('The operation was cancelled by the client'),
            'PaymentDeclinedDoToUnspecifiedErr' => __('Unexpecter error at 3rd party'),
            'InvalidAmount' => __('The amount is not valid for this operation'),
            'NoRecordFound' => __('No data found'),
            'OperationNotAllowed' => __('The operation is not allowed, transaction is in invalid state'),
            'ACQUIRER_HOST_OFFLINE' => __('Could not get in touch with the card issuer'),
            'ARCOT_MERCHANT_PLUGIN_ERROR' => __('The card could not be verified'),
            'REJECTED_BY_ACQUIRER_CARD_BLACKLISTED' => __('There is a problem with this card'),
            'REJECTED_BY_ACQUIRER_CARD_EXPIRED' => __('The card expired'),
            'REJECTED_BY_ACQUIRER_INSUFFICIENT_FUNDS' => __('Insufficient funds'),
            'REJECTED_BY_ACQUIRER_INVALID_AMOUNT' => __('Incorrect amount'),
            'USER_CANCELED' => __('Payment cancelled'),
            'CardNotAcceptedForThisPurchase' => __('Your Credit Card not accepted for this purchase'),
            'CreditCheckNotApproved' => __('Credit check was declined, please try another payment option')
        ];
        $errorMessages = array_change_key_case($errorMessages, CASE_UPPER);

        $errorCode = mb_strtoupper($errorCode);
        return isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : false;
    }

    /**
     * Get Verbose Error Message
     * @param array $details
     * @return string
     */
    public function getVerboseErrorMessage(array $details)
    {
        $errorCode = isset($details['transactionErrorCode']) ? $details['transactionErrorCode'] : $details['errorCode'];
        $errorMessage = $this->getErrorMessageByCode($errorCode);
        if ($errorMessage) {
            return $errorMessage;
        }

        $errorCode = isset($details['transactionErrorCode']) ? $details['transactionErrorCode'] : '';
        $errorDescription = isset($details['transactionThirdPartyError']) ? $details['transactionThirdPartyError'] : '';
        if (empty($errorCode) && empty($errorDescription)) {
            $errorCode = $details['code'];
            $errorDescription = $details['description'];
        }

        return __('PayEx error: %1 (%2)', $errorCode, $errorDescription);
    }

    /**
     * Get Order Items
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function getOrderItems(\Magento\Sales\Model\Order $order)
    {
        $lines = [];
        $items = $order->getAllVisibleItems();
        foreach ($items as $item) {
            /** @var \Magento\Sales\Model\Order\Item $item */
            $itemQty = (int)$item->getQtyOrdered();
            $priceWithTax = $item->getRowTotalInclTax();
            $priceWithoutTax = $item->getRowTotal();
            $taxPercent = (($priceWithTax / $priceWithoutTax) - 1) * 100; // works for all types
            $taxPrice = $priceWithTax - $priceWithoutTax;

            $lines[] = [
                'type' => 'product',
                'name' => $item->getName(),
                'qty' => $itemQty,
                'price_with_tax' => $priceWithTax,
                'price_without_tax' => $priceWithoutTax,
                'tax_price' => $taxPrice,
                'tax_percent' => $taxPercent
            ];
        }

        // add Shipping
        if (!$order->getIsVirtual()) {
            $shippingExclTax = $order->getShippingAmount();
            $shippingIncTax = $order->getShippingInclTax();
            $shippingTax = $shippingIncTax - $shippingExclTax;

            // find out tax-rate for the shipping
            if ((float)$shippingIncTax && (float)$shippingExclTax) {
                $shippingTaxRate = (($shippingIncTax / $shippingExclTax) - 1) * 100;
            } else {
                $shippingTaxRate = 0;
            }

            $lines[] = [
                'type' => 'shipping',
                'name' => $order->getShippingDescription(),
                'qty' => 1,
                'price_with_tax' => $shippingIncTax,
                'price_without_tax' => $shippingExclTax,
                'tax_price' => $shippingTax,
                'tax_percent' => $shippingTaxRate
            ];
        }

        // add Discount
        if (abs($order->getDiscountAmount()) > 0) {
            $discountData = $this->getOrderDiscountData($order);
            $discountInclTax = $discountData->getDiscountInclTax();
            $discountExclTax = $discountData->getDiscountExclTax();
            $discountVatAmount = $discountInclTax - $discountExclTax;
            $discountVatPercent = (($discountInclTax / $discountExclTax) - 1) * 100;

            $lines[] = [
                'type' => 'discount',
                'name' => __('Discount (%1)', $order->getDiscountDescription()),
                'qty' => 1,
                'price_with_tax' => -1 * $discountInclTax,
                'price_without_tax' => -1 * $discountExclTax,
                'tax_price' => -1 * $discountVatAmount,
                'tax_percent' => $discountVatPercent
            ];
        }

        // add Payment Fee
        if ($order->getPayexPaymentFee() > 0) {
            $feeExclTax = $order->getPayexPaymentFee();
            $feeTax = $order->getPayexPaymentFeeTax();
            $feeIncTax = $feeExclTax + $feeTax;
            $feeTaxRate = $feeExclTax > 0 ? (($feeIncTax / $feeExclTax) - 1) * 100 : 0;

            $lines[] = [
                'type' => 'fee',
                'name' => __('Payment Fee'),
                'qty' => 1,
                'price_with_tax' => $feeIncTax,
                'price_without_tax' => $feeExclTax,
                'tax_price' => $feeTax,
                'tax_percent' => $feeTaxRate
            ];
        }

        return $lines;
    }

    /**
     * Prepare Address Info
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function getAddressInfo(\Magento\Sales\Model\Order $order)
    {
        $country = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Directory\Model\Country');
        $billingAddress = $order->getBillingAddress()->getStreet();
        $billingCountryId = $order->getBillingAddress()->getCountryId();
        $billingCountry = $country->loadByCode($billingCountryId)->getName();

        $params = [
            'billingFirstName' => $order->getBillingAddress()->getFirstname(),
            'billingLastName' => $order->getBillingAddress()->getLastname(),
            'billingAddress1' => $billingAddress[0],
            'billingAddress2' => (isset($billingAddress[1])) ? $billingAddress[1] : '',
            'billingAddress3' => '',
            'billingPostNumber' => (string)$order->getBillingAddress()->getPostcode(),
            'billingCity' => (string)$order->getBillingAddress()->getCity(),
            'billingState' => (string)$order->getBillingAddress()->getRegion(),
            'billingCountry' => $billingCountry,
            'billingCountryCode' => $billingCountryId,
            'billingEmail' => (string)$order->getBillingAddress()->getEmail(),
            'billingPhone' => (string)$order->getBillingAddress()->getTelephone(),
            'billingGsm' => '',
            'deliveryFirstName' => '',
            'deliveryLastName' => '',
            'deliveryAddress1' => '',
            'deliveryAddress2' => '',
            'deliveryAddress3' => '',
            'deliveryPostNumber' => '',
            'deliveryCity' => '',
            'deliveryState' => '',
            'deliveryCountry' => '',
            'deliveryCountryCode' => '',
            'deliveryEmail' => '',
            'deliveryPhone' => '',
            'deliveryGsm' => '',
        ];

        // add Shipping
        if (!$order->getIsVirtual()) {
            $deliveryAddress = $order->getShippingAddress()->getStreet();
            $deliveryCountryId = $order->getShippingAddress()->getCountryId();
            $deliveryCountry = $country->loadByCode($billingCountryId)->getName();

            $params = array_merge($params, [
                'deliveryFirstName' => $order->getShippingAddress()->getFirstname(),
                'deliveryLastName' => $order->getShippingAddress()->getLastname(),
                'deliveryAddress1' => $deliveryAddress[0],
                'deliveryAddress2' => (isset($deliveryAddress[1])) ? $deliveryAddress[1] : '',
                'deliveryAddress3' => '',
                'deliveryPostNumber' => (string)$order->getShippingAddress()->getPostcode(),
                'deliveryCity' => (string)$order->getShippingAddress()->getCity(),
                'deliveryState' => (string)$order->getShippingAddress()->getRegion(),
                'deliveryCountry' => $deliveryCountry,
                'deliveryCountryCode' => $deliveryCountryId,
                'deliveryEmail' => (string)$order->getShippingAddress()->getEmail(),
                'deliveryPhone' => (string)$order->getShippingAddress()->getTelephone(),
                'deliveryGsm' => '',
            ]);
        }

        return $params;
    }

    /**
     * Get Assigned State
     * @param $status
     * @return \Magento\Framework\DataObject
     */
    public function getAssignedState($status) {

        $collection = $this->orderStatusCollectionFactory->create()->joinStates();
        $status = $collection->addAttributeToFilter('main_table.status', $status)->getFirstItem();
        return $status;
    }

    /**
     * Create Invoice
     * @param \Magento\Sales\Model\Order $order
     * @param array $qtys
     * @param bool $online
     * @param string $comment
     * @return \Magento\Sales\Model\Order\Invoice
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function makeInvoice(\Magento\Sales\Model\Order $order, array $qtys = [], $online = false, $comment = '')
    {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        $invoice = $this->invoiceService->prepareInvoice($order, $qtys);
        $invoice->setRequestedCaptureCase($online ? \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE : \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);

        // Add Comment
        if (!empty($comment)) {
            $invoice->addComment(
                $comment,
                true,
                true
            );

            $invoice->setCustomerNote($comment);
            $invoice->setCustomerNoteNotify(true);
        }

        $invoice->register();
        $invoice->getOrder()->setIsInProcess(true);

        /** @var \Magento\Framework\DB\Transaction $transactionSave */
        $transactionSave = $om->create(
            'Magento\Framework\DB\Transaction'
        )
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transactionSave->save();

        // send invoice emails
        try {
            $this->invoiceSender->send($invoice);
        } catch (\Exception $e) {
            $om->get('Psr\Log\LoggerInterface')->critical($e);
        }

        $invoice->setIsPaid(true);

        // Assign Last Transaction Id with Invoice
        $transactionId = $invoice->getOrder()->getPayment()->getLastTransId();
        if ($transactionId) {
            $invoice->setTransactionId($transactionId);
            $invoice->save();
        }

        return $invoice;
    }

    /**
     * Gets the total discount from Order
     * inkl. and excl. tax
     * Data is returned as a Varien_Object with these data-keys set:
     *   - discount_incl_tax
     *   - discount_excl_tax
     * @param \Magento\Sales\Model\Order $order
     * @return \Magento\Framework\DataObject
     */
    public function getOrderDiscountData(\Magento\Sales\Model\Order $order)
    {
        $discountIncl = 0;
        $discountExcl = 0;

        // find discount on the items
        foreach ($order->getItemsCollection() as $item) {
            /** @var \Magento\Sales\Model\Order\Item $item */
            if (!$this->taxHelper->priceIncludesTax()) {
                $discountExcl += $item->getDiscountAmount();
                $discountIncl += $item->getDiscountAmount() * (($item->getTaxPercent() / 100) + 1);
            } else {
                $discountExcl += $item->getDiscountAmount() / (($item->getTaxPercent() / 100) + 1);
                $discountIncl += $item->getDiscountAmount();
            }
        }

        // find out tax-rate for the shipping
        if ((float)$order->getShippingInclTax() && (float)$order->getShippingAmount()) {
            $shippingTaxRate = $order->getShippingInclTax() / $order->getShippingAmount();
        } else {
            $shippingTaxRate = 1;
        }

        // get discount amount for shipping
        $shippingDiscount = (float)$order->getShippingDiscountAmount();

        // apply/remove tax to shipping-discount
        if (!$this->taxHelper->priceIncludesTax()) {
            $discountIncl += $shippingDiscount * $shippingTaxRate;
            $discountExcl += $shippingDiscount;
        } else {
            $discountIncl += $shippingDiscount;
            $discountExcl += $shippingDiscount / $shippingTaxRate;
        }

        $return = new \Magento\Framework\DataObject;
        return $return->setDiscountInclTax($discountIncl)->setDiscountExclTax($discountExcl);
    }

    /**
     * Add Payment Transaction
     * @param \Magento\Sales\Model\Order $order
     * @param array $details
     * @return Transaction
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addPaymentTransaction(\Magento\Sales\Model\Order $order, array $details = [])
    {
        /** @var \Magento\Sales\Model\Order\Payment\Transaction $transaction */
        $transaction = null;

        /* Transaction statuses: 0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
        $transaction_status = !empty($details['transactionStatus']) ? (int)$details['transactionStatus'] : 'undefined';
        switch ($transaction_status) {
            case 1:
                // From PayEx PIM:
                // "If PxOrder.Complete returns transactionStatus = 1, then check pendingReason for status."
                // See http://www.payexpim.com/payment-methods/paypal/
                if ($details['pending'] === 'true') {
                    $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_AUTH, null, true);
                    $transaction->setIsClosed(0);
                    $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                    $transaction->save();
                    break;
                }

                $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_PAYMENT, null, true);
                $transaction->setIsClosed(0);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                $transaction->save();
                break;
            case 3:
                $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_AUTH, null, true);
                $transaction->setIsClosed(0);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                $transaction->save();
                break;
            case 0;
            case 6:
                $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_CAPTURE, null, true);
                $transaction->isFailsafe(true)->close(false);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                $transaction->save();
                break;
            case 2:
                $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_REFUND, null, true);
                $transaction->isFailsafe(true)->close(false);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                $transaction->save();
                break;
            case 4;
                $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_VOID, null, true);
                $transaction->isFailsafe(true)->close(false);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                $transaction->save();
                break;
            case 5;
                $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_PAYMENT, null, true);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                $transaction->save();
                break;
            default:
                // Invalid transaction status
        }

        return $transaction;
    }

    /**
     * Detect Client Language
     * @return string
     */
    public function getLanguage()
    {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var \Magento\Framework\Locale\Resolver $resolver */
        $resolver = $om->get('Magento\Framework\Locale\Resolver');
        $locale = $resolver->getLocale();

        /** @var \PayEx\Payments\Model\Config\Source\Language $language */
        $language = $om->get('PayEx\Payments\Model\Config\Source\Language');
        $languages = $language->toOptionArray();
        foreach ($languages as $key => $value) {
            if (str_replace('_', '-', $locale) === $value['value']) {
                return $value['value'];
            }
        }

        // Use en-US as default language
        return 'en-US';
    }

    /**
     * Generate Invoice Print XML for Financing Invoice/PartPayment
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function getInvoiceExtraPrintBlocksXML(\Magento\Sales\Model\Order $order) {
        $lines = $this->getOrderItems($order);

        // Replace illegal characters of product names
        $replace_illegal = $order->getPayment()->getMethodInstance()->getConfigData('replace_illegal', $order->getStoreId());
        if ($replace_illegal) {
            $replacement_char = $order->getPayment()->getMethodInstance()->getConfigData('replacement_char', $order->getStoreId());
            if (empty($replacement_char)) {
                $replacement_char = '-';
            }

            $lines = array_map(function($value) use($replacement_char) {
                if (isset($value['name'])) {
                    mb_regex_encoding('utf-8');
                    $value['name'] = mb_ereg_replace('[^a-zA-Z0-9_:!#=?\[\]@{}´ %-\/À-ÖØ-öø-ú]', $replacement_char, $value['name']);
                }
                return $value;
            }, $lines);
        }

        $dom = new \DOMDocument('1.0', 'utf-8');
        $OnlineInvoice = $dom->createElement('OnlineInvoice');
        $dom->appendChild($OnlineInvoice);
        $OnlineInvoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $OnlineInvoice->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsd', 'http://www.w3.org/2001/XMLSchema');

        $OrderLines = $dom->createElement('OrderLines');
        $OnlineInvoice->appendChild($OrderLines);

        // Add Order Lines
        foreach ($lines as $line) {
            $OrderLine = $dom->createElement('OrderLine');
            $OrderLine->appendChild($dom->createElement('Product', $line['name']));
            $OrderLine->appendChild($dom->createElement('Qty', $line['qty']));
            $OrderLine->appendChild($dom->createElement('UnitPrice', sprintf("%.2f", $line['price_without_tax'] / $line['qty'])));
            $OrderLine->appendChild($dom->createElement('VatRate', sprintf("%.2f", $line['tax_percent'])));
            $OrderLine->appendChild($dom->createElement('VatAmount', sprintf("%.2f", $line['tax_price'])));
            $OrderLine->appendChild($dom->createElement('Amount', sprintf("%.2f", $line['price_with_tax'])));
            $OrderLines->appendChild($OrderLine);
        }

        return str_replace("\n", '', $dom->saveXML());
    }

    /**
     * Get Shopping Cart XML for MasterPass
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    public function getOrderShoppingCartXML(\Magento\Sales\Model\Order $order) {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var \Magento\Catalog\Helper\Image $imageHelper */
        $imageHelper = $om->get('Magento\Catalog\Helper\Image');

        $dom = new \DOMDocument('1.0', 'utf-8');
        $ShoppingCart = $dom->createElement('ShoppingCart');
        $dom->appendChild($ShoppingCart);

        $currency = $order->getOrderCurrencyCode();

        $ShoppingCart->appendChild($dom->createElement('CurrencyCode', $currency));
        $ShoppingCart->appendChild($dom->createElement('Subtotal', (int)(100 * $order->getGrandTotal())));

        // Add Order Lines
        $items = $order->getAllVisibleItems();
        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($items as $item) {
            $product = $item->getProduct();
            $qty = $item->getQtyOrdered();
            $image = $imageHelper->init($product, 'category_page_list')->getUrl();

            $ShoppingCartItem = $dom->createElement('ShoppingCartItem');
            $ShoppingCartItem->appendChild($dom->createElement('Description', $item->getName()));
            $ShoppingCartItem->appendChild($dom->createElement('Quantity', (float)$qty));
            $ShoppingCartItem->appendChild($dom->createElement('Value', (int)bcmul($product->getFinalPrice(), 100)));
            $ShoppingCartItem->appendChild($dom->createElement('ImageURL', $image));
            $ShoppingCart->appendChild($ShoppingCartItem);
        }

        return str_replace("\n", '', $dom->saveXML());
    }

    /**
     * Get Shopping Cart XML for MasterPass
     * @param \Magento\Quote\Model\Quote $quote
     * @return mixed
     */
    public function getQuteShoppingCartXML(\Magento\Quote\Model\Quote $quote) {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var \Magento\Catalog\Helper\Image $imageHelper */
        $imageHelper = $om->get('Magento\Catalog\Helper\Image');

        $dom = new \DOMDocument('1.0', 'utf-8');
        $ShoppingCart = $dom->createElement('ShoppingCart');
        $dom->appendChild($ShoppingCart);

        $currency = $quote->getQuoteCurrencyCode();

        $ShoppingCart->appendChild($dom->createElement('CurrencyCode', $currency));
        $ShoppingCart->appendChild($dom->createElement('Subtotal', (int)(100 * $quote->getGrandTotal())));

        // Add Order Lines
        $items = $quote->getAllVisibleItems();
        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ($items as $item) {
            $product = $item->getProduct();
            $qty = $item->getQty();
            $image = $imageHelper->init($product, 'category_page_list')->getUrl();

            $ShoppingCartItem = $dom->createElement('ShoppingCartItem');
            $ShoppingCartItem->appendChild($dom->createElement('Description', $item->getName()));
            $ShoppingCartItem->appendChild($dom->createElement('Quantity', (float)$qty));
            $ShoppingCartItem->appendChild($dom->createElement('Value', (int)bcmul($product->getFinalPrice(), 100)));
            $ShoppingCartItem->appendChild($dom->createElement('ImageURL', $image));
            $ShoppingCart->appendChild($ShoppingCartItem);
        }

        return str_replace("\n", '', $dom->saveXML());
    }

    /**
     * Calculate Payment Fee Price
     * @param float $fee
     * @param int $tax_class
     * @return \Magento\Framework\DataObject
     */
    public function getPaymentFeePrice($fee, $tax_class)
    {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var \Magento\Checkout\Model\Session $session */
        $session = $om->get('Magento\Checkout\Model\Session');

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $session->getQuote();

        /** @var \Magento\Tax\Model\Calculation $calculationTool */
        $calculationTool = $om->get('Magento\Tax\Model\Calculation');

        // Get Tax Rate
        /** @var \Magento\Framework\DataObject $request */
        $request = $calculationTool->getRateRequest(
            $quote->getShippingAddress(),
            $quote->getBillingAddress(),
            $quote->getCustomerTaxClassId(),
            $quote->getStore()
        );

        $taxRate = $calculationTool->getRate($request->setProductClassId($tax_class));
        $priceIncludeTax = $this->taxHelper->priceIncludesTax($quote->getStore());
        $taxAmount = $calculationTool->calcTaxAmount($fee, $taxRate, $priceIncludeTax, true);
        if ($priceIncludeTax) {
            $fee -= $taxAmount;
        }

        $result = new \Magento\Framework\DataObject;
        $result->setPaymentFeeExclTax($fee)
            ->setPaymentFeeInclTax($fee + $taxAmount)
            ->setPaymentFeeTax($taxAmount)
            ->setRateRequest($request);

        return $result;
    }

    /**
     * Get Name Parser Instance
     * @see https://github.com/joshfraser/PHP-Name-Parser
     * @return \FullNameParser
     * @throws \Exception
     */
    public function getNameParser()
    {
        if (!class_exists('FullNameParser', false)) {
            throw new \Exception('Missing PHP-Name-Parser library. Please install from https://github.com/joshfraser/PHP-Name-Parser');
        }

        return new \FullNameParser();
    }

    /**
     * Get Magento Version
     * @return string
     */
    public function getMageVersion()
    {
        return $this->productMetadata->getVersion();
    }
}