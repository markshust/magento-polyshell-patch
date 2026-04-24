<?php

declare(strict_types=1);

namespace MarkShust\PolyshellPatch\Plugin;

use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Api\ImageProcessor;
use Magento\Framework\Exception\LocalizedException;
use MarkShust\PolyshellPatch\Model\StrictImagePayloadValidator;

/**
 * Runs strict binary validation and GD re-encode before media write.
 *
 * Size limits: {@see StrictImagePayloadValidator::MAX_BASE64_INPUT_CHARS} and
 * {@see StrictImagePayloadValidator::MAX_DECODED_BYTES} (reject oversized input before full decode).
 */
class ImageProcessorStrictBinaryValidation
{
    /**
     * @var StrictImagePayloadValidator
     */
    private StrictImagePayloadValidator $strictImagePayloadValidator;

    /**
     * @param StrictImagePayloadValidator $strictImagePayloadValidator
     */
    public function __construct(StrictImagePayloadValidator $strictImagePayloadValidator)
    {
        $this->strictImagePayloadValidator = $strictImagePayloadValidator;
    }

    /**
     * Validate payload before ImageProcessor writes to media.
     *
     * @param ImageProcessor $subject
     * @param callable $proceed
     * @param string $entityType
     * @param ImageContentInterface $imageContent
     * @return string
     * @throws LocalizedException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundProcessImageContent(
        ImageProcessor $subject,
        callable $proceed,
        $entityType,
        ImageContentInterface $imageContent
    ) {
        $encoded = $imageContent->getBase64EncodedData();
        if ($encoded === null || $encoded === '') {
            throw new LocalizedException(__('The image content is invalid. Verify the content and try again.'));
        }

        if (strlen($encoded) > StrictImagePayloadValidator::MAX_BASE64_INPUT_CHARS) {
            throw new LocalizedException(__('The image content is invalid. Verify the content and try again.'));
        }

        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- strict mode; invalid base64 returns false
        $binary = base64_decode($encoded, true);
        if ($binary === false || $binary === '') {
            throw new LocalizedException(__('The image content is invalid. Verify the content and try again.'));
        }

        $this->strictImagePayloadValidator->assertValidAndNormalize(
            $binary,
            (string) $imageContent->getType(),
            $imageContent
        );

        return $proceed($entityType, $imageContent);
    }
}
