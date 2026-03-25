<?php

declare(strict_types=1);

namespace MarkShust\PolyshellPatch\Plugin;

use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Api\ImageProcessor;
use Magento\Framework\Api\Uploader;
use Magento\Framework\Exception\NoSuchEntityException;
use MarkShust\PolyshellPatch\Utility\Configurations;

/**
 * Enforce an allowlist of file extensions before ImageProcessor saves uploaded files.
 *
 * Mitigates PolyShell (APSB25-94): the core ImageProcessor never calls
 * setAllowedExtensions() on the Uploader, so any extension — including .php — is accepted.
 */
class ImageProcessorRestrictExtensions
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'gif', 'png'];

    /**
     * @var Uploader
     */
    private Uploader $uploader;

    /**
     * @var Configurations
     */
    private Configurations $configurations;

    /**
     * @param Uploader $uploader
     * @param Configurations $configurations
     */
    public function __construct(
        Uploader $uploader,
        Configurations $configurations
    ) {
        $this->uploader = $uploader;
        $this->configurations = $configurations;
    }

    /**
     * Before processImageContent, lock the uploader to image-only extensions.
     *
     * @param ImageProcessor $subject
     * @param string $entityType
     * @param ImageContentInterface $imageContent
     *
     * @return null
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws NoSuchEntityException
     */
    public function beforeProcessImageContent(
        ImageProcessor $subject,
        $entityType,
        $imageContent
    ) {
        if (!$this->configurations->isModuleEnabled()) {
            return null;
        }

        $this->uploader->setAllowedExtensions(self::ALLOWED_EXTENSIONS);
        return null;
    }
}
