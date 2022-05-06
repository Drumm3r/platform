<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\Test\Document\DocumentTrait;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\TestDefaults;

class ReferenceInvoiceLoaderTest extends TestCase
{
    use DocumentTrait;

    private ReferenceInvoiceLoader $referenceInvoiceLoader;

    private DocumentGenerator $documentGenerator;

    private Context $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->referenceInvoiceLoader = $this->getContainer()->get(ReferenceInvoiceLoader::class);
        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);
        $this->context = Context::createDefaultContext();
        $this->ids = new TestDataCollection($this->context);
        $customerId = $this->createCustomer();

        $this->salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $customerId,
            ]
        );
    }

    public function testLoadWithoutDocument(): void
    {
        $this->getContainer()->get(Connection::class)->executeStatement('DELETE FROM document');

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);
        $invoice = $this->referenceInvoiceLoader->load($orderId);

        static::assertEmpty($invoice);
    }

    public function testLoadWithoutReferenceDocumentId(): void
    {
        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        // Create two documents, the latest invoice will be returned
        $this->createDocument(InvoiceRenderer::TYPE, $orderId, [], $this->context)->first();
        $invoiceStruct = $this->createDocument(InvoiceRenderer::TYPE, $orderId, [], $this->context)->first();

        $invoice = $this->referenceInvoiceLoader->load($orderId);

        static::assertNotEmpty($invoice['id']);
        static::assertEquals($invoiceStruct->getId(), $invoice['id']);
    }

    public function testLoadWithReferenceDocumentId(): void
    {
        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        // Create two documents, the one with passed referenceInvoiceId will be returned
        $invoiceStruct = $this->createDocument(InvoiceRenderer::TYPE, $orderId, [], $this->context)->first();
        $this->createDocument(InvoiceRenderer::TYPE, $orderId, [], $this->context)->first();

        $invoice = $this->referenceInvoiceLoader->load($orderId, $invoiceStruct->getId());

        static::assertEquals($invoiceStruct->getId(), $invoice['id']);
    }
}
