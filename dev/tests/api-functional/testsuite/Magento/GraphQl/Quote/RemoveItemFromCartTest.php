<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Quote;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\TestFramework\ObjectManager;

/**
 * Test for empty cart creation mutation
 */
class RemoveItemFromCartTest extends GraphQlAbstract
{
    /**
     * @var QuoteResource
     */
    private $quoteResource;

    /**
     * @var Quote
     */
    private $quote;

    /**
     * @var QuoteIdToMaskedQuoteIdInterface
     */
    private $quoteIdToMaskedId;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var GuestCartRepositoryInterface
     */
    private $guestCartRepository;

    protected function setUp()
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->quoteResource = $objectManager->create(QuoteResource::class);
        $this->quote = $objectManager->create(Quote::class);
        $this->quoteIdToMaskedId = $objectManager->create(QuoteIdToMaskedQuoteIdInterface::class);
        $this->productRepository = $objectManager->create(ProductRepositoryInterface::class);
        $this->guestCartRepository = $objectManager->create(GuestCartRepositoryInterface::class);
    }

    /**
     * @magentoApiDataFixture Magento/Checkout/_files/quote_with_simple_product_saved.php
     */
    public function testGuestRemoveItemFromCart()
    {
        $this->quoteResource->load(
            $this->quote,
            'test_order_with_simple_product_without_address',
            'reserved_order_id'
        );
        $maskedQuoteId = $this->quoteIdToMaskedId->execute((int)$this->quote->getId());
        $itemId = $this->quote->getItemByProduct($this->productRepository->get('simple'))->getId();

        $query = <<<QUERY
mutation {
  removeItemFromCart(
    input: {
      cart_id: "$maskedQuoteId"
      cart_item_id: "$itemId"
    }
  ) {
    cart {
      cart_id
      items {
        id
        qty
      }
    }
  }
}
QUERY;
        $response = $this->graphQlQuery($query);

        $this->assertArrayHasKey('removeItemFromCart', $response);
        $this->assertArrayHasKey('cart', $response['removeItemFromCart']);

        $responseCart = $response['removeItemFromCart']['cart'];

        $this->assertCount(0, $responseCart['items']);
    }

    /**
     * @magentoApiDataFixture Magento/Checkout/_files/quote_with_simple_product_saved.php
     * @magentoApiDataFixture Magento/Customer/_files/customer.php
     */
    public function testRemoveItemFromCart()
    {
        $this->quoteResource->load(
            $this->quote,
            'test_order_with_simple_product_without_address',
            'reserved_order_id'
        );
        $maskedQuoteId = $this->quoteIdToMaskedId->execute((int)$this->quote->getId());
        $itemId = $this->quote->getItemByProduct($this->productRepository->get('simple'))->getId();

        $this->quote->setCustomerId(1);
        $this->quoteResource->save($this->quote);

        $headerMap = $this->getHeaderMap();

        $query = <<<QUERY
mutation {
  removeItemFromCart(
    input: {
      cart_id: "$maskedQuoteId"
      cart_item_id: "$itemId"
    }
  ) {
    cart {
      cart_id
      items {
        id
        qty
      }
    }
  }
}
QUERY;

        $response = $this->graphQlQuery($query, [], '', $headerMap);

        $this->assertArrayHasKey('removeItemFromCart', $response);
        $this->assertArrayHasKey('cart', $response['removeItemFromCart']);

        $responseCart = $response['removeItemFromCart']['cart'];

        $this->assertCount(0, $responseCart['items']);
    }

    public function testRemoveItemFromCartNoSuchCartIdException()
    {
        $maskedCartId = 'nada';

        $this->expectExceptionMessage('No such entity with cartId');

        $query = <<<QUERY
mutation {
  removeItemFromCart(
    input: {
      cart_id: "$maskedCartId"
      cart_item_id: "nononono"
    }
  ) {
    cart {
      cart_id
      items {
        id
        qty
      }
    }
  }
}
QUERY;
        $this->graphQlQuery($query);
    }

    /**
     * @magentoApiDataFixture Magento/Checkout/_files/quote_with_simple_product_saved.php
     */
    public function testRemoveItemFromCartNoSuchCartItem()
    {
        $this->quoteResource->load(
            $this->quote,
            'test_order_with_simple_product_without_address',
            'reserved_order_id'
        );
        $maskedQuoteId = $this->quoteIdToMaskedId->execute((int)$this->quote->getId());
        $itemId = 'nononono';

        $this->expectExceptionMessage(sprintf('Cart doesn\'t contain the %s item.', $itemId));

        $query = <<<QUERY
mutation {
  removeItemFromCart(
    input: {
      cart_id: "$maskedQuoteId"
      cart_item_id: "$itemId"
    }
  ) {
    cart {
      cart_id
      items {
        id
        qty
      }
    }
  }
}
QUERY;
        $this->graphQlQuery($query);
    }

    /**
     * @param string $username
     * @return array
     */
    private function getHeaderMap(string $username = 'customer@example.com'): array
    {
        $password = 'password';
        /** @var CustomerTokenServiceInterface $customerTokenService */
        $customerTokenService = ObjectManager::getInstance()
            ->get(CustomerTokenServiceInterface::class);
        $customerToken = $customerTokenService->createCustomerAccessToken($username, $password);
        $headerMap = ['Authorization' => 'Bearer ' . $customerToken];
        return $headerMap;
    }
}
