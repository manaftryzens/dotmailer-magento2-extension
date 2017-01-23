<?php

namespace Dotdigitalgroup\Email\Model\Customer;

class Guest
{
    /**
     * @var int
     */
    public $countGuests = 0;
    /**
     * @var
     */
    public $start;
    /**
     * @var \Dotdigitalgroup\Email\Helper\Data
     */
    public $helper;
    /**
     * @var \Dotdigitalgroup\Email\Helper\File
     */
    public $file;
    /**
     * @var \Dotdigitalgroup\Email\Model\ContactFactory
     */
    public $contactFactory;
    /**
     * @var \Dotdigitalgroup\Email\Model\ImporterFactory
     */
    public $importerFactory;
    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    public $salesOrderFactory;
    /**
     * @var array
     */
    public $guests = [];

    /**
     * Guest constructor.
     *
     * @param \Dotdigitalgroup\Email\Model\ImporterFactory $importerFactory
     * @param \Dotdigitalgroup\Email\Model\ContactFactory $contactFactory
     * @param \Dotdigitalgroup\Email\Helper\File $file
     * @param \Dotdigitalgroup\Email\Helper\Data $helper
     * @param \Magento\Sales\Model\OrderFactory $salesOrderFactory
     */
    public function __construct(
        \Dotdigitalgroup\Email\Model\ImporterFactory $importerFactory,
        \Dotdigitalgroup\Email\Model\ContactFactory $contactFactory,
        \Dotdigitalgroup\Email\Helper\File $file,
        \Dotdigitalgroup\Email\Helper\Data $helper,
        \Magento\Sales\Model\OrderFactory $salesOrderFactory
    ) {
        $this->importerFactory = $importerFactory;
        $this->contactFactory  = $contactFactory;
        $this->helper          = $helper;
        $this->file            = $file;
        $this->salesOrderFactory = $salesOrderFactory;
    }

    /**
     * GUEST SYNC.
     */
    public function sync()
    {
        //Find and create guest
        $this->findAndCreateGuest();

        $this->start = microtime(true);
        $websites    = $this->helper->getWebsites();
        $started     = false;

        foreach ($websites as $website) {
            //check if the guest is mapped and enabled
            $addresbook = $this->helper->getGuestAddressBook($website);
            $guestSyncEnabled = $this->helper->isGuestSyncEnabled($website);
            $apiEnabled = $this->helper->isEnabled($website);
            if ($addresbook && $guestSyncEnabled && $apiEnabled) {
                //sync guests for website
                $this->exportGuestPerWebsite($website);

                if ($this->countGuests && !$started) {
                    $this->helper->log('----------- Start guest sync ----------');
                    $started = true;
                }
            }
        }
        if ($this->countGuests) {
            $this->helper->log('---- End Guest total time for guest sync : '
                . gmdate('H:i:s', microtime(true) - $this->start));
        }
    }

    /**
     * Find and create guests
     */
    public function findAndCreateGuest()
    {
        $contacts = $this->contactFactory->create()
            ->getCollection()
            ->getColumnValues('email');

        //get the order collection
        $salesOrderCollection = $this->salesOrderFactory->create()
            ->getCollection()
            ->addFieldToFilter('customer_is_guest', ['eq' => 1])
            ->addFieldToFilter('customer_email', ['notnull' => true])
            ->addFieldToFilter('customer_email', ['nin' => $contacts]);

        foreach ($salesOrderCollection as $order) {
            $storeId = $order->getStoreId();
            $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();

            //add guest to the list
            $this->guests[] = [
                'email' => $order->getCustomerEmail(),
                'website_id' => $websiteId,
                'store_id' => $storeId,
                'is_guest' => 1
            ];
        }

        /**
         * Add guest to contacts table.
         */
        if (!empty($this->guests)) {
            $this->contactFactory->create()
                ->getResource()
                ->insert($this->guests);
        }
    }

    /**
     * Export guests for a website.
     *
     * @param $website
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function exportGuestPerWebsite($website)
    {
        $guests = $this->contactFactory->create()
            ->getGuests($website);
        //found some guests
        if ($guests->getSize()) {
            $guestFilename = strtolower($website->getCode() . '_guest_'
                . date('d_m_Y_Hi') . '.csv');
            $this->helper->log('Guest file: ' . $guestFilename);
            $storeName = $this->helper->getMappedStoreName($website);
            $this->file->outputCSV(
                $this->file->getFilePath($guestFilename),
                ['Email', 'emailType', $storeName]
            );

            foreach ($guests as $guest) {
                $email = $guest->getEmail();
                try {
                    //@codingStandardsIgnoreStart
                    $guest->setEmailImported(\Dotdigitalgroup\Email\Model\Contact::EMAIL_CONTACT_IMPORTED);
                    $guest->getResource()->save($guest);
                    //@codingStandardsIgnoreEnd
                    $storeName = $website->getName();
                    // save data for guests
                    $this->file->outputCSV(
                        $this->file->getFilePath($guestFilename),
                        [$email, 'Html', $storeName]
                    );
                    ++$this->countGuests;
                } catch (\Exception $e) {
                    throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
                }
            }
            if ($this->countGuests) {
                //register in queue with importer
                $this->importerFactory->create()
                    ->registerQueue(
                        \Dotdigitalgroup\Email\Model\Importer::IMPORT_TYPE_GUEST,
                        '',
                        \Dotdigitalgroup\Email\Model\Importer::MODE_BULK,
                        $website->getId(),
                        $guestFilename
                    );
            }
        }
    }
}
