<?php
/**
 * Copyright © Experius B.V. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Experius\EmailCatcher\Model;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Mail\Message;
use Magento\Framework\Mail\TransportInterfaceFactory;
use Experius\EmailCatcher\Registry\CurrentTemplate;

class Mail
{
    /**
     * @var Message
     */
    protected $messageFactory;

    /**
     * @var EmailcatcherFactory
     */
    protected $emailCatcherFactory;

    /**
     * @var TransportInterfaceFactory
     */
    protected $mailTransportFactory;

    /**
     * @var ProductMetadataInterface
     */
    protected $magentoProductMetaData;

    /**
     * @var \Magento\Framework\Mail\EmailMessageInterfaceFactory|null
     */
    protected $emailMessageInterfaceFactory;

    /**
     * @var \Magento\Framework\Mail\MimeMessageInterfaceFactory|null
     */
    protected $mimeMessageInterfaceFactory;

    /**
     * @var \Magento\Framework\Mail\MimePartInterfaceFactory|null
     */
    protected $mimePartInterfaceFactory;

    /**
     * @var \Magento\Framework\Mail\AddressConverter|null
     */
    protected $addressConverter;

    /**
     * Param that used for storing all message data until it will be used
     *
     * @var array
     */
    private $messageData = [];
    /**
     * @var CurrentTemplate
     */
    private $currentTemplate;

    /**
     * Mail constructor.
     *
     * @param Message $messageFactory
     * @param EmailcatcherFactory $emailcatcherFactory
     * @param TransportInterfaceFactory $transportInterfaceFactory
     * @param CurrentTemplate $currentTemplate
     * @param ProductMetadataInterface|null $magentoProductMetaData
     * @param \Magento\Framework\Mail\EmailMessageInterfaceFactory|null $emailMessageInterfaceFactory
     * @param \Magento\Framework\Mail\MimeMessageInterfaceFactory|null $mimeMessageInterfaceFactory
     * @param \Magento\Framework\Mail\MimePartInterfaceFactory|null $mimePartInterfaceFactory
     * @param \Magento\Framework\Mail\AddressConverter|null $addressConverter
     */
    public function __construct(
        Message $messageFactory,
        EmailcatcherFactory $emailcatcherFactory,
        TransportInterfaceFactory $transportInterfaceFactory,
        CurrentTemplate $currentTemplate,
        ProductMetadataInterface $magentoProductMetaData = null,
        $emailMessageInterfaceFactory = null,
        $mimeMessageInterfaceFactory = null,
        $mimePartInterfaceFactory = null,
        $addressConverter = null
    ) {
        $this->messageFactory = $messageFactory;
        $this->emailCatcherFactory = $emailcatcherFactory;
        $this->mailTransportFactory = $transportInterfaceFactory;
        $this->currentTemplate = $currentTemplate;

        $this->magentoProductMetaData = $magentoProductMetaData ?: \Magento\Framework\App\ObjectManager::getInstance()
            ->get(ProductMetadataInterface::class);

        if (version_compare($this->magentoProductMetaData->getVersion(), "2.3.3", ">=")) {
            $this->emailMessageInterfaceFactory = $emailMessageInterfaceFactory
                ?: \Magento\Framework\App\ObjectManager::getInstance()
                    ->get(\Magento\Framework\Mail\EmailMessageInterfaceFactory::class);
            $this->mimeMessageInterfaceFactory = $mimeMessageInterfaceFactory
                ?: \Magento\Framework\App\ObjectManager::getInstance()
                    ->get(\Magento\Framework\Mail\MimeMessageInterfaceFactory::class);
            $this->mimePartInterfaceFactory = $mimePartInterfaceFactory
                ?: \Magento\Framework\App\ObjectManager::getInstance()
                    ->get(\Magento\Framework\Mail\MimePartInterfaceFactory::class);
            $this->addressConverter = $addressConverter
                ?: \Magento\Framework\App\ObjectManager::getInstance()
                    ->get(\Magento\Framework\Mail\AddressConverter::class);
        }
    }

    /**
     * Send message
     *
     * @param $emailCatcherId
     * @param $alternativeToAddress
     */
    public function sendMessage($emailCatcherId, $alternativeToAddress)
    {
        /** @var \Experius\EmailCatcher\Model\Emailcatcher $emailCatcher */
        $emailCatcher = $this->emailCatcherFactory->create()->load($emailCatcherId);
        $templateIdentifier = $emailCatcher->getTemplateIdentifier();
        if ($templateIdentifier) {
            $this->currentTemplate->set($templateIdentifier);
        }
        $recipient = ($alternativeToAddress) ? $alternativeToAddress : $emailCatcher->getRecipient();

        if (version_compare($this->magentoProductMetaData->getVersion(), "2.3.3", ">=")) {
            // default message type is MimeInterface::TYPE_HTML, so no need to set it
            $this->messageData['from'][] = $this->addressConverter->convert(
                mb_decode_mimeheader($emailCatcher->getSender()),
                mb_decode_mimeheader($emailCatcher->getSender())
            );
            $this->messageData['to'][] = $this->addressConverter->convert(mb_decode_mimeheader($recipient), mb_decode_mimeheader($recipient));
            $content = $emailCatcher->getBody();
            $mimePart = $this->mimePartInterfaceFactory->create(['content' => $content]);
            $this->messageData['body'] = $this->mimeMessageInterfaceFactory->create(
                ['parts' => [$mimePart]]
            );
            $this->messageData['subject'] = html_entity_decode(
                (string)$emailCatcher->getSubject(),
                ENT_QUOTES
            );
            $message = $this->emailMessageInterfaceFactory->create($this->messageData);
        } else {
            /** @var Message $message */
            $message = $this->messageFactory;
            $message->setMessageType('html');
            $message->setFrom(mb_encode_mimeheader($emailCatcher->getSender()));
            $message->addTo(mb_encode_mimeheader($recipient));
            $message->setBodyHtml($emailCatcher->getBody());
            $message->setSubject(mb_encode_mimeheader($emailCatcher->getSubject()));
        }

        $mailTransport = $this->mailTransportFactory->create(['message' => clone $message]);
        $mailTransport->sendMessage();
    }
}
