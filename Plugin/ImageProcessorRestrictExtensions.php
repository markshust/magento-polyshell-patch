<?php

declare(strict_types=1);

namespace MarkShust\PolyshellPatch\Plugin;

use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Api\ImageProcessor;
use Magento\Framework\Api\Uploader;

/**
 * Enforce an allowlist of file extensions before ImageProcessor saves uploaded files.
 *
 * Mitigates PolyShell (APSB25-94): the core ImageProcessor never calls
 * setAllowedExtensions() on the Uploader, so any extension — including .php — is accepted.
 */
class ImageProcessorRestrictExtensions
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'gif', 'png'];

    private const MIME_EXTENSION_MAP = [
        'image/jpg'  => 'jpg',
        'image/jpeg' => 'jpg',
        'image/gif'  => 'gif',
        'image/png'  => 'png',
    ];

    /**
     * @var Uploader
     */
    private Uploader $uploader;

    /**
     * @param Uploader $uploader
     */
    public function __construct(Uploader $uploader)
    {
        $this->uploader = $uploader;
    }

    /**
     * Before processImageContent, lock the uploader to image-only extensions.
     *
     * Magento core stores ImageContentInterface::NAME as the filename without
     * extension when images are added programmatically (e.g. CLI import via
     * Product::addImageToMediaGallery). In this case the Uploader would reject
     * the file because an empty extension is not in the allowlist. We derive the
     * extension from the MIME type — which Magento has already validated — so that
     * both programmatic and form-based uploads pass the extension check.
     *
     * @param ImageProcessor $subject
     * @param string $entityType
     * @param ImageContentInterface $imageContent
     * @return null
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeProcessImageContent(
        ImageProcessor $subject,
        $entityType,
        $imageContent
    ) {
        if (!pathinfo($imageContent->getName(), PATHINFO_EXTENSION)) {
            $extension = self::MIME_EXTENSION_MAP[$imageContent->getType()] ?? null;
            if ($extension) {
                $imageContent->setName($imageContent->getName() . '.' . $extension);
            }
        }

        $this->uploader->setAllowedExtensions(self::ALLOWED_EXTENSIONS);
        return null;
    }
}
