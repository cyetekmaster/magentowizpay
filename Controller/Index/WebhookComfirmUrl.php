<?php
/**
 *
 * @package     magento2
 * @author
 * @license     https://opensource.org/licenses/OSL-3.0 Open Software License v. 3.0 (OSL-3.0)
 * @link
 */

 namespace Wizpay\Wizpay\Controller\Index;

class WebhookComfirmUrl extends Success
{

    public function __construct(
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

}