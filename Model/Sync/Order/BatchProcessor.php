<?php

namespace Dotdigitalgroup\Email\Model\Sync\Order;

use Dotdigitalgroup\Email\Logger\Logger;
use Dotdigitalgroup\Email\Model\Catalog\UpdateCatalogBulk;
use Dotdigitalgroup\Email\Model\Importer;
use Dotdigitalgroup\Email\Model\ImporterFactory;
use Dotdigitalgroup\Email\Model\ResourceModel\OrderFactory as OrderResourceFactory;

class BatchProcessor
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var UpdateCatalogBulk
     */
    private $bulkUpdate;

    /**
     * @var ImporterFactory
     */
    private $importerFactory;

    /**
     * @var OrderResourceFactory
     */
    private $orderResourceFactory;

    /**
     * @param Logger $logger
     * @param UpdateCatalogBulk $bulkUpdate
     * @param ImporterFactory $importerFactory
     * @param OrderResourceFactory $orderResourceFactory
     */
    public function __construct(
        Logger $logger,
        UpdateCatalogBulk $bulkUpdate,
        ImporterFactory $importerFactory,
        OrderResourceFactory $orderResourceFactory
    ) {
        $this->logger = $logger;
        $this->bulkUpdate = $bulkUpdate;
        $this->importerFactory = $importerFactory;
        $this->orderResourceFactory = $orderResourceFactory;
    }

    /**
     * Batch Processor.
     *
     * @param array $batch
     */
    public function process($batch)
    {
        $this->addToImportQueue($batch);
        $this->resetOrderedProducts($batch);
        $this->markOrdersAsImported($batch);
    }

    /**
     * Register orders to importer.
     *
     * @param array $ordersBatch
     *
     * @return void
     */
    private function addToImportQueue(array $ordersBatch)
    {
        foreach ($ordersBatch as $websiteId => $orders) {
            $success = $this->importerFactory->create()
                ->registerQueue(
                    Importer::IMPORT_TYPE_ORDERS,
                    $orders,
                    Importer::MODE_BULK,
                    $websiteId
                );
            if ($success) {
                $this->logger->info(
                    sprintf(
                        '%s orders synced for website id: %s',
                        count($orders),
                        $websiteId
                    )
                );
            }
        }
    }

    /**
     * Update products from orders.
     *
     * @param array $ordersBatch
     *
     * @return void
     */
    private function resetOrderedProducts($ordersBatch)
    {
        foreach ($ordersBatch as $orders) {
            $this->bulkUpdate->execute($this->getAllProductsFromBatch($orders));
        }
    }

    /**
     * Update orders.
     *
     * @param array $ordersBatch
     *
     * @return void
     */
    private function markOrdersAsImported($ordersBatch)
    {
        $this->orderResourceFactory->create()
            ->setImportedDateByIds(
                $this->getOrderIdsFromBatch($ordersBatch)
            );
    }

    /**
     * Fetch products.
     *
     * @param \Dotdigitalgroup\Email\Model\Connector\Order[] $orders
     *
     * @return array
     */
    private function getAllProductsFromBatch($orders)
    {
        $allProducts = [];
        foreach ($orders as $order) {
            if (! isset($order['products'])) {
                continue;
            }
            foreach ($order['products'] as $products) {
                $allProducts[] = $products;
            }
        }

        return $allProducts;
    }

    /**
     * Fetch order ids.
     *
     * @param array $ordersBatch
     *
     * @return array
     */
    private function getOrderIdsFromBatch(array $ordersBatch)
    {
        $ids = [];

        foreach ($ordersBatch as $ordersByWebsite) {
            foreach ($ordersByWebsite as $key => $data) {
                $ids[] = $key;
            }
        }

        return $ids;
    }
}
